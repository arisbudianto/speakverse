<?php

namespace App\Services\LearningAnalytics;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Definisi kohort penelitian: siswa sungguhan, tanpa akun uji / yang dikecualikan admin.
 * Aman dijalankan sebelum migrasi tahap 2 (kolom belum ada → tidak ada yang dikecualikan).
 */
final class Cohort
{
    private static ?bool $has = null;

    public static function hasExclusion(): bool
    {
        return self::$has ??= Schema::hasColumn('users', 'exclude_from_analytics');
    }

    public static function students(): Builder
    {
        $q = User::query()->where(function ($q) {
            $q->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
        });
        if (self::hasExclusion()) {
            $q->where('exclude_from_analytics', false)->where('is_test', false);
        }

        return $q;
    }

    /** Saring query yang sudah join users dengan alias $alias. */
    public static function whereJoined($query, string $alias = 'u')
    {
        if (self::hasExclusion()) {
            $query->where($alias . '.exclude_from_analytics', false)->where($alias . '.is_test', false);
        }

        return $query;
    }

    /** @return int[] id akun yang dikecualikan (untuk menyaring tabel yang hanya punya user_id) */
    public static function excludedIds(): array
    {
        return self::hasExclusion() ? DB::table('users')->where(fn ($q) => $q->where('exclude_from_analytics', true)->orWhere('is_test', true))->pluck('id')->all() : [];
    }

    /** @return array<string,int> alasan → jumlah, untuk catatan "n dikecualikan" di halaman */
    public static function excludedSummary(): array
    {
        if (! self::hasExclusion()) {
            return [];
        }
        $rows = DB::table('users')->where(fn ($q) => $q->where('exclude_from_analytics', true)->orWhere('is_test', true))
            ->selectRaw("CASE WHEN is_test = 1 THEN 'akun uji' ELSE COALESCE(NULLIF(exclude_reason, ''), 'dikecualikan') END r, COUNT(*) c")->groupBy('r')->pluck('c', 'r')->all();

        return array_map('intval', $rows);
    }
}
