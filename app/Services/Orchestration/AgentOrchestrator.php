<?php

namespace App\Services\Orchestration;

use App\Services\Learning\AdaptationPolicy;
use App\Services\Learning\DiagnosticEngine;
use App\Services\Learning\ScaffoldingEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * M01: deterministic pedagogical workflow over existing SpeakVerse services.
 * No changes to grading, learner records, or existing controllers.
 */
final class AgentOrchestrator
{
    public function __construct(
        private readonly DiagnosticEngine $diagnostic,
        private readonly AdaptationPolicy $policy,
        private readonly ScaffoldingEngine $scaffolding,
    ) {}

    /**
     * @param array{
     *   question: object, selected_answer: string,
     *   signals?: array, scaffolding_context: array,
     *   teacher_override?: array|null, force_template_only?: bool,
     *   user_id?: int|null, correlation_id?: string
     * } $input
     * @return array{workflow_id:string,status:string,diagnosis:?array,decision:?array,scaffolding:?array,trace:array}
     */
    public function run(array $input): array
    {
        if (! isset($input['question']) || ! is_object($input['question'])
            || ! isset($input['selected_answer']) || ! is_string($input['selected_answer'])
            || ! isset($input['scaffolding_context']) || ! is_array($input['scaffolding_context'])) {
            throw new InvalidArgumentException('question, selected_answer and scaffolding_context are required.');
        }

        $workflowId = (string) Str::uuid();
        $trace = [];
        $diagnosis = null;
        $decision = null;
        $scaffolding = null;
        $step = function (string $agent, string $status, array $details = []) use (&$trace): void {
            $trace[] = [
                'sequence' => count($trace) + 1,
                'agent' => $agent,
                'status' => $status,
                'details' => $details,
                'occurred_at' => now()->toIso8601String(),
            ];
        };

        try {
            $diagnosis = $this->diagnostic->classify($input['question'], $input['selected_answer']);
            $step('diagnostic', 'completed', ['error_code' => $diagnosis['error_code'] ?? null]);

            $decision = $this->policy->decideHintLevel(
                $input['signals'] ?? [],
                $input['teacher_override'] ?? null
            );
            $level = (int) ($decision['level'] ?? 0);
            $step('adaptation_policy', 'completed', [
                'level' => $level,
                'reasons' => $decision['reasons'] ?? [],
            ]);

            if ($level === 0) {
                $step('scaffolding', 'skipped', ['reason' => 'level_zero']);
            } else {
                $context = $input['scaffolding_context'];
                // Orchestrator owns the diagnosis and policy level, not the caller.
                $context['error_code'] = $diagnosis['error_code'] ?? null;
                $context['level'] = $level;
                $scaffolding = $this->scaffolding->generate(
                    $context,
                    (bool) ($input['force_template_only'] ?? false),
                    $input['user_id'] ?? null
                );
                $step('scaffolding', 'completed', [
                    'level' => $scaffolding['level'] ?? $level,
                    'source' => $scaffolding['source'] ?? 'unknown',
                ]);
            }

            $status = 'completed';
        } catch (Throwable $exception) {
            $step('orchestrator', 'failed', ['exception_type' => get_class($exception)]);
            $status = 'failed';
            Log::warning('M01 orchestrator failed', [
                'workflow_id' => $workflowId,
                'exception_type' => get_class($exception),
            ]);
        }

        // Deliberately exclude student answers, question text, hints, and PII from logs.
        Log::info('M01 workflow trace', [
            'workflow_id' => $workflowId,
            'correlation_id' => $input['correlation_id'] ?? null,
            'status' => $status,
            'trace' => $trace,
        ]);

        return compact('workflowId', 'status', 'diagnosis', 'decision', 'scaffolding', 'trace')
            + ['workflow_id' => $workflowId];
    }
}
