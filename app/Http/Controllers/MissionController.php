<?php

namespace App\Http\Controllers;

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

        return view('missions.index', [
            'units' => $units,
            'skills' => ProgressionService::SKILLS,
            'completedMap' => $map['completedMap'],
            'unlockMap' => $map['unlockMap'],
            'completedTotal' => $map['completedTotal'],
            'totalStages' => $map['totalStages'],
            'overallProgress' => $map['overallProgress'],
        ]);
    }
}