<?php

namespace App\Console\Commands;

use App\Services\LearningAnalytics\Risk\RiskService;
use Illuminate\Console\Command;

class LaScoreRiskCommand extends Command
{
    protected $signature = 'la:score-risk';

    protected $description = 'Learning Analytics: skor risiko (aturan transparan + model logit pendamping) dan riwayat harian.';

    public function handle(RiskService $svc): int
    {
        $r = $svc->run();
        $this->info(sprintf('Selesai: %d siswa (tinggi %d, sedang %d, rendah %d). Model: %s.', $r['students'], $r['tinggi'], $r['sedang'], $r['rendah'],
            $r['model_ready'] ? 'tampil, AUC-CV ' . round($r['auc'], 2) : 'tidak ditampilkan' . ($r['auc'] !== null ? ' (AUC-CV ' . round($r['auc'], 2) . ')' : ' (data belum cukup)')));

        return self::SUCCESS;
    }
}
