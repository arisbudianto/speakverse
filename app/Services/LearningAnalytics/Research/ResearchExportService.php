<?php

namespace App\Services\LearningAnalytics\Research;

use App\Services\Analytics\SchoolLearningAnalytics;
use App\Services\LearningAnalytics\Engagement\EngagementMetrics;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Menyusun baris ekspor riset (pseudonim atau teridentifikasi). Tidak menulis apa pun ke database. */
class ResearchExportService
{
    public function __construct(private readonly SchoolLearningAnalytics $base)
    {
    }

    /**
     * @param bool $identified  true = sertakan nama/email/sekolah asli (di luar skema pseudonim)
     * @param bool $includeUnconsented  true = ikutkan siswa yang belum menyetujui (wajib alasan; dicatat)
     * @return array{headers:array,rows:array,n:int,excluded:int}
     */
    public function build(bool $identified, bool $includeUnconsented): array
    {
        $today = new DateTimeImmutable('today');
        $students = $this->base->report(null, null)['students'];
        $ids = array_column($students, 'id');
        $consent = Schema::hasTable('la_consents') ? DB::table('la_consents')->pluck('accepted', 'user_id') : collect();
        $group = DB::table('users')->whereIn('id', $ids)->pluck('research_group', 'id');
        $srl = Schema::hasTable('la_srl_student') ? DB::table('la_srl_student')->get()->keyBy('user_id') : collect();
        $risk = Schema::hasTable('la_risk_scores') ? DB::table('la_risk_scores')->get()->keyBy('user_id') : collect();
        $mastered = Schema::hasTable('la_mastery') ? DB::table('la_mastery')->where('mastered', true)->selectRaw('user_id, COUNT(*) c')->groupBy('user_id')->pluck('c', 'user_id') : collect();
        $wheel = Schema::hasTable('la_mastery') ? DB::table('la_mastery')->where('wheel_spinning', true)->selectRaw('user_id, COUNT(*) c')->groupBy('user_id')->pluck('c', 'user_id') : collect();
        $days = [];
        foreach (array_chunk($ids, 800) as $chunk) {
            foreach (DB::table('la_daily_student')->whereIn('user_id', $chunk)->where('date', '>=', $today->modify('-40 days')->format('Y-m-d'))
                ->get(['user_id', 'date', 'sessions', 'active_seconds', 'events', 'peak_hour']) as $r) {
                $days[$r->user_id][substr((string) $r->date, 0, 10)] = ['sessions' => (int) $r->sessions, 'active_seconds' => (int) $r->active_seconds,
                    'events' => (int) $r->events, 'peak_hour' => (int) $r->peak_hour];
            }
        }
        $em = new EngagementMetrics();
        $ps = new Pseudonymizer(hash('sha256', 'la-research|' . config('app.key')));
        $rows = [];
        $excluded = 0;
        foreach ($students as $s) {
            $id = $s['id'];
            $accepted = $consent->has($id) ? (bool) $consent[$id] : null;
            if ($accepted !== true && ! $includeUnconsented) {
                $excluded++;
                continue;
            }
            $eng = $em->student($days[$id] ?? [], $today);
            $sr = $srl[$id] ?? null;
            $enough = $sr && $sr->enough;
            $vals = [
                'major' => $s['major'], 'grade' => $s['grade'], 'research_group' => $group[$id] ?? null,
                'consent' => $accepted === null ? 'belum menjawab' : ($accepted ? 'setuju' : 'tidak setuju'),
                'listening' => $s['skills']['listening'], 'reading' => $s['skills']['reading'], 'writing' => $s['skills']['writing'], 'speaking' => $s['skills']['speaking'],
                'pretest' => $s['pre'], 'posttest' => $s['post'], 'gain' => $s['gain'], 'xp' => $s['xp'], 'lesson_progress' => round($s['progress'], 3),
                'active_days_28' => $eng['active_days'], 'sessions_28' => $eng['sessions'], 'minutes_28' => $eng['minutes'], 'regularity_index' => $eng['regularity_index'],
                'engagement_pattern' => $eng['pattern'],
                'quiz_items' => $enough ? $sr->items : null, 'first_try_acc' => $enough ? (float) $sr->first_try_acc : null, 'hint_rate' => $enough ? (float) $sr->hint_rate : null,
                'hint_l3_rate' => $enough ? (float) $sr->hint_l3_rate : null, 'fast_wrong_rate' => $enough && $sr->fast_wrong_rate !== null ? (float) $sr->fast_wrong_rate : null,
                'behavior_profile' => $enough ? $sr->profile : null, 'mastered_competencies' => (int) ($mastered[$id] ?? 0), 'wheel_spinning_competencies' => (int) ($wheel[$id] ?? 0),
                'risk_score' => isset($risk[$id]) ? (float) $risk[$id]->score : null, 'risk_level' => $risk[$id]->level ?? null,
            ];
            $row = $ps->row($id, $s['school'], $vals);
            if ($identified) {
                $row = ['nama' => $s['name'], 'email' => $s['email'], 'sekolah' => $s['school'], 'paralel' => $s['parallel']] + $row;
            }
            $rows[] = array_values($row);
        }
        $headers = array_keys($identified ? (['nama' => 1, 'email' => 1, 'sekolah' => 1, 'paralel' => 1] + array_flip(Pseudonymizer::COLUMNS)) : array_flip(Pseudonymizer::COLUMNS));

        return ['headers' => $headers, 'rows' => $rows, 'n' => count($rows), 'excluded' => $excluded];
    }
}
