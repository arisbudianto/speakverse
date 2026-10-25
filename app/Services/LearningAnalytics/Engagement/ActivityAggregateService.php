<?php

namespace App\Services\LearningAnalytics\Engagement;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loader DB tipis: membaca jejak aktivitas dari tabel yang sudah ada,
 * lalu menulis agregat harian ke la_daily_student (upsert, idempoten).
 *
 * Sumber jejak waktu (hanya yang tersedia di skema):
 *  learning_events.created_at · assessment_submissions.submitted_at (cadangan created_at)
 *  · user_lesson_progress.completed_at · gamification_transactions.created_at
 * Login/page-view TIDAK tercatat (lihat docs/learning-analytics/00-audit.md).
 */
class ActivityAggregateService
{
    private const CHUNK = 200;

    /** @return array{students:int,rows:int} */
    public function run(Carbon $since, ?int $onlyUser = null): array
    {
        $ids = User::query()
            ->where(function ($q) {
                $q->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
            })
            ->when($onlyUser, fn ($q) => $q->where('id', $onlyUser))
            ->pluck('id')->all();

        $rows = 0;
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $ts = array_fill_keys($chunk, []);
            $this->collect($ts, 'learning_events', 'created_at', $chunk, $since);
            $this->collect($ts, 'assessment_submissions', DB::raw('COALESCE(submitted_at, created_at)'), $chunk, $since);
            $this->collect($ts, 'user_lesson_progress', 'completed_at', $chunk, $since);
            $this->collect($ts, 'gamification_transactions', 'created_at', $chunk, $since);
            $quiz = $this->quizCounts($chunk, $since);

            $batch = [];
            $now = now();
            foreach ($ts as $uid => $list) {
                foreach (DailyAggregator::days($list) as $date => $d) {
                    if ($date < $since->format('Y-m-d')) {
                        continue;
                    }
                    $q = $quiz[$uid][$date] ?? ['a' => 0, 'c' => 0, 'h1' => 0, 'h2' => 0, 'h3' => 0];
                    $batch[] = [
                        'user_id' => $uid, 'date' => $date,
                        'sessions' => $d['sessions'], 'active_seconds' => $d['active_seconds'],
                        'events' => $d['events'], 'peak_hour' => $d['peak_hour'],
                        'quiz_answers' => $q['a'], 'quiz_correct' => $q['c'],
                        'hint_l1' => $q['h1'], 'hint_l2' => $q['h2'], 'hint_l3' => $q['h3'],
                        'computed_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($batch, 500) as $b) {
                DB::table('la_daily_student')->upsert($b, ['user_id', 'date'],
                    ['sessions', 'active_seconds', 'events', 'peak_hour', 'quiz_answers', 'quiz_correct', 'hint_l1', 'hint_l2', 'hint_l3', 'computed_at']);
            }
            $rows += count($batch);
        }

        return ['students' => count($ids), 'rows' => $rows];
    }

    /** @param array<int,int[]> $ts */
    private function collect(array &$ts, string $table, $col, array $ids, Carbon $since): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $isRaw = $col instanceof \Illuminate\Contracts\Database\Query\Expression;
        if (! $isRaw && ! Schema::hasColumn($table, $col)) {
            return;
        }
        // mundur 1 hari agar sesi lintas tengah malam tidak terpotong di batas $since
        $from = $since->copy()->subDay();
        $q = DB::table($table)->whereIn('user_id', $ids);
        $isRaw ? $q->whereRaw('COALESCE(submitted_at, created_at) >= ?', [$from]) : $q->where($col, '>=', $from)->whereNotNull($col);
        foreach ($q->select('user_id', DB::raw(($isRaw ? 'COALESCE(submitted_at, created_at)' : $col) . ' as ts'))->cursor() as $r) {
            $t = strtotime((string) $r->ts);
            if ($t) {
                $ts[$r->user_id][] = $t;
            }
        }
    }

    /** @return array<int,array<string,array{a:int,c:int,h1:int,h2:int,h3:int}>> */
    private function quizCounts(array $ids, Carbon $since): array
    {
        $out = [];
        if (! Schema::hasTable('learning_events')) {
            return $out;
        }
        $rows = DB::table('learning_events')
            ->whereIn('user_id', $ids)->where('created_at', '>=', $since)
            ->selectRaw("user_id, DATE(created_at) as d,
                SUM(CASE WHEN event_type IN ('select','revise') AND attempt_no = 1 THEN 1 ELSE 0 END) as a,
                SUM(CASE WHEN event_type IN ('select','revise') AND attempt_no = 1 AND is_correct = 1 THEN 1 ELSE 0 END) as c,
                SUM(CASE WHEN event_type = 'hint_request' AND hint_level_shown = 1 THEN 1 ELSE 0 END) as h1,
                SUM(CASE WHEN event_type = 'hint_request' AND hint_level_shown = 2 THEN 1 ELSE 0 END) as h2,
                SUM(CASE WHEN event_type = 'hint_request' AND hint_level_shown = 3 THEN 1 ELSE 0 END) as h3")
            ->groupBy('user_id', 'd')->get();
        foreach ($rows as $r) {
            $out[$r->user_id][(string) $r->d] = ['a' => (int) $r->a, 'c' => (int) $r->c, 'h1' => (int) $r->h1, 'h2' => (int) $r->h2, 'h3' => (int) $r->h3];
        }

        return $out;
    }
}
