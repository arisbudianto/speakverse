<?php

namespace App\Services\LearningAnalytics\Intervention;

use App\Services\LearningAnalytics\Content\ContentEffectiveness;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/** Loader DB untuk evaluasi tindak lanjut dan efektivitas konten (dihitung saat halaman dibuka; data kecil). */
class InterventionService
{
    private const WINDOW = 14;

    public function interventions(): array
    {
        $today = new DateTimeImmutable('today');
        $lim = $today->modify('-' . self::WINDOW . ' days');
        $follow = DB::table('la_interventions')->orderBy('followed_at')->get(['user_id', 'followed_at'])->groupBy('user_id')->map(fn ($g) => $g->first()->followed_at);
        $rows = [];
        foreach ($follow as $uid => $at) {
            $ref = new DateTimeImmutable(substr((string) $at, 0, 10));
            if ($ref <= $lim) {
                $rows[$uid] = ['user' => $uid, 'followed' => true, 'ref' => $ref];
            }
        }
        $ctl = DB::table('la_risk_history')->whereIn('level', ['sedang', 'tinggi'])->where('date', '<=', $lim->format('Y-m-d'))->orderBy('date')->get(['user_id', 'date']);
        foreach ($ctl as $c) {
            if (isset($rows[$c->user_id]) || $follow->has($c->user_id)) {
                continue;
            }
            $rows[$c->user_id] = ['user' => $c->user_id, 'followed' => false, 'ref' => new DateTimeImmutable($c->date)];
        }
        $out = [];
        foreach ($rows as $uid => $r) {
            $b = $this->activeDays($uid, $r['ref']->modify('-' . self::WINDOW . ' days'), $r['ref']->modify('-1 day'));
            $a = $this->activeDays($uid, $r['ref'], $r['ref']->modify('+' . (self::WINDOW - 1) . ' days'));
            $out[] = ['user' => $uid, 'followed' => $r['followed'], 'before' => $b, 'after' => $a];
        }

        return (new InterventionEvaluator())->evaluate($out) + ['window_days' => self::WINDOW];
    }

    private function activeDays(int $uid, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) DB::table('la_daily_student')->where('user_id', $uid)->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])->count();
    }

    public function content(array $gain): array
    {
        $ev = DB::table('learning_events')->whereNotNull('lesson_id')->whereIn('event_type', ['start', 'submit', 'abandon'])
            ->selectRaw("lesson_id, SUM(event_type='start') starts, SUM(event_type='submit') submits, SUM(event_type='abandon') abandons")
            ->groupBy('lesson_id')->get()->keyBy('lesson_id');
        $acc = DB::table('learning_events')->whereNotNull('lesson_id')->whereIn('event_type', ['select', 'revise'])->where('attempt_no', 1)->whereNotNull('is_correct')
            ->selectRaw('lesson_id, AVG(is_correct) acc')->groupBy('lesson_id')->pluck('acc', 'lesson_id');
        $lessons = [];
        foreach (DB::table('lessons')->get(['id', 'title', 'skill_type']) as $l) {
            $e = $ev[$l->id] ?? null;
            $lessons[] = ['lesson' => (int) $l->id, 'name' => $l->title . ' (' . $l->skill_type . ')', 'starts' => (int) ($e->starts ?? 0), 'submits' => (int) ($e->submits ?? 0),
                'abandons' => (int) ($e->abandons ?? 0), 'first_try_acc' => isset($acc[$l->id]) ? round((float) $acc[$l->id], 3) : null];
        }
        $done = DB::table('user_lesson_progress')->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '')->orWhere('status', 'completed');
        })->get(['user_id', 'lesson_id'])->groupBy('lesson_id')->map(fn ($g) => $g->pluck('user_id')->map(fn ($v) => (int) $v)->all())->all();

        return (new ContentEffectiveness())->rank($lessons, $done, $gain);
    }
}
