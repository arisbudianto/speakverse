<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassResultsController extends Controller
{
    public function show(Request $request): View
    {
        $class = $this->validatedClass($request);
        $this->authorizeClass($class);

        $report = $this->buildReport($class);

        return view('staff.classes.results', $report);
    }

    public function export(Request $request)
    {
        $class = $this->validatedClass($request);
        $this->authorizeClass($class);
        $report = $this->buildReport($class);

        $headers = [
            'Nama', 'Email', 'Sekolah', 'Jurusan', 'Kelas', 'Paralel',
            'XP', 'Level', 'Listening', 'Reading', 'Writing', 'Speaking',
            'Rata-rata Pretest', 'Rata-rata Posttest', 'Pelajaran Selesai',
        ];

        $rows = [];
        foreach ($report['rows'] as $row) {
            $rows[] = [
                $row['name'],
                $row['email'],
                $row['school'],
                $row['major'],
                $row['grade'],
                $row['parallel'],
                $row['xp'],
                $row['level'],
                $row['listening'],
                $row['reading'],
                $row['writing'],
                $row['speaking'],
                $row['pretest_avg'],
                $row['posttest_avg'],
                $row['completed_lessons'],
            ];
        }

        $name = 'hasil-'.$class['grade'].$class['parallel'].'-'.now()->format('Ymd').'.xlsx';

        return SimpleXlsxWriter::download($name, $headers, $rows);
    }

    private function buildReport(array $class): array
    {
        $students = User::query()
            ->where(function ($q) {
                $q->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
            })
            ->where('school', $class['school'])
            ->where('major', $class['major'])
            ->where('grade', $class['grade'])
            ->where('parallel', $class['parallel'])
            ->orderBy('name')
            ->get();

        $ids = $students->pluck('id');
        $submissions = collect();
        $progress = collect();

        if ($ids->isNotEmpty()) {
            try {
                $submissions = AssessmentSubmission::query()
                    ->whereIn('user_id', $ids)
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('user_id');
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                $progress = UserLessonProgress::query()
                    ->whereIn('user_id', $ids)
                    ->get()
                    ->groupBy('user_id');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $rows = [];
        $sum = [
            'xp' => 0,
            'listening' => 0,
            'reading' => 0,
            'writing' => 0,
            'speaking' => 0,
            'pretest' => 0,
            'posttest' => 0,
        ];
        $countSkill = ['listening' => 0, 'reading' => 0, 'writing' => 0, 'speaking' => 0, 'pretest' => 0, 'posttest' => 0];
        $bands = ['belum' => 0, 'berkembang' => 0, 'mahir' => 0];
        $weeklyBucket = [];
        $remedialStudents = [];

        foreach ($students as $student) {
            $userSubs = $submissions->get($student->id, collect());
            $skills = [];
            foreach (['listening', 'reading', 'writing', 'speaking'] as $skill) {
                $latest = $userSubs->firstWhere('skill', $skill);
                $skills[$skill] = $latest?->final_score;
                if ($skills[$skill] !== null) {
                    $sum[$skill] += $skills[$skill];
                    $countSkill[$skill]++;
                }
            }

            $pre = $userSubs->where('type', 'pretest')->avg('final_score');
            $post = $userSubs->where('type', 'posttest')->avg('final_score');
            if ($pre !== null) {
                $sum['pretest'] += $pre;
                $countSkill['pretest']++;
            }
            if ($post !== null) {
                $sum['posttest'] += $post;
                $countSkill['posttest']++;
            }

            $completed = $progress->get($student->id, collect())->filter(function ($row) {
                return empty($row->status) || $row->status === 'completed';
            })->count();
            $avgFour = collect($skills)->filter(fn ($v) => $v !== null)->avg();
            if ($avgFour === null) {
                $bands['belum']++;
            } elseif ($avgFour < 70) {
                $bands['berkembang']++;
            } else {
                $bands['mahir']++;
            }

            $sum['xp'] += (int) $student->xp;

            foreach ($userSubs as $sub) {
                $when = $sub->submitted_at ?? $sub->created_at ?? null;
                if (!$when) {
                    continue;
                }
                $weekKey = \Carbon\Carbon::parse($when)->startOfWeek()->format('Y-m-d');
                if (!isset($weeklyBucket[$weekKey])) {
                    $weeklyBucket[$weekKey] = ['n' => 0, 'sum' => 0];
                }
                if ($sub->final_score !== null) {
                    $weeklyBucket[$weekKey]['n']++;
                    $weeklyBucket[$weekKey]['sum'] += (float) $sub->final_score;
                }
            }

            $weakSkills = [];
            foreach ($skills as $skillName => $skillScore) {
                if ($skillScore !== null && $skillScore < 70) {
                    $weakSkills[] = $skillName;
                }
            }
            if ($avgFour === null || $weakSkills !== []) {
                $remedialStudents[] = [
                    'name' => $student->name,
                    'weak_skills' => $avgFour === null ? ['belum mengerjakan'] : $weakSkills,
                    'reason' => $avgFour === null
                        ? 'Belum ada skor AI/tes yang terekam.'
                        : 'Skor di bawah 70 pada: ' . implode(', ', $weakSkills),
                ];
            }

            $rows[] = [
                'name' => $student->name,
                'email' => $student->email,
                'school' => $student->school,
                'major' => $student->major,
                'grade' => $student->grade,
                'parallel' => $student->parallel,
                'xp' => (int) $student->xp,
                'level' => $student->level,
                'listening' => $skills['listening'],
                'reading' => $skills['reading'],
                'writing' => $skills['writing'],
                'speaking' => $skills['speaking'],
                'pretest_avg' => $pre !== null ? round($pre, 1) : null,
                'posttest_avg' => $post !== null ? round($post, 1) : null,
                'completed_lessons' => $completed,
            ];
        }

        ksort($weeklyBucket);
        $weeklyLabels = [];
        $weeklyScores = [];
        foreach ($weeklyBucket as $weekKey => $bucket) {
            $weeklyLabels[] = \Carbon\Carbon::parse($weekKey)->isoFormat('D MMM');
            $weeklyScores[] = $bucket['n'] > 0 ? round($bucket['sum'] / $bucket['n'], 1) : 0;
        }

        $weakClassSkills = [];
        foreach (['listening', 'reading', 'writing', 'speaking'] as $skillName) {
            if ($countSkill[$skillName] > 0) {
                $avgSkill = round($sum[$skillName] / $countSkill[$skillName], 1);
                if ($avgSkill < 70) {
                    $weakClassSkills[$skillName] = $avgSkill;
                }
            }
        }

        $skillAdvice = [
            'listening' => 'Remedial listening: ulang audio pendek 1–2 menit, latihan soal detail (angka, nama, langkah), lalu kuis lisan 5 butir.',
            'reading' => 'Remedial reading: teks 80–120 kata, tandai kata kunci, latihan main idea + referensi kata ganti.',
            'writing' => 'Remedial writing: kerangka 3 paragraf, tulis ulang 80 kata, fokus tugas (bukan meniru soal) dan mechanics.',
            'speaking' => 'Remedial speaking: rekam 30–45 detik, cek relevansi jawaban, latihan pelafalan 5 frasa target.',
            'belum mengerjakan' => 'Siswa belum submit. Beri batas waktu Pre-Test/Unit dan dampingi login pertama.',
        ];

        $remediations = [];
        if ($weakClassSkills !== []) {
            foreach ($weakClassSkills as $skillName => $avgSkill) {
                $remediations[] = [
                    'title' => 'Kelas lemah di ' . $skillName . ' (rata-rata ' . $avgSkill . ')',
                    'action' => $skillAdvice[$skillName],
                ];
            }
        }
        foreach ($remedialStudents as $item) {
            $actions = [];
            foreach ($item['weak_skills'] as $weakSkill) {
                $actions[] = $skillAdvice[$weakSkill] ?? $skillAdvice['belum mengerjakan'];
            }
            $remediations[] = [
                'title' => $item['name'] . ' — ' . $item['reason'],
                'action' => implode(' ', array_unique($actions)),
            ];
        }
        if ($remediations === []) {
            $remediations[] = [
                'title' => 'Belum ada rekomendasi khusus',
                'action' => 'Nilai kelas masih terbatas atau sudah di atas 70. Lanjut pengayaan teks/dialog sesuai unit berjalan.',
            ];
        }

        $total = max($students->count(), 1);
        $avg = function (string $key) use ($sum, $countSkill, $total) {
            if (in_array($key, ['listening', 'reading', 'writing', 'speaking', 'pretest', 'posttest'], true)) {
                return $countSkill[$key] ? round($sum[$key] / $countSkill[$key], 1) : null;
            }

            return round($sum['xp'] / $total, 1);
        };

        return [
            'class' => $class,
            'students' => $students,
            'rows' => $rows,
            'analysis' => [
                'total' => $students->count(),
                'avg_xp' => $avg('xp'),
                'avg_listening' => $avg('listening'),
                'avg_reading' => $avg('reading'),
                'avg_writing' => $avg('writing'),
                'avg_speaking' => $avg('speaking'),
                'avg_pretest' => $avg('pretest'),
                'avg_posttest' => $avg('posttest'),
                'gain' => ($avg('pretest') !== null && $avg('posttest') !== null)
                    ? round($avg('posttest') - $avg('pretest'), 1)
                    : null,
                'bands' => $bands,
                'weekly_labels' => $weeklyLabels,
                'weekly_scores' => $weeklyScores,
                'remediations' => $remediations,
            ],
        ];
    }

    private function validatedClass(Request $request): array
    {
        $schools = config('schools', []);
        $majors = $schools[$request->school] ?? [];

        return $request->validate([
            'school' => ['required', Rule::in(array_keys($schools))],
            'major' => ['required', Rule::in($majors)],
            'grade' => ['required', Rule::in(['X', 'XI', 'XII'])],
            'parallel' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ]);
    }

    private function authorizeClass(array $class): void
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
            return;
        }

        abort_unless(
            $user->teachingAssignments()
                ->where('school', $class['school'])
                ->where('major', $class['major'])
                ->where('grade', $class['grade'])
                ->where('parallel', $class['parallel'])
                ->exists(),
            403
        );
    }
}
