<?php

namespace App\Services\Orchestration;

use App\Models\AgentWorkflowTrace;
use App\Services\Learning\AdaptationPolicy;
use App\Services\Learning\DiagnosticEngine;
use App\Services\Learning\ScaffoldingEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\UniqueConstraintViolationException;
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
     *   user_id?: int|null, correlation_id?: string, idempotency_key?: string
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

        // Idempotency is opt-in and scoped to the authenticated learner.
        $requestKey = $input['idempotency_key'] ?? null;
        if ($requestKey !== null && (! is_string($requestKey) || strlen($requestKey) > 128
            || $requestKey === '' || ! isset($input['user_id']) || ! is_int($input['user_id']))) {
            throw new InvalidArgumentException('Idempotency requires a nonempty key and integer user_id.');
        }

        $scopedKey = $requestKey !== null
            ? hash('sha256', $input['user_id'].':'.$requestKey)
            : null;
        $fingerprint = $scopedKey !== null
            ? hash('sha256', serialize(array_diff_key($input, array_flip(['correlation_id', 'idempotency_key']))))
            : null;
        $workflowId = (string) Str::uuid();
        $claimed = false;
        if ($scopedKey !== null) {
            try {
                AgentWorkflowTrace::query()->create([
                    'workflow_id' => $workflowId,
                    'status' => 'processing',
                    'steps' => [],
                    'idempotency_key' => $scopedKey,
                    'input_fingerprint' => $fingerprint,
                ]);
                $claimed = true;
            } catch (UniqueConstraintViolationException $exception) {
                $existing = AgentWorkflowTrace::query()->where('idempotency_key', $scopedKey)->firstOrFail();
                if (! hash_equals((string) $existing->input_fingerprint, $fingerprint)) {
                    throw new InvalidArgumentException('Idempotency key was reused for different input.');
                }

                return $existing->result_payload ?? [
                    'workflow_id' => $existing->workflow_id,
                    'status' => 'processing',
                    'diagnosis' => null,
                    'decision' => null,
                    'scaffolding' => null,
                    'trace' => $existing->steps,
                ];
            }
        }

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

        $result = [
            'workflow_id' => $workflowId,
            'status' => $status,
            'diagnosis' => $diagnosis,
            'decision' => $decision,
            'scaffolding' => $scaffolding,
            'trace' => $trace,
        ];

        try {
            if ($claimed) {
                AgentWorkflowTrace::query()->where('workflow_id', $workflowId)->update([
                    'status' => $status,
                    'steps' => json_encode($trace),
                    'result_payload' => json_encode($result),
                ]);
            } else {
                AgentWorkflowTrace::query()->create([
                    'workflow_id' => $workflowId,
                    'status' => $status,
                    'steps' => $trace,
                ]);
            }
        } catch (Throwable $exception) {
            // Telemetry must not interrupt the learner's workflow.
            Log::warning('M01 trace persistence failed', [
                'workflow_id' => $workflowId,
                'exception_type' => get_class($exception),
            ]);
        }

        return $result;
    }
}
