<?php

namespace App\Services\LearningAnalytics\Outcome;

use App\Services\LearningAnalytics\Stats\Descriptive as D;
use App\Services\LearningAnalytics\Stats\Inference as I;

/**
 * Analisis hasil belajar tingkat lanjut (kelas murni).
 *
 * Masukan: daftar siswa (keluaran SchoolLearningMetrics::compute()['students']) dengan
 * pre, post, school, major, grade, parallel. Pasangan = siswa yang punya pre DAN post.
 *
 * Keluaran: ringkasan berpasangan (t/Wilcoxon sesuai aturan I::chooseTest, CI 95%, Hedges' g, Hake),
 * per sekolah, histogram, ringkas-lima-angka per sekolah, ICC (sekolah dan kelas), uji kesenjangan
 * (Kruskal–Wallis antar sekolah dan antar jurusan). Tidak ada koreksi perbandingan ganda; p hanya indikatif.
 */
final class OutcomeAnalysis
{
    public function __construct(private int $minPairs = 5)
    {
    }

    public function analyze(array $students): array
    {
        $pre = $post = [];
        $bySchoolPair = [];
        $postBySchool = [];
        $postByClass = [];
        $gainByClass = [];
        $postByMajor = [];
        $gainBySchool = [];
        $hakeStudent = [];
        foreach ($students as $s) {
            $sch = $s['school'] ?: '(Belum diisi)';
            $cls = $sch . '|' . $s['major'] . '|' . $s['grade'] . '|' . $s['parallel'];
            if ($s['post'] !== null) {
                $postBySchool[$sch][] = (float) $s['post'];
                $postByClass[$cls][] = (float) $s['post'];
                $postByMajor[$s['major'] ?: '(Belum diisi)'][] = (float) $s['post'];
            }
            if ($s['pre'] !== null && $s['post'] !== null) {
                $pre[] = (float) $s['pre'];
                $post[] = (float) $s['post'];
                $bySchoolPair[$sch][0][] = (float) $s['pre'];
                $bySchoolPair[$sch][1][] = (float) $s['post'];
                $gainBySchool[$sch][] = $s['post'] - $s['pre'];
                $gainByClass[$cls][] = (float) ($s['post'] - $s['pre']);
                if (($h = I::hakeStudent((float) $s['pre'], (float) $s['post'])) !== null) {
                    $hakeStudent[] = $h;
                }
            }
        }
        $n = count($pre);
        $test = null;
        $effect = null;
        $hake = null;
        $paired = null;
        if ($n >= 2) {
            $kind = I::chooseTest($pre, $post);
            $paired = I::pairedT($pre, $post);
            $wil = I::wilcoxon($pre, $post);
            $test = [
                'chosen' => $kind,
                'reason' => $kind === 't'
                    ? 'n ≥ 20, selisih tidak miring (|skew|<1) dan kurtosis wajar (|ekses|<2) → t berpasangan'
                    : 'n < 20 atau selisih miring/berekor tebal → Wilcoxon signed-rank',
                't' => $paired, 'wilcoxon' => $wil,
                'p' => $kind === 't' ? ($paired['p'] ?? null) : ($wil['p'] ?? null),
            ];
            $effect = I::effectSize($pre, $post);
            $hake = I::hakeClass($pre, $post);
        }

        $schools = [];
        foreach ($bySchoolPair as $name => [$a, $b]) {
            $np = count($a);
            $h = I::hakeClass($a, $b);
            $e = $np >= $this->minPairs ? I::effectSize($a, $b) : null;
            $w = $np >= $this->minPairs ? I::wilcoxon($a, $b) : null;
            $schools[] = [
                'school' => $name, 'pairs' => $np, 'pre' => round(D::mean($a), 1), 'post' => round(D::mean($b), 1),
                'diff' => round(D::mean($b) - D::mean($a), 1), 'hake' => $h ? round($h['g'], 2) : null, 'hake_cat' => $h['category'] ?? null,
                'g' => $e ? round($e['g'], 2) : null, 'g_label' => $e['label'] ?? null, 'p' => $w ? $w['p'] : null,
                'indicative' => $np < $this->minPairs,
            ];
        }
        usort($schools, fn ($x, $y) => strcmp($x['school'], $y['school']));

        $box = [];
        foreach ($postBySchool as $name => $v) {
            $box[] = ['school' => $name, 'n' => count($v), 'min' => min($v), 'q1' => D::quantile($v, .25), 'median' => D::median($v),
                'q3' => D::quantile($v, .75), 'max' => max($v), 'mean' => D::mean($v), 'sd' => D::sd($v)];
        }
        usort($box, fn ($x, $y) => strcmp($x['school'], $y['school']));

        $filterMin = fn (array $g, int $m) => array_filter($g, fn ($v) => count($v) >= $m);
        $equity = [
            'school' => I::kruskalWallis($filterMin($postBySchool, 3)),
            'major' => I::kruskalWallis($filterMin($postByMajor, 3)),
        ];
        foreach ($equity as $k => $e) {
            if ($e) {
                $equity[$k]['significant'] = $e['p'] < 0.05;
                $equity[$k]['size'] = $e['epsilon2'] < 0.01 ? 'diabaikan' : ($e['epsilon2'] < 0.08 ? 'kecil' : ($e['epsilon2'] < 0.26 ? 'sedang' : 'besar'));
            }
        }
        $icc = [
            'post_school' => self::iccRow(I::icc1($filterMin($postBySchool, 1))),
            'post_class' => self::iccRow(I::icc1($filterMin($postByClass, 1))),
            'gain_school' => self::iccRow(I::icc1($filterMin($gainBySchool, 1))),
            'gain_class' => self::iccRow(I::icc1($filterMin($gainByClass, 1))),
        ];

        return [
            'pairs' => $n,
            'mean_pre' => $n ? round(D::mean($pre), 1) : null,
            'mean_post' => $n ? round(D::mean($post), 1) : null,
            'test' => $test, 'effect' => $effect, 'hake' => $hake,
            'hake_student_mean' => $hakeStudent ? round(D::mean($hakeStudent), 2) : null,
            'hake_student_n' => count($hakeStudent),
            'schools' => $schools, 'box' => $box,
            'hist_pre' => self::hist(array_filter(array_column($students, 'pre'), fn ($v) => $v !== null)),
            'hist_post' => self::hist(array_filter(array_column($students, 'post'), fn ($v) => $v !== null)),
            'equity' => $equity, 'icc' => $icc,
        ];
    }

    private static function iccRow(?array $i): ?array
    {
        if (! $i) {
            return null;
        }
        $v = max(0.0, $i['icc']);

        return $i + ['label' => $v < 0.05 ? 'kecil' : ($v < 0.15 ? 'sedang' : 'besar')];
    }

    /** @return int[] 10 kotak: 0–9,10–19,…,90–100 */
    public static function hist(array $v): array
    {
        $h = array_fill(0, 10, 0);
        foreach ($v as $x) {
            $h[(int) min(9, max(0, floor($x / 10)))]++;
        }

        return $h;
    }
}
