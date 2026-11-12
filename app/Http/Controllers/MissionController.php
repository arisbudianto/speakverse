<?php

namespace App\Http\Controllers;

use App\Models\SpeakingSubmission;
use App\Models\Unit;
use App\Services\ProgressionService;
use Illuminate\Support\Facades\Auth;

class MissionController extends Controller
{
    public function index(ProgressionService $progressionService)
    {
        $userId = Auth::id();

        $map = $progressionService->missionMap($userId);

        // Peta misi (missions.index) menampilkan lessons per unit; muat
        // relasinya secara terpisah agar missionMap() tetap bisa dipakai
        // ulang oleh guard di controller lain tanpa eager-load yang tidak
        // perlu di sana.
        $units = Unit::with('lessons')
            ->whereIn('id', $map['units']->pluck('id'))
            ->orderBy('order_number')
            ->get();

        // Rekaman yang sudah dikirim, tetapi nilainya masih menunggu penilaian.
        // Dibawa dari hotfix produksi 29 Okt 2026 (belum ada di git sebelum
        // sekarang); belum dipakai oleh missions/index.blade.php, jadi belum
        // terlihat di UI, tapi dipertahankan di sini bukan dibuang.
        $speakingLessons = $units->flatMap(fn ($unit) => $unit->lessons
            ->where('skill_type', 'speaking')->pluck('id'));
        $pendingLessonIds = SpeakingSubmission::query()
            ->where('user_id', $userId)
            ->whereNull('total_score')
            ->whereNotNull('transcript')
            ->whereHas('material', fn ($query) => $query->whereIn('lesson_id', $speakingLessons))
            ->with('material')
            ->get()
            ->pluck('material.lesson_id')
            ->filter()
            ->all();
        $pendingSpeakingUnitIds = $units
            ->filter(fn ($unit) => $unit->lessons
                ->where('skill_type', 'speaking')
                ->pluck('id')->intersect($pendingLessonIds)->isNotEmpty())
            ->pluck('id')->all();

        return view('missions.index', [
            'units' => $units,
            'skills' => $progressionService->requiredSkills($userId),
            'completedMap' => $map['completedMap'],
            'unlockMap' => $map['unlockMap'],
            'completedTotal' => $map['completedTotal'],
            'totalStages' => $map['totalStages'],
            'overallProgress' => $map['overallProgress'],
            'pendingSpeakingUnitIds' => $pendingSpeakingUnitIds,
        ]);
    }
}