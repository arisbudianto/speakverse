<?php

namespace Tests\Unit;

use App\Services\Learning\AdaptationPolicy;
use App\Services\Learning\DiagnosticEngine;
use App\Services\Learning\ScaffoldingEngine;
use App\Services\Orchestration\AgentOrchestrator;
use InvalidArgumentException;
use App\Models\AgentWorkflowTrace;
use Mockery;
use Tests\TestCase;

class AgentOrchestratorTest extends TestCase
{
    public function test_trace_is_persisted_with_no_student_answer_or_passage(): void
    {
            $diagnostic = Mockery::mock(DiagnosticEngine::class);
            $policy = Mockery::mock(AdaptationPolicy::class);
            $scaffolding = Mockery::mock(ScaffoldingEngine::class);
            $diagnostic->shouldReceive('classify')->once()->andReturn(['error_code' => null]);
            $policy->shouldReceive('decideHintLevel')->once()->andReturn(['level' => 0, 'reasons' => []]);
            $scaffolding->shouldNotReceive('generate');

            $result = (new AgentOrchestrator($diagnostic, $policy, $scaffolding))->run([
                'question' => (object) ['correct_answer' => 'A'],
                'selected_answer' => 'SECRET_STUDENT_ANSWER',
                'scaffolding_context' => ['text' => 'PRIVATE_PASSAGE'],
            ]);

            $saved = AgentWorkflowTrace::query()->where('workflow_id', $result['workflow_id'])->firstOrFail();
            $this->assertSame('completed', $saved->status);
            $this->assertCount(3, $saved->steps);
            $this->assertStringNotContainsString('SECRET_STUDENT_ANSWER', json_encode($saved->steps));
            $this->assertStringNotContainsString('PRIVATE_PASSAGE', json_encode($saved->steps));
    }

    public function test_routes_diagnostic_policy_and_scaffolding_in_order(): void
    {
        $question = (object) ['correct_answer' => 'A', 'error_if_wrong' => ['B' => 'lexical']];
        $diagnostic = Mockery::mock(DiagnosticEngine::class);
        $policy = Mockery::mock(AdaptationPolicy::class);
        $scaffolding = Mockery::mock(ScaffoldingEngine::class);

        $diagnostic->shouldReceive('classify')->once()->with($question, 'B')
            ->andReturn(['error_code' => 'lexical', 'error_confidence' => 1.0, 'classifier' => 'rule']);
        $policy->shouldReceive('decideHintLevel')->once()->with(['wrong_count' => 1], ['max_level' => 2])
            ->andReturn(['level' => 1, 'reasons' => ['wrong_first_attempt'], 'policy_version' => 'v1']);
        $scaffolding->shouldReceive('generate')->once()->with(
            ['text' => 'passage', 'error_code' => 'lexical', 'level' => 1],
            true,
            7
        )->andReturn(['level' => 1, 'source' => 'rule', 'hint_text' => 'Look at the context.']);

        $result = (new AgentOrchestrator($diagnostic, $policy, $scaffolding))->run([
            'question' => $question, 'selected_answer' => 'B',
            'signals' => ['wrong_count' => 1],
            'teacher_override' => ['max_level' => 2],
            'scaffolding_context' => ['text' => 'passage', 'level' => 3, 'error_code' => 'syntactic'],
            'force_template_only' => true, 'user_id' => 7,
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(['diagnostic', 'adaptation_policy', 'scaffolding'], array_column($result['trace'], 'agent'));
        $this->assertSame([1, 2, 3], array_column($result['trace'], 'sequence'));
        $this->assertSame('lexical', $result['diagnosis']['error_code']);
        $this->assertNotEmpty($result['workflow_id']);
    }

    public function test_zero_level_skips_scaffolding(): void
    {
        $diagnostic = Mockery::mock(DiagnosticEngine::class);
        $policy = Mockery::mock(AdaptationPolicy::class);
        $scaffolding = Mockery::mock(ScaffoldingEngine::class);
        $diagnostic->shouldReceive('classify')->once()->andReturn(['error_code' => null]);
        $policy->shouldReceive('decideHintLevel')->once()->andReturn(['level' => 0, 'reasons' => []]);
        $scaffolding->shouldNotReceive('generate');

        $result = (new AgentOrchestrator($diagnostic, $policy, $scaffolding))->run([
            'question' => (object) ['correct_answer' => 'A'],
            'selected_answer' => 'A', 'scaffolding_context' => [],
        ]);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('skipped', $result['trace'][2]['status']);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $orchestrator = new AgentOrchestrator(
            Mockery::mock(DiagnosticEngine::class),
            Mockery::mock(AdaptationPolicy::class),
            Mockery::mock(ScaffoldingEngine::class)
        );
        $this->expectException(InvalidArgumentException::class);
        $orchestrator->run([]);
    }

    public function test_agent_failure_is_traced_without_exposing_exception_message(): void
    {
        $diagnostic = Mockery::mock(DiagnosticEngine::class);
        $diagnostic->shouldReceive('classify')->once()->andThrow(new \RuntimeException('private student content'));
        $orchestrator = new AgentOrchestrator(
            $diagnostic, Mockery::mock(AdaptationPolicy::class), Mockery::mock(ScaffoldingEngine::class)
        );
        $result = $orchestrator->run([
            'question' => (object) [], 'selected_answer' => 'B', 'scaffolding_context' => [],
        ]);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('failed', $result['trace'][0]['status']);
        $this->assertStringNotContainsString('private student content', json_encode($result));
    }
}
