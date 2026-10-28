<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProgressionService;
use Illuminate\Console\Command;

/**
 * Laporkan tingkat penyelesaian setiap tahap (Pre-Test, Unit 1-4, Post-Test)
 * di antara seluruh siswa terdaftar, serta berapa siswa yang sudah
 * mengerjakan Post-Test tanpa menuntaskan keempat keterampilan pada tahap
 * sebelumnya — bukti lapangan dari bug akses langsung yang sudah diperbaiki
 * di ProgressionService (lihat commit yang menambahkan guard lockedRedirect).
 *
 * Perintah ini TIDAK mengubah data apa pun, murni pelaporan. Cocok
 * dijalankan sebelum dan sesudah fix dipasang di server produksi untuk
 * membandingkan dampaknya.
 */
class ReportModuleCompletionCommand extends Command
{
    protected $signature = 'progression:report
        {--role=student : Peran pengguna yang dihitung (default: student)}';

    protected $description = 'Laporkan tingkat penyelesaian tiap tahap (Pre-Test/Unit 1-4/Post-Test) dan jumlah siswa yang mengerjakan Post-Test tanpa menuntaskan tahap sebelumnya.';

    public function handle(ProgressionService $progressionService): int
    {
        $role = (string) $this->option('role');

        $students = User::query()
            ->when(
                $role !== 'all',
                fn ($query) => $query->where('role', $role)
            )
            ->get(['id', 'name']);

        $totalStudents = $students->count();

        if ($totalStudents === 0) {
            $this->warn("Tidak ada pengguna dengan role='{$role}'.");

            return self::SUCCESS;
        }

        $units = $progressionService->orderedUnits();

        if ($units->isEmpty()) {
            $this->warn('Tidak ada tahap (unit) aktif untuk dilaporkan.');

            return self::SUCCESS;
        }

        $this->info("=== Tingkat Penyelesaian Tahap — {$totalStudents} siswa (role={$role}) ===");
        $this->newLine();

        $rows = [];

        foreach ($units as $unit) {
            $completedCount = 0;

            foreach ($students as $student) {
                if ($progressionService->isUnitCompleted($student->id, $unit)) {
                    $completedCount++;
                }
            }

            $percentage = round(($completedCount / $totalStudents) * 100, 1);

            $rows[] = [
                $unit->title ?: ucfirst($unit->type),
                $unit->type,
                $completedCount,
                $totalStudents,
                "{$percentage}%",
            ];
        }

        $this->table(
            ['Tahap', 'Jenis', 'Selesai (4/4 skill)', 'Total Siswa', '% Selesai'],
            $rows
        );

        $this->newLine();

        $this->reportPosttestWithoutPriorCompletion($progressionService, $students, $units);

        return self::SUCCESS;
    }

    /**
     * Hitung siswa yang sudah mengerjakan (minimal 1 skill) Post-Test,
     * padahal tahap tepat sebelum Post-Test (Unit 4) belum tuntas 4/4
     * keterampilan — bukti akses langsung tanpa menuntaskan urutan belajar.
     */
    private function reportPosttestWithoutPriorCompletion(
        ProgressionService $progressionService,
        $students,
        $units
    ): void {
        $posttest = $units->firstWhere('type', 'posttest');

        if (! $posttest) {
            $this->comment('Tidak ditemukan tahap bertipe posttest — lewati pengecekan lompat tahap.');

            return;
        }

        $index = $units->search(fn ($u) => $u->id === $posttest->id);
        $previousUnit = $index !== false && $index > 0 ? $units[$index - 1] : null;

        $skippers = [];

        foreach ($students as $student) {
            $startedPosttest = count($progressionService->completedSkills($student->id, $posttest)) > 0;

            if (! $startedPosttest) {
                continue;
            }

            $priorDone = $previousUnit
                ? $progressionService->isUnitCompleted($student->id, $previousUnit)
                : true;

            if (! $priorDone) {
                $skippers[] = $student->name;
            }
        }

        $this->info('=== Siswa yang mengerjakan Post-Test tanpa menuntaskan tahap sebelumnya ===');

        if (empty($skippers)) {
            $this->line('Tidak ada (sesuai data saat ini).');

            return;
        }

        $this->warn(count($skippers).' siswa:');

        foreach ($skippers as $name) {
            $this->line('- '.$name);
        }
    }
}
