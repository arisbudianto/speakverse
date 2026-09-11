<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSubmission;
use App\Models\Lesson;
use App\Models\ListeningMaterial;
use App\Models\UserLessonProgress;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentListeningController extends Controller
{
    /**
     * Menampilkan materi listening.
     */
    public function listening(
        Lesson $lesson
    ): View {
        $material =
            ListeningMaterial::with(
                'questions'
            )
                ->where(
                    'lesson_id',
                    $lesson->id
                )
                ->firstOrFail();

        return view(
            'missions.listening.index',
            [
                'lesson' =>
                    $lesson,

                'material' =>
                    $material,
            ]
        );
    }

    /**
     * Menampilkan halaman quiz listening.
     */
    public function quiz(
        Lesson $lesson
    ): View {
        $material =
            ListeningMaterial::with([
                'questions' =>
                    function ($query) {
                        $query
                            ->orderBy('id');
                    },
            ])
                ->where(
                    'lesson_id',
                    $lesson->id
                )
                ->firstOrFail();

        return view(
            'missions.listening.quiz',
            [
                'lesson' =>
                    $lesson,

                'material' =>
                    $material,

                'questions' =>
                    $material->questions,
            ]
        );
    }

    /**
     * Menyimpan jawaban dan menyelesaikan quiz listening.
     */
    public function complete(
        Request $request,
        Lesson $lesson,
        GamificationService $gamificationService
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Validasi jawaban
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'answers' => [
                    'required',
                    'array',
                ],

                'answers.*' => [
                    'required',
                    'string',
                    'in:A,B,C,D,E',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Ambil material dan questions
        |--------------------------------------------------------------------------
        */

        $material =
            ListeningMaterial::with([
                'questions' =>
                    function ($query) {
                        $query
                            ->orderBy('id');
                    },
            ])
                ->where(
                    'lesson_id',
                    $lesson->id
                )
                ->firstOrFail();

        $answers =
            $validated['answers'];

        /*
        |--------------------------------------------------------------------------
        | Pastikan seluruh soal dijawab
        |--------------------------------------------------------------------------
        */

        $missingQuestions =
            $material
                ->questions
                ->filter(
                    function (
                        $question
                    ) use (
                        $answers
                    ) {
                        return
                            !array_key_exists(
                                $question->id,
                                $answers
                            );
                    }
                );

        if (
            $missingQuestions
                ->isNotEmpty()
        ) {
            throw ValidationException
                ::withMessages([
                    'answers' =>
                        'Semua soal wajib dijawab sebelum quiz dikirim.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan assessment
        |--------------------------------------------------------------------------
        */

        $submission =
            DB::transaction(
                function () use (
                    $lesson,
                    $material,
                    $answers
                ) {
                    $earnedScore = 0;
                    $maximumScore = 0;

                    /*
                    |--------------------------------------------------------------------------
                    | Assessment Submission
                    |--------------------------------------------------------------------------
                    */

                    $submission =
                        AssessmentSubmission
                            ::create([
                                'user_id' =>
                                    Auth::id(),

                                'unit_id' =>
                                    $lesson
                                        ->unit_id,

                                'lesson_id' =>
                                    $lesson->id,

                                'type' =>
                                    'unit',

                                'skill' =>
                                    'listening',

                                'final_score' =>
                                    0,

                                'status' =>
                                    'completed',

                                'feedback' =>
                                    'Listening quiz completed.',

                                'submitted_at' =>
                                    now(),
                            ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Assessment Answers
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $material->questions
                        as $question
                    ) {
                        $selectedAnswer =
                            strtoupper(
                                trim(
                                    (string)
                                    $answers[
                                        $question->id
                                    ]
                                )
                            );

                        $correctAnswer =
                            strtoupper(
                                trim(
                                    (string)
                                    $question
                                        ->correct_answer
                                )
                            );

                        $isCorrect =
                            $selectedAnswer
                            ===
                            $correctAnswer;

                        /*
                         * Nilai maksimum per soal.
                         */
                        $questionMaximumScore =
                            max(
                                0,
                                (int)
                                $question->score
                            );

                        /*
                         * Jika benar mendapatkan nilai penuh.
                         */
                        $questionEarnedScore =
                            $isCorrect
                                ? $questionMaximumScore
                                : 0;

                        $earnedScore +=
                            $questionEarnedScore;

                        $maximumScore +=
                            $questionMaximumScore;

                        AssessmentAnswer
                            ::create([
                                'assessment_submission_id' =>
                                    $submission
                                        ->id,

                                'question_type' =>
                                    'listening',

                                'question_id' =>
                                    $question
                                        ->id,

                                'selected_option' =>
                                    $selectedAnswer,

                                'is_correct' =>
                                    $isCorrect,

                                'score' =>
                                    $questionEarnedScore,

                                'max_score' =>
                                    $questionMaximumScore,

                                'feedback' =>
                                    $isCorrect
                                        ? 'Correct answer.'
                                        : 'Incorrect answer.',
                            ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Final Score
                    |--------------------------------------------------------------------------
                    |
                    | Normalisasi skor menjadi 0–100.
                    |
                    */

                    $finalScore =
                        $maximumScore > 0
                            ? (int) round(
                                (
                                    $earnedScore
                                    /
                                    $maximumScore
                                )
                                *
                                100
                            )
                            : 0;

                    /*
                    |--------------------------------------------------------------------------
                    | Update assessment score
                    |--------------------------------------------------------------------------
                    */

                    $submission
                        ->update([
                            'final_score' =>
                                $finalScore,
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | User Lesson Progress
                    |--------------------------------------------------------------------------
                    */

                    UserLessonProgress
                        ::updateOrCreate(
                            [
                                'user_id' =>
                                    Auth::id(),

                                'lesson_id' =>
                                    $lesson
                                        ->id,

                                'skill_type' =>
                                    'listening',
                            ],
                            [
                                'unit_id' =>
                                    $lesson
                                        ->unit_id,

                                'status' =>
                                    'completed',

                                'score' =>
                                    $finalScore,

                                'completed_at' =>
                                    now(),
                            ]
                        );

                    return $submission
                        ->fresh();
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Gamification Reward
        |--------------------------------------------------------------------------
        |
        | Reward diberikan setelah data akademik berhasil tersimpan.
        |
        | Listening:
        |
        | +30 XP
        | +10 SpeakCoins
        |
        | Jika Listening merupakan skill terakhir dalam Unit,
        | GamificationService juga otomatis memberikan bonus Unit.
        |
        */

        $gamification =
            $gamificationService
                ->rewardLessonCompletion(
                    Auth::user(),
                    $lesson,
                    'listening',
                    (int)
                    $submission
                        ->final_score
                );

        /*
        |--------------------------------------------------------------------------
        | JSON Response
        |--------------------------------------------------------------------------
        |
        | "gamification" akan dibaca oleh gamification.js
        | untuk menampilkan Instant Reward Popup.
        |
        */

        return response()
            ->json([
                'success' =>
                    true,

                'score' =>
                    (int)
                    $submission
                        ->final_score,

                'submission_id' =>
                    $submission
                        ->id,

                'message' =>
                    'Listening result saved.',

                'gamification' =>
                    $gamification,
            ]);
    }
}