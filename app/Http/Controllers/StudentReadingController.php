<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSubmission;
use App\Models\Lesson;
use App\Models\ReadingMaterial;
use App\Models\UserLessonProgress;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentReadingController extends Controller
{
    /**
     * Menampilkan satu reading passage.
     */
    public function reading(
        Lesson $lesson
    ) {
        $material = ReadingMaterial::with([
            'questions' => function ($query) {
                $query->orderBy('id');
            },
        ])
            ->where(
                'lesson_id',
                $lesson->id
            )
            ->orderBy('id')
            ->firstOrFail();

        return view(
            'missions.reading.index',
            compact(
                'lesson',
                'material'
            )
        );
    }

    /**
     * Menampilkan semua soal dari reading passage.
     */
    public function quiz(
        Lesson $lesson
    ) {
        $material = ReadingMaterial::with([
            'questions' => function ($query) {
                $query->orderBy('id');
            },
        ])
            ->where(
                'lesson_id',
                $lesson->id
            )
            ->orderBy('id')
            ->firstOrFail();

        return view(
            'missions.reading.quiz',
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
     * Menyimpan jawaban quiz reading Unit 1–4.
     */
    public function complete(
        Request $request,
        Lesson $lesson,
        GamificationService $gamificationService
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Validasi Jawaban
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'answers' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'answers.*' => [
                    'required',
                    'in:A,B,C,D,E',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Ambil Reading Material
        |--------------------------------------------------------------------------
        */

        $material =
            ReadingMaterial::with([
                'questions' => function ($query) {
                    $query->orderBy('id');
                },
            ])
                ->where(
                    'lesson_id',
                    $lesson->id
                )
                ->orderBy('id')
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Pastikan seluruh soal sudah dijawab
        |--------------------------------------------------------------------------
        */

        $answers =
            $validated['answers'];

        $missingQuestions =
            $material
                ->questions
                ->filter(
                    function ($question) use ($answers) {
                        return !array_key_exists(
                            $question->id,
                            $answers
                        );
                    }
                );

        if ($missingQuestions->isNotEmpty()) {
            return response()->json(
                [
                    'message' =>
                        'Semua soal wajib dijawab sebelum quiz dikirim.',
                ],
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan hasil Reading
        |--------------------------------------------------------------------------
        */

        $submission =
            DB::transaction(
                function () use (
                    $validated,
                    $lesson,
                    $material
                ) {
                    $answers =
                        $validated['answers'];

                    $earnedScore = 0;
                    $maximumScore = 0;

                    /*
                    |--------------------------------------------------------------------------
                    | Assessment Submission
                    |--------------------------------------------------------------------------
                    */

                    $submission =
                        AssessmentSubmission::create([
                            'user_id' =>
                                Auth::id(),

                            'unit_id' =>
                                $lesson->unit_id,

                            'lesson_id' =>
                                $lesson->id,

                            'type' =>
                                'unit',

                            'skill' =>
                                'reading',

                            'final_score' =>
                                0,

                            'status' =>
                                'completed',

                            'feedback' =>
                                'Reading quiz completed.',

                            'submitted_at' =>
                                now(),
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Proses Jawaban
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $material->questions
                        as $question
                    ) {
                        $selected =
                            $answers[
                                $question->id
                            ] ?? null;

                        $correctAnswer =
                            strtoupper(
                                trim(
                                    (string)
                                    $question
                                        ->correct_answer
                                )
                            );

                        $selectedAnswer =
                            $selected !== null
                                ? strtoupper(
                                    trim(
                                        (string)
                                        $selected
                                    )
                                )
                                : null;

                        $isCorrect =
                            $selectedAnswer !== null
                            &&
                            $selectedAnswer
                            ===
                            $correctAnswer;

                        /*
                        |--------------------------------------------------------------------------
                        | Skor soal
                        |--------------------------------------------------------------------------
                        */

                        $questionMaximumScore =
                            max(
                                0,
                                (int)
                                $question->score
                            );

                        $questionEarnedScore =
                            $isCorrect
                                ? $questionMaximumScore
                                : 0;

                        $earnedScore +=
                            $questionEarnedScore;

                        $maximumScore +=
                            $questionMaximumScore;

                        /*
                        |--------------------------------------------------------------------------
                        | Simpan Assessment Answer
                        |--------------------------------------------------------------------------
                        */

                        AssessmentAnswer::create([
                            'assessment_submission_id' =>
                                $submission->id,

                            'question_type' =>
                                'reading',

                            'question_id' =>
                                $question->id,

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
                    | Final Score 0–100
                    |--------------------------------------------------------------------------
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
                    | Update Assessment Submission
                    |--------------------------------------------------------------------------
                    */

                    $submission->update([
                        'final_score' =>
                            $finalScore,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | User Lesson Progress
                    |--------------------------------------------------------------------------
                    */

                    UserLessonProgress::updateOrCreate(
                        [
                            'user_id' =>
                                Auth::id(),

                            'lesson_id' =>
                                $lesson->id,

                            'skill_type' =>
                                'reading',
                        ],
                        [
                            'unit_id' =>
                                $lesson->unit_id,

                            'status' =>
                                'completed',

                            'score' =>
                                $finalScore,

                            'completed_at' =>
                                now(),
                        ]
                    );

                    return $submission->fresh();
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Gamification Reward
        |--------------------------------------------------------------------------
        |
        | Reading selesai:
        |
        | +30 XP
        | +10 SpeakCoins
        |
        | Jika Reading merupakan skill terakhir dalam Unit,
        | bonus Unit akan diberikan otomatis oleh GamificationService.
        |
        */

        $gamification =
            $gamificationService
                ->rewardLessonCompletion(
                    Auth::user(),
                    $lesson,
                    'reading',
                    (int)
                    $submission
                        ->final_score
                );

        /*
        |--------------------------------------------------------------------------
        | JSON Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' =>
                true,

            'score' =>
                (int)
                $submission
                    ->final_score,

            'submission_id' =>
                $submission->id,

            'message' =>
                'Reading result saved.',

            /*
             * Nantinya dibaca oleh resources/js/gamification.js
             * untuk menampilkan popup reward.
             */
            'gamification' =>
                $gamification,
        ]);
    }
}