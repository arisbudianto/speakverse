<?php

namespace App\Services\LearningAnalytics\Content;

use App\Services\LearningAnalytics\Stats\Descriptive as D;
use App\Services\LearningAnalytics\Stats\Inference as I;

/**
 * Efektivitas konten per pelajaran (kelas murni).
 *
 *  abandon_rate  abandon / (submit + abandon)
 *  uplift        rerata kenaikan skor (post − pre) siswa yang MENYELESAIKAN pelajaran dikurangi yang belum (Welch, CI 95%, Hedges' g)
 *                — HANYA korelasi: siswa yang rajin juga lebih mungkin menyelesaikan pelajaran dan naik skornya.
 *  penanda       'mengangkat skor' (p<0,05, g≥0,3, n≥8 tiap kelompok); 'sering ditinggalkan' (abandon_rate≥0,30 dengan ≥10 percobaan)
 *
 * @param array<int,array{lesson:int,name:string,starts:int,submits:int,abandons:int,first_try_acc:?float}> $lessons
 * @param array<int,int[]> $completedBy  lesson => daftar user yang menyelesaikan
 * @param array<int,float> $gain         user => selisih post-pre (hanya yang punya pasangan)
 */
final class ContentEffectiveness
{
    public function rank(array $lessons, array $completedBy, array $gain): array
    {
        $out = [];
        foreach ($lessons as $l) {
            $done = array_flip($completedBy[$l['lesson']] ?? []);
            $a = $b = [];
            foreach ($gain as $uid => $g) {
                if (isset($done[$uid])) {
                    $a[] = $g;
                } else {
                    $b[] = $g;
                }
            }
            $att = $l['submits'] + $l['abandons'];
            $abandon = $att > 0 ? $l['abandons'] / $att : null;
            $w = (count($a) >= 8 && count($b) >= 8) ? I::welch($a, $b) : null;
            $g = $w ? I::hedgesGIndependent($a, $b) : null;
            $flags = [];
            if ($w && $g !== null && $w['p'] < 0.05 && $g >= 0.3) {
                $flags[] = 'mengangkat skor';
            }
            if ($abandon !== null && $att >= 10 && $abandon >= 0.30) {
                $flags[] = 'sering ditinggalkan';
            }
            $out[] = $l + [
                'abandon_rate' => $abandon === null ? null : round($abandon, 3),
                'n_done' => count($a), 'n_not' => count($b),
                'uplift' => $w ? round($w['diff'], 1) : null, 'uplift_ci' => $w ? [round($w['ci'][0], 1), round($w['ci'][1], 1)] : null,
                'uplift_p' => $w ? $w['p'] : null, 'g' => $g === null ? null : round($g, 2), 'flags' => $flags,
            ];
        }
        usort($out, fn ($x, $y) => [$y['g'] ?? -9] <=> [$x['g'] ?? -9]);

        return $out;
    }
}
