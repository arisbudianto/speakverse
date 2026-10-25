<?php

namespace App\Http\Controllers;

use App\Services\LearningAnalytics\Guidance\StudentGuidance;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Siswa: "Kemajuan Saya" — hanya data milik sendiri, bahasa suportif, tanpa peringkat. */
class StudentProgressController extends Controller
{
    public const CONSENT_VERSION = 'v1';

    public function show(Request $request): View
    {
        $uid = $request->user()->id;
        $today = new DateTimeImmutable('today');
        $has = Schema::hasTable('la_mastery') && Schema::hasTable('la_daily_student');
        $states = $has ? DB::table('la_mastery')->where('user_id', $uid)->orderBy('competency')->get(['competency', 'p_mastery', 'n_obs']) : collect();
        $active = $has ? DB::table('la_daily_student')->where('user_id', $uid)->where('date', '>=', $today->modify('-27 days')->format('Y-m-d'))->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->flip()->map(fn () => true)->all() : [];
        $weekly = StudentGuidance::weekly($active, $today);
        $next = StudentGuidance::nextStep($states->map(fn ($s) => ['competency' => $s->competency, 'p' => (float) $s->p_mastery, 'n_obs' => (int) $s->n_obs])->all());
        $consent = Schema::hasTable('la_consents') ? DB::table('la_consents')->where('user_id', $uid)->first() : null;
        $cal = [];
        for ($i = 27; $i >= 0; $i--) {
            $d = $today->modify("-$i days");
            $cal[] = ['date' => $d->format('Y-m-d'), 'day' => (int) $d->format('j'), 'active' => isset($active[$d->format('Y-m-d')])];
        }

        return view('progress.my', compact('states', 'weekly', 'next', 'cal', 'consent', 'has'));
    }

    public function consent(Request $request): RedirectResponse
    {
        $v = $request->validate(['accepted' => ['required', 'boolean']]);
        abort_unless(Schema::hasTable('la_consents'), 404);
        DB::table('la_consents')->updateOrInsert(['user_id' => $request->user()->id], ['version' => self::CONSENT_VERSION, 'accepted' => (bool) $v['accepted'], 'answered_at' => now()]);

        return back()->with('status', $v['accepted'] ? 'Terima kasih, persetujuan Anda tersimpan.' : 'Pilihan Anda tersimpan. Data Anda tidak disertakan dalam ekspor riset.');
    }
}
