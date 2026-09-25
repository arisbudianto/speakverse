<?php

namespace App\Services\Learning;

use App\Models\LearningEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Modul 4 — Adaptation Policy.
 *
 * Menentukan level bantuan (0–3) berikutnya untuk satu soal,
 * berdasarkan data Modul 1 (interaksi: response_ms, revisi) dan
 * Modul 2 (benar/salah). SENGAJA rule-based (tabel if-else), TIDAK
 * pakai ML, sesuai instruksi dokumen rencana.
 *
 * BELUM DIPANGGIL dari controller/endpoint mana pun saat ini — belum
 * ada UI "Check"/"I need a hint" (itu Modul 5). Service ini
 * disiapkan lebih dulu supaya saat Modul 5 dibangun, keputusan level
 * bantuan tinggal dipanggil, bukan didesain dari nol.
 */
class AdaptationPolicy
{
    /**
     * Fungsi murni (pure function): tidak menyentuh database sama
     * sekali, hanya mengambil keputusan dari sinyal yang diberikan.
     * Ini yang membuatnya gampang diuji dengan skenario buatan (lihat
     * perintah `php artisan learning:test-adaptation-policy`).
     *
     * @param array{
     *     wrong_count?: int,
     *     hints_requested?: int,
     *     response_ms?: int|null,
     *     median_response_ms?: float|null,
     *     revision_count?: int,
     *     fading_active?: bool,
     * } $signals
     * @param array{
     *     freeze_level?: int,
     *     min_level?: int,
     *     max_level?: int,
     * }|null $teacherOverride Kunci guru (Modul 6, belum dibangun —
     *     parameter ini sudah disiapkan supaya M6 tinggal
     *     mengisinya nanti tanpa mengubah signature method ini).
     *
     * @return array{level: int, reasons: string[], policy_version: string}
     */
    public function decideHintLevel(array $signals, ?array $teacherOverride = null): array
    {
        $wrongCount = (int) ($signals['wrong_count'] ?? 0);
        $hintsRequested = (int) ($signals['hints_requested'] ?? 0);
        $responseMs = $signals['response_ms'] ?? null;
        $medianResponseMs = $signals['median_response_ms'] ?? null;
        $revisionCount = (int) ($signals['revision_count'] ?? 0);
        $fadingActive = (bool) ($signals['fading_active'] ?? false);

        $level = 0;
        $reasons = [];

        /*
        |--------------------------------------------------------------------------
        | L0 -> L1: salah pertama, atau siswa minta hint duluan.
        |--------------------------------------------------------------------------
        */
        if ($wrongCount >= 1 || $hintsRequested >= 1) {
            $level = 1;
            $reasons[] = $wrongCount >= 1 ? 'wrong_first_attempt' : 'hint_requested';
        }

        /*
        |--------------------------------------------------------------------------
        | L1 -> L2: salah lagi, ATAU (respons lambat DAN sering revisi).
        |--------------------------------------------------------------------------
        */
        $slowResponse = $responseMs !== null
            && $medianResponseMs !== null
            && $medianResponseMs > 0
            && $responseMs > $medianResponseMs * config('learning.adaptation.slow_response_multiplier', 1.5);

        $highRevision = $revisionCount >= config('learning.adaptation.high_revision_threshold', 2);

        if ($level >= 1 && ($wrongCount >= 2 || ($slowResponse && $highRevision))) {
            $level = 2;
            $reasons[] = $wrongCount >= 2 ? 'wrong_again' : 'slow_and_uncertain';
        }

        /*
        |--------------------------------------------------------------------------
        | L2 -> L3: tetap salah, atau ini permintaan hint ketiga.
        |--------------------------------------------------------------------------
        */
        if ($level >= 2 && ($wrongCount >= 3 || $hintsRequested >= 3)) {
            $level = 3;
            $reasons[] = $wrongCount >= 3 ? 'still_wrong' : 'third_hint_request';
        }

        /*
        |--------------------------------------------------------------------------
        | Fading: 2 soal beruntun benar tanpa hint -> batasi max L1.
        |--------------------------------------------------------------------------
        */
        if ($fadingActive) {
            $fadingMax = config('learning.adaptation.fading_max_level', 1);

            if ($level > $fadingMax) {
                $level = $fadingMax;
                $reasons[] = 'fading_cap';
            }
        }

        $level = $this->applyTeacherOverride($level, $teacherOverride, $reasons);

        $maxLevel = config('learning.adaptation.max_level', 3);
        $level = max(0, min($level, $maxLevel));

        return [
            'level' => $level,
            'reasons' => $reasons,
            'policy_version' => config('learning.policy_version', 'v1'),
        ];
    }

    /**
     * Modul 6 (belum dibangun) akan mengisi $teacherOverride dari
     * tabel teacher_scaffolding_policies. Sampai saat itu, parameter
     * ini selalu null dan method ini tidak melakukan apa-apa — tapi
     * kontraknya sudah siap.
     */
    private function applyTeacherOverride(int $level, ?array $override, array &$reasons): int
    {
        if (! $override) {
            return $level;
        }

        if (isset($override['freeze_level'])) {
            $reasons[] = 'teacher_lock';

            return (int) $override['freeze_level'];
        }

        if (isset($override['max_level']) && $level > $override['max_level']) {
            $level = (int) $override['max_level'];
            $reasons[] = 'teacher_max_level';
        }

        if (isset($override['min_level']) && $level < $override['min_level']) {
            $level = (int) $override['min_level'];
            $reasons[] = 'teacher_min_level';
        }

        return $level;
    }

    /**
     * Versi yang MEMBACA data sungguhan dari learning_events (Modul
     * 1) untuk satu soal dalam satu sesi, lalu memanggil
     * decideHintLevel(). Ini pintu yang akan dipanggil endpoint
     * POST .../hint di Modul 5 nanti.
     *
     * Sama seperti service Modul 1/2 lainnya: kegagalan di sini
     * TIDAK BOLEH menggagalkan apa pun di sisi siswa — fallback ke
     * level 0 (tidak dibantu) kalau terjadi error, bukan exception.
     */
    public function resolveHintLevel(
        string $sessionId,
        int $questionId,
        ?int $lessonId,
        string $skill = 'reading',
        ?array $teacherOverride = null
    ): array {
        try {
            $attempts = LearningEvent::query()
                ->where('quiz_session_id', $sessionId)
                ->where('question_id', $questionId)
                ->whereIn('event_type', ['select', 'revise'])
                ->orderBy('id')
                ->get();

            $wrongCount = $attempts->where('is_correct', false)->count();
            $revisionCount = $attempts->where('event_type', 'revise')->count();
            $latestResponseMs = optional($attempts->last())->response_ms;

            $hintsRequested = LearningEvent::query()
                ->where('quiz_session_id', $sessionId)
                ->where('question_id', $questionId)
                ->where('event_type', 'hint_request')
                ->count();

            $medianResponseMs = app(InteractionLogger::class)
                ->medianResponseMsForQuestion($lessonId, $skill, $questionId);

            return $this->decideHintLevel([
                'wrong_count' => $wrongCount,
                'hints_requested' => $hintsRequested,
                'response_ms' => $latestResponseMs,
                'median_response_ms' => $medianResponseMs,
                'revision_count' => $revisionCount,
                'fading_active' => $this->isFadingActive($sessionId, $questionId),
            ], $teacherOverride);
        } catch (Throwable $exception) {
            Log::warning('AdaptationPolicy: resolveHintLevel() failed, falling back to level 0.', [
                'quiz_session_id' => $sessionId,
                'question_id' => $questionId,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'level' => 0,
                'reasons' => ['error_fallback'],
                'policy_version' => config('learning.policy_version', 'v1'),
            ];
        }
    }

    /**
     * Cek apakah N soal TERAKHIR yang dikerjakan (selain soal saat
     * ini) dalam sesi ini semuanya dijawab benar TANPA hint sama
     * sekali — kalau ya, fading aktif untuk soal saat ini.
     *
     * N = config('learning.adaptation.fading_streak').
     */
    private function isFadingActive(string $sessionId, int $currentQuestionId): bool
    {
        $streakNeeded = config('learning.adaptation.fading_streak', 2);

        $recentQuestions = LearningEvent::query()
            ->where('quiz_session_id', $sessionId)
            ->where('question_id', '!=', $currentQuestionId)
            ->whereIn('event_type', ['select', 'revise'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('question_id')
            // Ambil attempt TERAKHIR per soal (kalau ada revisi,
            // yang menentukan benar/salah akhir adalah pilihan
            // terakhir).
            ->map(fn ($group) => $group->sortByDesc('id')->first())
            ->sortByDesc(fn ($event) => $event->id)
            ->values()
            ->take($streakNeeded);

        if ($recentQuestions->count() < $streakNeeded) {
            return false;
        }

        foreach ($recentQuestions as $event) {
            if ($event->is_correct !== true) {
                return false;
            }

            $hadHint = LearningEvent::query()
                ->where('quiz_session_id', $sessionId)
                ->where('question_id', $event->question_id)
                ->where('event_type', 'hint_request')
                ->exists();

            if ($hadHint) {
                return false;
            }
        }

        return true;
    }
}
