<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\SchoolLearningAnalytics;
use App\Services\LearningAnalytics\AccessLog;
use App\Services\LearningAnalytics\Intervention\InterventionService;
use App\Services\LearningAnalytics\Risk\RiskScorer;
use App\Services\LearningAnalytics\Risk\RiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Analitik Lanjutan Fase 4: Peringatan Dini, Intervensi & Konten. */
class LearningAnalyticsRiskController extends Controller
{
    public function risk(Request $request): View
    {
        $v = $request->validate(['school' => ['nullable', 'string', 'max:150'], 'level' => ['nullable', 'in:tinggi,sedang,rendah']]);
        $ready = Schema::hasTable('la_risk_scores') && DB::table('la_risk_scores')->exists();
        $rows = collect();
        $counts = [];
        $model = null;
        $schools = [];
        if ($ready) {
            $q = DB::table('la_risk_scores as r')->join('users as u', 'u.id', '=', 'r.user_id');
            $counts = (clone $q)->selectRaw('r.level, COUNT(*) c')->groupBy('r.level')->pluck('c', 'level')->all();
            $schools = DB::table('users')->whereIn('id', DB::table('la_risk_scores')->select('user_id'))->whereNotNull('school')->where('school', '!=', '')->distinct()->orderBy('school')->pluck('school')->all();
            if (! empty($v['school'])) {
                $q->where('u.school', $v['school']);
            }
            $q->where('r.level', ! empty($v['level']) ? '=' : '!=', $v['level'] ?? 'rendah');
            $rows = $q->orderByDesc('r.score')->limit(200)->get(['u.id as uid', 'u.name', 'u.school', 'u.grade', 'u.major', 'u.parallel', 'r.score', 'r.level', 'r.confidence', 'r.factors', 'r.model_prob', 'r.computed_at'])
                ->map(function ($r) {
                    $r->factors = json_decode($r->factors ?? '[]', true) ?: [];

                    return $r;
                });
            $run = DB::table('la_model_runs')->where('kind', 'risk_logit')->orderByDesc('id')->first();
            $model = $run ? (json_decode($run->metrics, true) ?: []) + ['computed_at' => $run->computed_at] : null;
            AccessLog::record('view_risk_list', $v['school'] ?? 'semua', ['level' => $v['level'] ?? null, 'rows' => $rows->count()]);
        }

        return view('admin.learning-analytics.risk', [
            'ready' => $ready, 'rows' => $rows, 'counts' => $counts, 'model' => $model, 'schools' => $schools, 'filters' => ['school' => $v['school'] ?? null, 'level' => $v['level'] ?? null],
            'weights' => RiskService::weights(), 'defaults' => config('learning_analytics.risk.weights'), 'labels' => RiskScorer::LABELS,
            'thresholds' => ['high' => config('learning_analytics.risk.level_high'), 'medium' => config('learning_analytics.risk.level_medium'), 'post' => config('learning_analytics.risk.posttest_threshold')],
        ]);
    }

    public function saveWeights(Request $request): RedirectResponse
    {
        $keys = array_keys(config('learning_analytics.risk.weights'));
        $rules = [];
        foreach ($keys as $k) {
            $rules['w.' . $k] = ['required', 'numeric', 'min:0', 'max:50'];
        }
        $data = $request->validate($rules)['w'];
        abort_if(array_sum($data) <= 0, 422, 'Minimal satu bobot harus lebih dari 0.');
        DB::table('la_settings')->updateOrInsert(['key' => 'risk_weights'], ['value' => json_encode(array_map('floatval', $data)), 'updated_by' => auth()->id(), 'updated_at' => now()]);
        AccessLog::record('update_risk_weights', null, $data);

        return redirect()->route('admin.la.risk')->with('status', 'Bobot disimpan. Berlaku pada perhitungan berikutnya (php artisan la:score-risk atau jadwal harian).');
    }

    public function interventions(SchoolLearningAnalytics $base, InterventionService $svc): View
    {
        $gain = [];
        foreach ($base->report(null, null)['students'] as $s) {
            if ($s['gain'] !== null) {
                $gain[$s['id']] = (float) $s['gain'];
            }
        }
        $ready = Schema::hasTable('la_interventions');
        $eval = $ready ? $svc->interventions() : null;
        $recent = $ready ? DB::table('la_interventions as i')->join('users as u', 'u.id', '=', 'i.user_id')->leftJoin('users as t', 't.id', '=', 'i.actor_id')
            ->orderByDesc('i.followed_at')->limit(30)->get(['u.name', 'u.school', 't.name as teacher', 'i.risk_level', 'i.action', 'i.note', 'i.followed_at']) : collect();

        return view('admin.learning-analytics.interventions', ['ready' => $ready, 'eval' => $eval, 'recent' => $recent, 'content' => $ready ? $svc->content($gain) : []]);
    }
}
