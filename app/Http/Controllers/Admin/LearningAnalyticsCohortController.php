<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LearningAnalytics\AccessLog;
use App\Services\LearningAnalytics\Cohort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Kohort penelitian: tandai akun uji / dikecualikan, tinjau kandidat duplikat dan akun tanpa sekolah. Tidak ada penggabungan otomatis. */
class LearningAnalyticsCohortController extends Controller
{
    private const TEST_PATTERN = '(uji|test|demo|dummy|coba|trial)';

    public function index(Request $request): View
    {
        $ready = Cohort::hasExclusion();
        $v = $request->validate(['tampil' => ['nullable', 'in:semua,tanpa_sekolah,mirip_uji,dikecualikan'], 'school' => ['nullable', 'string', 'max:150'], 'q' => ['nullable', 'string', 'max:80']]);
        $tampil = $v['tampil'] ?? 'semua';
        $rows = null;
        $dups = collect();
        $summary = [];
        $schools = [];
        if ($ready) {
            $base = fn () => User::query()->where(function ($q) {
                $q->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
            });
            $q = $base();
            match ($tampil) {
                'tanpa_sekolah' => $q->where(fn ($w) => $w->whereNull('school')->orWhere('school', '')),
                'mirip_uji' => $q->where(fn ($w) => $w->whereRaw('LOWER(name) REGEXP ?', [self::TEST_PATTERN])->orWhereRaw('LOWER(email) REGEXP ?', [self::TEST_PATTERN])),
                'dikecualikan' => $q->where(fn ($w) => $w->where('is_test', true)->orWhere('exclude_from_analytics', true)),
                default => null,
            };
            if (! empty($v['school'])) {
                $v['school'] === '(Belum diisi)' ? $q->where(fn ($w) => $w->whereNull('school')->orWhere('school', '')) : $q->where('school', $v['school']);
            }
            if (! empty($v['q'])) {
                $q->where(fn ($w) => $w->where('name', 'like', '%' . $v['q'] . '%')->orWhere('email', 'like', '%' . $v['q'] . '%'));
            }
            $rows = $q->orderBy('name')->simplePaginate(50, ['id', 'name', 'email', 'school', 'grade', 'major', 'parallel', 'is_test', 'exclude_from_analytics', 'exclude_reason'])->withQueryString();
            $summary = [
                'total' => $base()->count(),
                'dikecualikan' => $base()->where(fn ($w) => $w->where('is_test', true)->orWhere('exclude_from_analytics', true))->count(),
                'tanpa_sekolah' => $base()->where(fn ($w) => $w->whereNull('school')->orWhere('school', ''))->count(),
                'mirip_uji' => $base()->where(fn ($w) => $w->whereRaw('LOWER(name) REGEXP ?', [self::TEST_PATTERN])->orWhereRaw('LOWER(email) REGEXP ?', [self::TEST_PATTERN]))->count(),
            ];
            $schools = array_merge(['(Belum diisi)'], $base()->whereNotNull('school')->where('school', '!=', '')->distinct()->orderBy('school')->pluck('school')->all());
            // kandidat duplikat: nama sama setelah dinormalisasi (huruf kecil, spasi ganda dirapikan)
            $dupNames = DB::table('users')->where(fn ($w) => $w->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user'))
                ->selectRaw("LOWER(TRIM(REPLACE(REPLACE(name, '  ', ' '), '  ', ' '))) n, COUNT(*) c")->groupBy('n')->having('c', '>', 1)->orderByDesc('c')->limit(30)->pluck('c', 'n');
            foreach ($dupNames as $n => $c) {
                $dups->push(['name' => $n, 'users' => User::query()->whereRaw("LOWER(TRIM(REPLACE(REPLACE(name, '  ', ' '), '  ', ' '))) = ?", [$n])
                    ->get(['id', 'name', 'email', 'school', 'grade', 'major', 'parallel', 'is_test', 'exclude_from_analytics', 'created_at'])]);
            }
            AccessLog::recordHourly('view_cohort', $tampil);
        }

        return view('admin.learning-analytics.cohort', compact('ready', 'rows', 'dups', 'summary', 'schools', 'tampil') + ['filters' => $v]);
    }

    public function mark(Request $request): RedirectResponse
    {
        abort_unless(Cohort::hasExclusion(), 404);
        $v = $request->validate([
            'mode' => ['required', 'in:ids,school,emails'], 'ids' => ['nullable', 'array', 'max:2000'], 'ids.*' => ['integer'],
            'school' => ['nullable', 'string', 'max:150'], 'emails' => ['nullable', 'string', 'max:20000'],
            'action' => ['required', 'in:test,exclude,include'], 'reason' => ['nullable', 'string', 'max:120'],
        ]);
        if ($v['action'] !== 'include' && mb_strlen(trim($v['reason'] ?? '')) < 3) {
            return back()->withErrors(['reason' => 'Alasan wajib diisi (≥3 karakter) saat mengecualikan akun.'])->withInput();
        }
        $q = User::query()->where(function ($w) {
            $w->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
        });
        if ($v['mode'] === 'ids') {
            $q->whereIn('id', $v['ids'] ?? []);
        } elseif ($v['mode'] === 'school') {
            abort_if(empty($v['school']), 422);
            $v['school'] === '(Belum diisi)' ? $q->where(fn ($w) => $w->whereNull('school')->orWhere('school', '')) : $q->where('school', $v['school']);
        } else {
            $emails = array_values(array_filter(array_map(fn ($e) => mb_strtolower(trim($e)), preg_split('/[\s,;]+/', (string) ($v['emails'] ?? '')))));
            $q->whereIn(DB::raw('LOWER(email)'), $emails);
        }
        $set = match ($v['action']) {
            'test' => ['is_test' => true, 'exclude_from_analytics' => true, 'exclude_reason' => $v['reason']],
            'exclude' => ['is_test' => false, 'exclude_from_analytics' => true, 'exclude_reason' => $v['reason']],
            default => ['is_test' => false, 'exclude_from_analytics' => false, 'exclude_reason' => null],
        };
        $n = $q->update($set);
        AccessLog::record('cohort_' . $v['action'], $v['mode'] === 'school' ? $v['school'] : $v['mode'], ['affected' => $n, 'reason' => $v['reason'] ?? null]);

        return back()->with('status', "$n akun diperbarui. Angka akan berubah setelah perhitungan ulang (php artisan la:aggregate, la:fit-models, la:score-risk) atau jadwal dini hari.");
    }
}
