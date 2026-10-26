<?php

namespace App\Services\LearningAnalytics\Engagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Memuat la_daily_student menjadi peta hari per siswa; menyertakan pemisahan aktivitas belajar vs tes bila kolomnya ada. */
final class DaysLoader
{
    private static ?bool $split = null;

    public static function hasSplit(): bool
    {
        return self::$split ??= Schema::hasColumn('la_daily_student', 'learn_events');
    }

    /**
     * @param int[] $ids
     * @return array<int,array<string,array<string,int>>> user => 'Y-m-d' => [...]
     */
    public static function load(array $ids, string $from): array
    {
        $cols = ['user_id', 'date', 'sessions', 'active_seconds', 'events', 'peak_hour'];
        if (self::hasSplit()) {
            array_push($cols, 'learn_events', 'test_events');
        }
        $days = [];
        foreach (array_chunk($ids, 800) as $chunk) {
            foreach (DB::table('la_daily_student')->whereIn('user_id', $chunk)->where('date', '>=', $from)->get($cols) as $r) {
                $row = ['sessions' => (int) $r->sessions, 'active_seconds' => (int) $r->active_seconds, 'events' => (int) $r->events, 'peak_hour' => (int) $r->peak_hour];
                if (self::hasSplit()) {
                    $row['learn_events'] = (int) $r->learn_events;
                    $row['test_events'] = (int) $r->test_events;
                }
                $days[(int) $r->user_id][substr((string) $r->date, 0, 10)] = $row;
            }
        }

        return $days;
    }
}
