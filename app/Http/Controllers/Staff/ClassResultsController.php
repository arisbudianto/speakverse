<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\Lesson;
use App\Models\LearningEvent;
use App\Models\TeacherScaffoldingPolicy;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Services\Learning\DiagnosticEngine;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassResultsController extends Controller
{
    public function show(Request $request, DiagnosticEngine $diagnosticEngine): View
    {
        $class = $this->validatedClass($request);
        $this->authorizeClass($class);

        $report = $this->buildReport($class, $diagnosticEngine);

        return view('staff.classes.results', $report);
    }

    public function export(Request $request, DiagnosticEngine $diagnosticEngine)
    {
        $class = $this->validatedClass($request);
        $this->authorizeClass($class);
        $report = $this->buildReport($class, $diagnosticEngine);

        $headers = [
            'Nama', 'Email', 'Sekolah', 'Jurusan', 'Kelas', 'Paralel',
            'XP', 'Level', 'Listening', 'Reading', 'Writing', 'Speaking',
            'Rata-rata Pretest', 'Rata-rata Posttest', 'Pelajaran Selesai',
            'Status Penyelesaian', 'Waktu Pengerjaan (Akun Dibuat → Selesai Semua Unit)',
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
                $row['is_fully_completed'] ? 'Selesai semua unit' : 'Belum selesai semua unit',
                $row['is_fully_completed'] ? ($row['duration_label'] ?? '-') : '-',
            ];
        }

        $name = 'hasil-'.$class['grade'].$class['parallel'].'-'.now()->format('Ymd').'.xlsx';

        return SimpleXlsxWriter::download($name, $headers, $rows);
    }

    /*
    |--------------------------------------------------------------------------
    | Modul 6b — Teacher HITL Override UI
    |--------------------------------------------------------------------------
    */

    /**
     * Guru/admin membuat kebijakan baru: untuk satu siswa tertentu
     * di kelas ini, atau untuk seluruh kelas.
     */
    public function storeOverride(Request $request): RedirectResponse
    {
        $class = $this->validatedClass($request);
        $this->authorizeClass($class);

        $validated = $request->validate([
            'scope_type' => ['required', Rule::in(['student', 'class'])],
            'student_id' => ['required_if:scope_type,student', 'nullable', 'integer'],
            'freeze_level' => ['nullable', 'integer', 'min:1', 'max:3'],
            'max_level' => ['nullable', 'integer', 'min:1', 'max:3'],
            'min_level' => ['nullable', 'integer', 'min:1', 'max:3'],
            'require_hint_before_recheck' => ['nullable', 'boolean'],
            'disable_llm' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $payload = [
            'teacher_id' => Auth::id(),
            'scope_type' => $validated['scope_type'],
            'freeze_level' => $validated['freeze_level'] ?? null,
            'max_level' => $validated['max_level'] ?? null,
            'min_level' => $validated['min_level'] ?? null,
            'require_hint_before_recheck' => (bool) ($validated['require_hint_before_recheck'] ?? false),
            'disable_llm' => (bool) ($validated['disable_llm'] ?? false),
            'note' => $validated['note'] ?? null,
            'expires_at' => isset($validated['expires_in_days'])
                ? now()->addDays($validated['expires_in_days'])
                : null,
        ];

        if ($validated['scope_type'] === 'student') {
            // Pastikan siswa yang dipilih memang benar ada di kelas
            // ini — bukan asal ID dari kelas lain.
            $student = User::query()
                ->where('id', $validated['student_id'])
                ->where('school', $class['school'])
                ->where('major', $class['major'])
                ->where('grade', $class['grade'])
                ->where('parallel', $class['parallel'])
                ->firstOrFail();

            $payload['student_id'] = $student->id;
        } else {
            $payload['school'] = $class['school'];
            $payload['major'] = $class['major'];
            $payload['grade'] = $class['grade'];
            $payload['parallel'] = $class['parallel'];
        }

        TeacherScaffoldingPolicy::create($payload);

        return back()->with('success', 'Kebijakan bantuan AI berhasil disimpan.');
    }

    /**
     * Hapus satu kebijakan. Otorisasi diturunkan dari kelas yang
     * terkait kebijakan itu sendiri (langsung kalau scope_type=class,
     * lewat data siswa kalau scope_type=student) — bukan dari
     * parameter request, supaya tidak bisa dipalsukan.
     */
    public function destroyOverride(TeacherScaffoldingPolicy $policy): RedirectResponse
    {
        if ($policy->scope_type === 'class') {
            $class = [
                'school' => $policy->school,
                'major' => $policy->major,
                'grade' => $policy->grade,
                'parallel' => $policy->parallel,
            ];
        } else {
            $student = $policy->student;
            abort_if(! $student, 404);

            $class = [
                'school' => $student->school,
                'major' => $student->major,
                'grade' => $student->grade,
                'parallel' => $student->parallel,
            ];
        }

        $this->authorizeClass($class);

        $policy->delete();

        return back()->with('success', 'Kebijakan bantuan AI dihapus.');
    }

    private function buildReport(array $class, DiagnosticEngine $diagnosticEngine): array
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

        // Total lesson (unit × skill) yang ada di seluruh kurikulum
        // (Pre-Test, Unit 1–4, Post-Test). Dipakai untuk menentukan
        // apakah seorang siswa sudah benar-benar menyelesaikan SEMUA
        // unit, bukan cuma sebagian.
        $totalCurriculumLessons = Lesson::query()->count();

        $ids = $students->pluck('id');

        /*
        |--------------------------------------------------------------------------
        | Modul 2 — Diagnostic Engine
        |--------------------------------------------------------------------------
        |
        | Frekuensi error_code (reading) untuk seluruh siswa di kelas
        | ini. DoD Modul 2: "laporan bisa menghitung frekuensi
        | error_code per kelas".
        */
        $errorFrequency = $diagnosticEngine
            ->errorFrequency($ids->all(), 'reading');

        $errorCodeLabels = [
            'lexical' => 'Lexical',
            'inferential' => 'Inferential',
            'syntactic' => 'Syntactic',
            'context_misconception' => 'Context Misconception',
        ];

        /*
        |--------------------------------------------------------------------------
        | Modul 6 — Teacher HITL Override: data "perlu intervensi"
        |--------------------------------------------------------------------------
        |
        | DoD: "Daftar 'perlu intervensi': error inferensial > ambang
        | atau hint L3 >= N." Dihitung sekaligus untuk semua siswa di
        | kelas ini (bukan query per siswa di dalam loop) supaya tidak
        | N+1.
        */
        $inferentialWrongByStudent = collect();
        $hintL3ByStudent = collect();

        if ($ids->isNotEmpty()) {
            $inferentialWrongByStudent = LearningEvent::query()
                ->whereIn('user_id', $ids)
                ->where('skill', 'reading')
                ->whereIn('event_type', ['select', 'revise'])
                ->where('attempt_no', 1)
                ->where('is_correct', false)
                ->where('error_code', 'inferential')
                ->selectRaw('user_id, count(*) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id');

            $hintL3ByStudent = LearningEvent::query()
                ->whereIn('user_id', $ids)
                ->where('event_type', 'hint_request')
                ->where('hint_level_shown', 3)
                ->selectRaw('user_id, count(*) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id');
        }

        $inferentialThreshold = (int) config('learning.intervention.inferential_wrong_threshold', 2);
        $hintL3Threshold = (int) config('learning.intervention.hint_l3_threshold', 2);

        /*
        |--------------------------------------------------------------------------
        | Modul 6 — kebijakan aktif untuk kelas ini (class-wide + per
        | siswa di kelas ini), dipakai UI "Kelola Bantuan AI".
        |--------------------------------------------------------------------------
        */
        $activePolicies = TeacherScaffoldingPolicy::query()
            ->active()
            ->where(function ($query) use ($class, $ids) {
                $query->where(function ($q) use ($class) {
                    $q->where('scope_type', 'class')
                        ->where('school', $class['school'])
                        ->where('major', $class['major'])
                        ->where('grade', $class['grade'])
                        ->where('parallel', $class['parallel']);
                });

                if ($ids->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($ids) {
                        $q->where('scope_type', 'student')
                            ->whereIn('student_id', $ids);
                    });
                }
            })
            ->with(['student:id,name', 'teacher:id,name'])
            ->orderByDesc('id')
            ->get();

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

            $completedRows = $progress->get($student->id, collect())->filter(function ($row) {
                return empty($row->status) || $row->status === 'completed';
            });
            $completed = $completedRows->count();

            // Waktu pengerjaan total = dari akun dibuat (created_at)
            // sampai lesson TERAKHIR yang diselesaikan (completed_at
            // paling baru). Hanya berarti kalau siswa sudah
            // menyelesaikan SEMUA lesson di kurikulum (Pre-Test s.d.
            // Post-Test) — kalau belum, durasi ini belum final jadi
            // tidak dipakai untuk ranking "tercepat".
            $lastCompletedAt = $completedRows->max('completed_at');
            $isFullyCompleted = $totalCurriculumLessons > 0
                && $completed >= $totalCurriculumLessons;
            $durationSeconds = null;
            if ($isFullyCompleted && $lastCompletedAt) {
                $durationSeconds = (int) $student->created_at
                    ->diffInSeconds($lastCompletedAt);
            }

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

            $inferentialWrong = (int) ($inferentialWrongByStudent[$student->id] ?? 0);
            $hintL3Count = (int) ($hintL3ByStudent[$student->id] ?? 0);
            $needsIntervention = $inferentialWrong > $inferentialThreshold
                || $hintL3Count >= $hintL3Threshold;

            $rows[] = [
                'id' => $student->id,
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
                'total_curriculum_lessons' => $totalCurriculumLessons,
                'is_fully_completed' => $isFullyCompleted,
                'duration_seconds' => $durationSeconds,
                'duration_label' => $this->formatDuration($durationSeconds),

                // Modul 6
                'inferential_wrong' => $inferentialWrong,
                'hint_l3_count' => $hintL3Count,
                'needs_intervention' => $needsIntervention,
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

        // Daftar siswa yang BERHASIL menyelesaikan seluruh unit
        // (Pre-Test s.d. Post-Test), diurutkan dari yang tercepat.
        // Sengaja tidak dibatasi (tidak ->take(N)) supaya guru bisa
        // melihat SEMUA siswa yang sudah tuntas, bukan cuma beberapa
        // yang tercepat — peringkat 1 di daftar inilah yang tercepat.
        $fastestCompletion = collect($rows)
            ->filter(fn ($row) => $row['is_fully_completed'] && $row['duration_seconds'] !== null)
            ->sortBy('duration_seconds')
            ->values()
            ->map(fn ($row) => [
                'name' => $row['name'],
                'duration_seconds' => $row['duration_seconds'],
                'duration_label' => $row['duration_label'],
            ])
            ->all();

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
                'total_curriculum_lessons' => $totalCurriculumLessons,
                'fastest_completion' => $fastestCompletion,
                'completed_count' => count($fastestCompletion),

                // Modul 2 — Diagnostic Engine
                'error_frequency' => $errorFrequency,
                'error_code_labels' => $errorCodeLabels,
                'error_frequency_total' => array_sum($errorFrequency),

                // Modul 6 — Teacher HITL Override
                'intervention_list' => collect($rows)->filter(fn ($row) => $row['needs_intervention'])->values()->all(),
                'inferential_threshold' => $inferentialThreshold,
                'hint_l3_threshold' => $hintL3Threshold,
                'active_policies' => $activePolicies,
            ],
        ];
    }

    /**
     * Format durasi detik menjadi label singkat berbahasa Indonesia,
     * mis. "2 hari 3 jam 15 menit" atau "45 menit".
     */
    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $seconds = max(0, $seconds);

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' hari';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' jam';
        }
        if ($minutes > 0 || $parts === []) {
            $parts[] = $minutes . ' menit';
        }

        return implode(' ', $parts);
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
