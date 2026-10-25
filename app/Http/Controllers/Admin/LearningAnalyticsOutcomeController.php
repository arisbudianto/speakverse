<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\SchoolLearningAnalytics;
use App\Services\LearningAnalytics\Outcome\OutcomeAnalysis;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Analitik Lanjutan Fase 2: tab Hasil (inferensi) dan Kualitas Soal (CTT). */
class LearningAnalyticsOutcomeController extends Controller
{
    public function outcomes(Request $request, SchoolLearningAnalytics $base, OutcomeAnalysis $analysis): View
    {
        $v = $request->validate(['school' => ['nullable', 'string', 'max:150'], 'grade' => ['nullable', 'in:X,XI,XII']]);
        $school = ($v['school'] ?? null) ?: null;
        $grade = ($v['grade'] ?? null) ?: null;
        $report = $base->report($school, $grade);

        return view('admin.learning-analytics.outcomes', [
            'a' => $analysis->analyze($report['students']),
            'schoolOptions' => array_column($report['schools'], 'school'),
            'filters' => ['school' => $school, 'grade' => $grade],
        ]);
    }

    public function items(Request $request): View
    {
        $ready = Schema::hasTable('la_item_stats');
        $forms = $items = collect();
        $computed = null;
        $only = $request->query('flagged') === '1';
        $form = $request->query('form');
        if ($ready) {
            $forms = DB::table('la_form_stats')->orderBy('form')->get();
            $q = DB::table('la_item_stats')->orderBy('form')->orderBy('item');
            if ($form) {
                $q->where('form', $form);
            }
            if ($only) {
                $q->whereRaw("JSON_LENGTH(flags) > 0");
            }
            $items = $q->limit(500)->get()->map(function ($i) {
                $i->flags = json_decode($i->flags ?? '[]', true) ?: [];
                $i->options = json_decode($i->options ?? '{}', true) ?: [];

                return $i;
            });
            $computed = DB::table('la_form_stats')->max('computed_at');
        }

        return view('admin.learning-analytics.items', compact('ready', 'forms', 'items', 'computed', 'only', 'form'));
    }

    public function itemsExport()
    {
        abort_unless(Schema::hasTable('la_item_stats'), 404);
        $rows = DB::table('la_item_stats')->orderBy('form')->orderBy('item')->get()->map(fn ($i) => [
            $i->form, $i->item, $i->n, (float) $i->p, $i->r_pb === null ? null : (float) $i->r_pb, $i->d_index === null ? null : (float) $i->d_index,
            $i->key_option, implode('; ', json_decode($i->flags ?? '[]', true) ?: []), $i->indicative ? 'ya' : 'tidak'])->all();

        return SimpleXlsxWriter::download('analisis-butir-' . now()->format('Ymd') . '.xlsx',
            ['Form', 'Butir', 'n', 'Kesukaran (p)', 'Daya beda (r_pb sisa)', 'D 27%', 'Kunci', 'Penanda', 'n kecil'], $rows);
    }
}
