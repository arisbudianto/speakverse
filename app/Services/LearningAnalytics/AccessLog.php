<?php

namespace App\Services\LearningAnalytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Mencatat akses ke data tingkat-siswa di fitur Learning Analytics (akuntabilitas, UU PDP). Gagal diam-diam bila tabel belum ada. */
final class AccessLog
{
    public static function record(string $action, ?string $target = null, array $meta = []): void
    {
        try {
            if (Schema::hasTable('la_access_log')) {
                DB::table('la_access_log')->insert(['actor_id' => auth()->id(), 'action' => $action, 'target' => $target, 'meta' => $meta ? json_encode($meta) : null, 'created_at' => now()]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
