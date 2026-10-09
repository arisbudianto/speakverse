<?php

namespace Tests\Feature;

use App\Models\AgentWorkflowTrace;
use App\Services\Learning\AdaptationPolicy;
use App\Services\Learning\DiagnosticEngine;
use App\Services\Learning\ScaffoldingEngine;
use App\Services\Orchestration\AgentOrchestrator;
use Mockery;
use Tests\TestCase;

class AgentOrchestratorWorkflowTest extends TestCase
{
    public function test_real_diagnosis_and_adaptation_flow_persists_trace_and_replays(): void
    {
        $scaffolding = Mockery::mock(ScaffoldingEngine::class);
        $scaffolding->shouldReceive('generate')->once()->withArgs(
            fn (array $context, bool $templateOnly, ?int $userId) =>
                $context['error_code'] === 'lexical'
                && $context['level'] === 1
                && $templateOnly
                && $userId === 42
        )->andReturn(['level' => 1, 'source' => 'template', 'hint_text' => 'Read the sentence again.']);

        $orchestrator = new AgentOrchestrator(
            new DiagnosticEngine(),
            new AdaptationPolicy(),
            $scaffolding
        );
        $input = [
            'question' => (object) [
                'correct_answer' => 'A',
                'error_if_wrong' => ['B' => 'lexical'],
            ],
            'selected_answer' => 'B',
            'signals' => ['wrong_count' => 1],
            'scaffolding_context' => ['skill' => 'reading', 'text' => 'A private passage'],
            'force_template_only' => true,
            'user_id' => 42,
            'idempotency_key' => 'reading-session-42-question-7-hint-1',
        ];

        $first = $orchestrator->run($input);
        $replay = $orchestrator->run($input);

        $this->assertSame('completed', $first['status']);
        $this->assertSame('lexical', $first['diagnosis']['error_code']);
        $this->assertSame(1, $first['decision']['level']);
        $this->assertSame('Read the sentence again.', $first['scaffolding']['hint_text']);
        $this->assertSame($first['workflow_id'], $replay['workflow_id']);
        $this->assertSame(
            ['diagnostic', 'adaptation_policy', 'scaffolding'],
            array_column($first['trace'], 'agent')
        );
        $saved = AgentWorkflowTrace::query()->where('workflow_id', $first['workflow_id'])->firstOrFail();
        $this->assertSame('completed', $saved->status);
        $this->assertStringNotContainsString('A private passage', json_encode($saved->steps));
    }
}
