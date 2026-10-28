<?php

namespace App\Services;

use App\Models\AssessmentSubmission;
use App\Models\Unit;
use App\Models\UserLessonProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/**
 * Menentukan apakah seorang siswa berhak membuka suatu tahap (unit/pretest/
 * posttest) berdasarkan penyelesaian tahap sebelumnya.
 *
 * Aturan: setiap tahap memerlukan keempat keterampilan (listening, reading,
 * writing, speaking) selesai sebelum tahap berikutnya terbuka. Tahap pertama
 * (berdasarkan order_number) selalu terbuka.
 *
 * Logika ini sebelumnya hanya dihitung di MissionController untuk
 * menampilkan/menyembunyikan tautan pada peta misi (UI saja). Siswa yang
 * mengetik atau membuka alamat halaman secara langsung tetap bisa mengakses
 * tahap yang seharusnya terkunci karena rute dan controller tidak pernah
 * memvalidasi aturan ini. Service ini menjadi satu sumber kebenaran yang
 * dipakai baik oleh peta misi maupun oleh guard di controller.
 */
class ProgressionService
{
    /**
     * Keempat keterampilan yang harus selesai agar satu tahap dianggap tuntas.
     */
    public const SKILLS = [
        'listening',
        'reading',
        'writing',
        'speaking',
    ];

    /**
     * Seluruh tahap (unit/pretest/posttest) aktif, terurut sesuai alur belajar.
     */
    public function orderedUnits(): Collection
    {
        return Unit::where('status', 'active')
            ->orderBy('order_number')
            ->get();
    }

    /**
     * Daftar keterampilan yang sudah diselesaikan siswa pada satu tahap.
     */
    public function completedSkills(int $userId, Unit $unit): array
    {
        if ($unit->type === 'unit') {
            return UserLessonProgress::where('user_id', $userId)
                ->where('unit_id', $unit->id)
                ->where('status', 'completed')
                ->pluck('skill_type')
                ->unique()
                ->values()
                ->toArray();
        }

        return AssessmentSubmission::where('user_id', $userId)
            ->where('unit_id', $unit->id)
            ->where('type', $unit->type)
            ->where('status', 'completed')
            ->whereNotNull('final_score')
            ->pluck('skill')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Apakah siswa sudah menuntaskan keempat keterampilan pada satu tahap.
     */
    public function isUnitCompleted(int $userId, Unit $unit): bool
    {
        return count($this->completedSkills($userId, $unit)) >= count(self::SKILLS);
    }

    /**
     * Apakah satu tahap terbuka bagi siswa — tahap pertama selalu terbuka;
     * tahap berikutnya terbuka hanya jika tahap tepat sebelumnya sudah tuntas.
     */
    public function isUnitUnlocked(int $userId, Unit $unit): bool
    {
        $units = $this->orderedUnits();

        $index = $units->search(
            fn (Unit $candidate) => $candidate->id === $unit->id
        );

        if ($index === false || $index === 0) {
            return true;
        }

        $previousUnit = $units->values()->get($index - 1);

        if (! $previousUnit) {
            return true;
        }

        return $this->isUnitCompleted($userId, $previousUnit);
    }

    /**
     * Peta status penyelesaian dan status buka/kunci untuk seluruh tahap,
     * dipakai oleh peta misi (missions.index). Dipusatkan di sini agar
     * tampilan dan guard akses selalu konsisten.
     *
     * @return array{units: Collection, completedMap: array, unlockMap: array, completedTotal: int, totalStages: int, overallProgress: int}
     */
    public function missionMap(int $userId): array
    {
        $units = $this->orderedUnits();

        $completedMap = [];

        foreach ($units as $unit) {
            $completedSkills = $this->completedSkills($userId, $unit);

            $completedMap[$unit->id] = [
                'completed_skills' => $completedSkills,
                'completed_count' => count($completedSkills),
                'is_completed' => count($completedSkills) >= count(self::SKILLS),
            ];
        }

        $unlockMap = [];

        foreach ($units as $index => $unit) {
            if ($index === 0) {
                $unlockMap[$unit->id] = true;

                continue;
            }

            $previousUnit = $units[$index - 1];

            $unlockMap[$unit->id] = $completedMap[$previousUnit->id]['is_completed'] ?? false;
        }

        $completedTotal = collect($completedMap)
            ->where('is_completed', true)
            ->count();

        $totalStages = $units->count();

        $overallProgress = $totalStages > 0
            ? (int) round(($completedTotal / $totalStages) * 100)
            : 0;

        return [
            'units' => $units,
            'completedMap' => $completedMap,
            'unlockMap' => $unlockMap,
            'completedTotal' => $completedTotal,
            'totalStages' => $totalStages,
            'overallProgress' => $overallProgress,
        ];
    }

    /**
     * Jika tahap masih terkunci bagi siswa, kembalikan redirect ke peta misi
     * disertai pesan. Jika sudah terbuka, kembalikan null sehingga controller
     * dapat melanjutkan seperti biasa.
     *
     * Dipanggil di awal method controller yang menampilkan atau menerima
     * jawaban (show/submit) suatu tahap, agar tautan yang di-bookmark, dibagikan,
     * atau diketik langsung tidak bisa melewati urutan belajar.
     */
    public function lockedRedirect(int $userId, Unit $unit): ?RedirectResponse
    {
        if ($this->isUnitUnlocked($userId, $unit)) {
            return null;
        }

        $label = $unit->title ?: match ($unit->type) {
            'pretest' => 'Pre-Test',
            'posttest' => 'Post-Test',
            default => 'tahap ini',
        };

        return redirect()
            ->route('missions')
            ->with(
                'error',
                "Selesaikan keempat keterampilan (Listening, Reading, Writing, Speaking) pada tahap sebelumnya terlebih dahulu sebelum membuka \"{$label}\"."
            );
    }
}
