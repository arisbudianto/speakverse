<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LearningAnalytics\AccessLog;
use App\Services\LearningAnalytics\Research\ResearchExportService;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Analitik Lanjutan Fase 5: Ekspor Riset (pseudonim, persetujuan, kelompok) dan Metodologi. */
class LearningAnalyticsResearchController extends Controller
{
    public function index(): View
    {
        $ready = Schema::hasTable('la_consents');
        $students = DB::table('users')->where(function ($q) {
            $q->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
        });
        $total = (clone $students)->count();
        $consent = $ready ? DB::table('la_consents')->selectRaw('SUM(accepted=1) yes, SUM(accepted=0) no')->first() : null;
        $groups = Schema::hasColumn('users', 'research_group') ? (clone $students)->selectRaw("COALESCE(NULLIF(research_group, ''), '(belum diatur)') g, COUNT(*) c")->groupBy('g')->pluck('c', 'g')->all() : [];
        $log = $ready ? DB::table('la_access_log as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')->orderByDesc('l.id')->limit(25)->get(['u.name', 'l.action', 'l.target', 'l.meta', 'l.created_at']) : collect();

        return view('admin.learning-analytics.research', [
            'ready' => $ready, 'total' => $total, 'yes' => (int) ($consent->yes ?? 0), 'no' => (int) ($consent->no ?? 0),
            'groups' => $groups, 'log' => $log, 'schools' => array_keys(config('schools', [])),
        ]);
    }

    public function setGroup(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'school' => ['required', Rule::in(array_keys(config('schools', [])))],
            'grade' => ['nullable', Rule::in(['X', 'XI', 'XII'])],
            'parallel' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'group' => ['nullable', Rule::in(['kontrol', 'perlakuan'])],
        ]);
        $q = DB::table('users')->where('school', $v['school'])->where(function ($q) {
            $q->where('role', 'student')->orWhereNull('role')->orWhere('role', 'user');
        });
        foreach (['grade', 'parallel'] as $f) {
            if (! empty($v[$f])) {
                $q->where($f, $v[$f]);
            }
        }
        $n = $q->update(['research_group' => $v['group'] ?: null]);
        AccessLog::record('set_research_group', $v['school'], $v + ['affected' => $n]);

        return back()->with('status', "Kelompok '" . ($v['group'] ?: 'kosong') . "' diterapkan pada $n siswa.");
    }

    public function export(Request $request, ResearchExportService $svc)
    {
        $v = $request->validate([
            'identified' => ['nullable', 'boolean'], 'include_unconsented' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        $identified = (bool) ($v['identified'] ?? false);
        $include = (bool) ($v['include_unconsented'] ?? false);
        if (($identified || $include) && mb_strlen(trim($v['reason'] ?? '')) < 10) {
            return back()->withErrors(['reason' => 'Alasan wajib diisi (≥10 karakter) untuk ekspor teridentifikasi atau yang menyertakan siswa tanpa persetujuan.'])->withInput();
        }
        $r = $svc->build($identified, $include);
        AccessLog::record($identified ? 'export_identified' : 'export_pseudonymized', null, ['rows' => $r['n'], 'excluded_no_consent' => $r['excluded'], 'include_unconsented' => $include, 'reason' => $v['reason'] ?? null]);

        return SimpleXlsxWriter::download(($identified ? 'riset-teridentifikasi-' : 'riset-pseudonim-') . now()->format('Ymd') . '.xlsx', $r['headers'], $r['rows']);
    }

    public function methodology(): View
    {
        return view('admin.learning-analytics.method');
    }
}
