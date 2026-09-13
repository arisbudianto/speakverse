<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSubmission;
use App\Models\Lesson;
use App\Models\UserLessonProgress;
use App\Models\WritingMaterial;
use App\Models\WritingSubmission;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentWritingController extends Controller
{
    /**
     * Menampilkan materi Writing Unit 1–4.
     */
    public function index(Lesson $lesson)
    {
        $material = WritingMaterial::with('questions')
            ->where('lesson_id', $lesson->id)
            ->first();

        if (!$material) {
            return redirect()
                ->route('missions')
                ->with(
                    'error',
                    'Writing material is not available yet. Please wait until the admin adds it.'
                );
        }

        return view(
            'missions.writing.index',
            compact(
                'lesson',
                'material'
            )
        );
    }

    /**
     * Menampilkan quiz Writing Unit 1–4.
     */
    public function quiz(Lesson $lesson)
    {
        $material = WritingMaterial::with('questions')
            ->where('lesson_id', $lesson->id)
            ->first();

        if (!$material) {
            return redirect()
                ->route('missions')
                ->with(
                    'error',
                    'Writing task is not available yet. Please wait until the admin adds it.'
                );
        }

        return view(
            'missions.writing.quiz',
            compact(
                'lesson',
                'material'
            )
        );
    }

    /**
     * Menilai dan menyimpan Writing Unit 1–4.
     */
    public function submit(
        Request $request,
        Lesson $lesson,
        WritingMaterial $material,
        GamificationService $gamificationService
    ): JsonResponse {
        abort_unless(
            (int) $material->lesson_id ===
            (int) $lesson->id,
            404
        );

        $validated = $request->validate([
            'answer' => [
                'required',
                'string',
                'min:20',
                'max:50000',
            ],
        ]);

        $material->load('questions');

        $studentAnswer = trim(
            (string) $validated['answer']
        );

        /*
         * WritingMaterial dapat mempunyai pertanyaan.
         * Jika tidak ada, judul material digunakan sebagai konteks.
         */
        $question = $material
            ->questions
            ->sortBy('id')
            ->first();

        $questionText = trim(
            (string) (
                $question?->question
                ?? $material->title
                ?? 'Writing task'
            )
        );

        $image = $question?->image;

        $submission = WritingSubmission::create([
            'user_id' =>
                Auth::id(),

            'writing_material_id' =>
                $material->id,

            'answer' =>
                $studentAnswer,
        ]);

        try {
            $result =
                $this->scoreWritingWithAi(
                    $questionText,
                    $image,
                    $studentAnswer
                );
        } catch (Throwable $exception) {
            Log::error(
                'Writing Unit AI evaluation failed.',
                [
                    'user_id' =>
                        Auth::id(),

                    'lesson_id' =>
                        $lesson->id,

                    'material_id' =>
                        $material->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            $result =
                $this->writingFallbackResult(
                    'Penilaian AI tidak dapat diproses sepenuhnya. '
                    . 'Nilai minimum rubric digunakan sementara.'
                );
        }

        $totalRubric =
            $result['orientation_score']
            + $result['complication_score']
            + $result['resolution_score']
            + $result['organization_score']
            + $result['mechanics_score'];

        /*
         * 5 kriteria × maksimum 4 = 20.
         */
        $finalScore = (int) round(
            (
                $totalRubric /
                20
            ) * 100
        );

        $assessmentSubmission = DB::transaction(
            function () use (
                $submission,
                $result,
                $finalScore,
                $lesson,
                $material,
                $question,
                $studentAnswer
            ) {
                /*
                |--------------------------------------------------------------------------
                | WritingSubmission
                |--------------------------------------------------------------------------
                */

                $submission->update([
                    'orientation_score' =>
                        $result['orientation_score'],

                    'complication_score' =>
                        $result['complication_score'],

                    'resolution_score' =>
                        $result['resolution_score'],

                    'organization_score' =>
                        $result['organization_score'],

                    'mechanics_score' =>
                        $result['mechanics_score'],

                    'final_score' =>
                        $finalScore,

                    'feedback' =>
                        $result['feedback'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | AssessmentSubmission
                |--------------------------------------------------------------------------
                */

                $assessmentSubmission =
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
                            'writing',

                        'final_score' =>
                            $finalScore,

                        'criteria_scores' => [
                            'orientation' =>
                                $result['orientation_score'],

                            'complication' =>
                                $result['complication_score'],

                            'resolution' =>
                                $result['resolution_score'],

                            'organization' =>
                                $result['organization_score'],

                            'mechanics' =>
                                $result['mechanics_score'],
                        ],

                        'status' =>
                            'completed',

                        'feedback' =>
                            $result['feedback'],

                        'submitted_at' =>
                            now(),
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Assessment Answer
                |--------------------------------------------------------------------------
                */

                AssessmentAnswer::create([
                    'assessment_submission_id' =>
                        $assessmentSubmission->id,

                    'question_type' =>
                        'writing',

                    'question_id' =>
                        $question?->id
                        ?? $material->id,

                    'answer' =>
                        $studentAnswer,

                    'selected_option' =>
                        null,

                    'is_correct' =>
                        null,

                    'orientation_score' =>
                        $result['orientation_score'],

                    'complication_score' =>
                        $result['complication_score'],

                    'resolution_score' =>
                        $result['resolution_score'],

                    'organization_score' =>
                        $result['organization_score'],

                    'mechanics_score' =>
                        $result['mechanics_score'],

                    'score' =>
                        $finalScore,

                    'max_score' =>
                        100,

                    'feedback' =>
                        $result['feedback'],
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
                            'writing',
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

                return $assessmentSubmission;
            }
        );

        $submission->refresh();

        /*
        |--------------------------------------------------------------------------
        | Gamification
        |--------------------------------------------------------------------------
        |
        | Writing:
        |
        | +40 XP
        | +15 SpeakCoins
        |
        | Jika Writing merupakan skill terakhir pada Unit,
        | bonus Unit otomatis diberikan.
        |
        */

        $gamification =
            $gamificationService
                ->rewardLessonCompletion(
                    Auth::user(),
                    $lesson,
                    'writing',
                    (int) $submission->final_score
                );

        /*
        |--------------------------------------------------------------------------
        | JSON Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'orientation_score' =>
                $submission->orientation_score,

            'complication_score' =>
                $submission->complication_score,

            'resolution_score' =>
                $submission->resolution_score,

            'organization_score' =>
                $submission->organization_score,

            'mechanics_score' =>
                $submission->mechanics_score,

            'total_score' =>
                $submission->final_score,

            'feedback' =>
                $submission->feedback,

            'assessment_submission_id' =>
                $assessmentSubmission->id,

            /*
             * Nantinya dibaca oleh gamification.js
             * untuk menampilkan Instant Reward Popup.
             */
            'gamification' =>
                $gamification,
        ]);
    }

    /**
     * Evaluasi Writing dengan Dinoiki/OpenAI-compatible API.
     */
    private function scoreWritingWithAi(
        string $question,
        ?string $image,
        string $answer
    ): array {
        $apiKey = config(
            'services.dinoiki.key'
        );

        if (!$apiKey) {
            throw new \RuntimeException(
                'DINOIKI_API_KEY is not configured.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'services.dinoiki.base_url',
                'https://ai.dinoiki.com/v1'
            ),
            '/'
        );

        $model = config(
            'services.dinoiki.chat_model',
            'gpt-4o'
        );

        $prompt = $this->buildWritingPrompt(
            $question,
            $image,
            $answer
        );

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->retry(
                2,
                1000
            )
            ->post(
                $baseUrl . '/chat/completions',
                [
                    'model' =>
                        $model,

                    'messages' => [
                        [
                            'role' =>
                                'system',

                            'content' =>
                                'You are an English writing examiner. Return only valid JSON.',
                        ],
                        [
                            'role' =>
                                'user',

                            'content' =>
                                $prompt,
                        ],
                    ],

                    'temperature' =>
                        0.2,

                    'max_completion_tokens' =>
                        900,
                ]
            );

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Writing AI request failed with HTTP status '
                . $response->status()
                . '.'
            );
        }

        $content = data_get(
            $response->json(),
            'choices.0.message.content',
            ''
        );

        $content =
            $this->cleanJsonResponse(
                (string) $content
            );

        $decoded = json_decode(
            $content,
            true
        );

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Writing AI returned invalid JSON.'
            );
        }

        return [
            'orientation_score' =>
                $this->normaliseRubricScore(
                    $decoded['orientation']
                    ?? 1
                ),

            'complication_score' =>
                $this->normaliseRubricScore(
                    $decoded['complication']
                    ?? 1
                ),

            'resolution_score' =>
                $this->normaliseRubricScore(
                    $decoded['resolution']
                    ?? 1
                ),

            'organization_score' =>
                $this->normaliseRubricScore(
                    $decoded['organization']
                    ?? 1
                ),

            'mechanics_score' =>
                $this->normaliseRubricScore(
                    $decoded['mechanics']
                    ?? 1
                ),

            'feedback' =>
                trim(
                    (string) (
                        $decoded['feedback']
                        ?? 'Tidak ada feedback.'
                    )
                ),
        ];
    }

    /**
     * Prompt rubric Writing.
     */
    private function buildWritingPrompt(
        string $question,
        ?string $image,
        string $answer
    ): string {
        $imageInformation = $image
            ? "An image is attached to the question with path/reference: {$image}"
            : 'No image is attached to the question.';

        return <<<PROMPT
You are an English writing examiner.

Evaluate the student's writing using ONLY the rubric below.

Use an integer score from 1 to 4 for each criterion.

ORIENTATION
1 = Characters, time, and place are difficult to identify.
2 = Characters and setting are identifiable but provide little detail.
3 = Characters and setting are described clearly with some vivid detail.
4 = Characters and setting are clearly and vividly developed.

COMPLICATION
1 = The problem is unclear.
2 = The problem can be identified but its significance is unclear.
3 = The problem and its significance are fairly easy to understand.
4 = The problem and its significance are very clear.

RESOLUTION
1 = There is no understandable solution.
2 = The solution is difficult to understand.
3 = The solution is understandable and mostly logical.
4 = The solution is clear, logical, and provides an effective ending.

ORGANIZATION
1 = Ideas appear randomly arranged.
2 = The text is difficult to follow and transitions are unclear.
3 = The text is fairly organized with mostly clear transitions.
4 = The text is very well organized with a logical sequence and clear transitions.

MECHANICS
1 = Many grammar, usage, spelling, or punctuation errors block comprehension.
2 = Serious errors sometimes interfere with comprehension.
3 = Only a few minor errors are present.
4 = No significant grammar, usage, spelling, or punctuation errors are present.

QUESTION:
{$question}

IMAGE INFORMATION:
{$imageInformation}

STUDENT ANSWER:
{$answer}

Return ONLY one valid JSON object using this exact structure:

{
  "orientation": 1,
  "complication": 1,
  "resolution": 1,
  "organization": 1,
  "mechanics": 1,
  "feedback": "Feedback konstruktif dalam Bahasa Indonesia, 3 sampai 6 kalimat."
}

Do not return Markdown.
Do not place JSON inside code fences.
PROMPT;
    }

    /**
     * Fallback jika AI gagal.
     */
    private function writingFallbackResult(
        string $feedback
    ): array {
        return [
            'orientation_score' => 1,
            'complication_score' => 1,
            'resolution_score' => 1,
            'organization_score' => 1,
            'mechanics_score' => 1,
            'feedback' => $feedback,
        ];
    }

    /**
     * Membersihkan Markdown code fence dari JSON AI.
     */
    private function cleanJsonResponse(
        string $content
    ): string {
        $content = trim($content);

        $content = preg_replace(
            '/^```(?:json)?\s*/i',
            '',
            $content
        );

        $content = preg_replace(
            '/\s*```$/',
            '',
            $content
        );

        $firstBrace = strpos(
            $content,
            '{'
        );

        $lastBrace = strrpos(
            $content,
            '}'
        );

        if (
            $firstBrace !== false
            &&
            $lastBrace !== false
            &&
            $lastBrace >= $firstBrace
        ) {
            return substr(
                $content,
                $firstBrace,
                $lastBrace - $firstBrace + 1
            );
        }

        return trim($content);
    }

    /**
     * Membatasi rubric 1–4.
     */
    private function normaliseRubricScore(
        mixed $score
    ): int {
        return max(
            1,
            min(
                4,
                (int) $score
            )
        );
    }
}