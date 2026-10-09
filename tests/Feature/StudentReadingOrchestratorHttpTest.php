<?php

namespace Tests\Feature;

use App\Models\AgentWorkflowTrace;
use App\Models\Lesson;
use App\Models\ReadingMaterial;
use App\Models\ReadingQuestion;
use App\Models\Unit;
use App\Models\User;
use App\Services\Learning\ScaffoldingEngine;
use App\Services\Learning\TeacherOverrideService;
use Mockery;
use Tests\TestCase;

class StudentReadingOrchestratorHttpTest extends TestCase
{
    public function test_guest_cannot_call_student_orchestration_endpoint(): void
    {
        $this->postJson('/missions/unit/999999/reading/orchestrate', [])
            ->assertUnauthorized();
    }

    public function test_authenticated_student_can_orchestrate_and_replay_over_http(): void
    {
        $student = User::factory()->create();
        $unit = Unit::query()->create([
            'title' => 'Reading Unit', 'order_number' => 1,
            'type' => 'unit', 'status' => 'active',
        ]);
        $lesson = Lesson::query()->create([
            'unit_id' => $unit->id, 'skill_type' => 'reading',
            'title' => 'Reading Lesson', 'order_number' => 1, 'status' => 'active',
        ]);
        $material = ReadingMaterial::query()->create([
            'lesson_id' => $lesson->id, 'title' => 'Reading Text',
            'passage' => 'A private passage about a museum.',
        ]);
        $question = ReadingQuestion::query()->create([
            'reading_material_id' => $material->id,
            'question' => 'What is the topic?',
            'option_a' => 'A museum', 'option_b' => 'A park',
            'option_c' => 'A library', 'option_d' => 'A station',
            'correct_answer' => 'A', 'error_if_wrong' => ['B' => 'lexical'],
        ]);
        $overrides = Mockery::mock(TeacherOverrideService::class);
        $overrides->shouldReceive('resolveForStudent')->andReturn(null);
        $this->app->instance(TeacherOverrideService::class, $overrides);
        $scaffolding = Mockery::mock(ScaffoldingEngine::class);
        $scaffolding->shouldReceive('generate')->once()->andReturn([
            'level' => 1, 'source' => 'rule', 'hint_text' => 'Read again.',
            'socratic_questions' => [],
        ]);
        $this->app->instance(ScaffoldingEngine::class, $scaffolding);

        $payload = [
            'question_id' => $question->id,
            'selected_answer' => 'B',
            'idempotency_key' => 'request-1',
        ];
        $url = route('student.reading.orchestrate', $lesson);
        $first = $this->actingAs($student)->postJson($url, $payload)
            ->assertOk()->assertJsonPath('status', 'completed');
        $second = $this->postJson($url, $payload)
            ->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame($first->json('workflow_id'), $second->json('workflow_id'));
        $this->assertSame(1, AgentWorkflowTrace::query()->whereNotNull('idempotency_key')->count());
        $this->assertArrayNotHasKey('diagnosis', $first->json());
        $this->assertArrayNotHasKey('correct_answer', $first->json());
    }

    public function test_student_cannot_orchestrate_a_question_outside_the_lesson(): void
    {
        $student = User::factory()->create();
        $unit = Unit::query()->create([
            'title' => 'Reading Unit', 'order_number' => 1,
            'type' => 'unit', 'status' => 'active',
        ]);
        $lesson = Lesson::query()->create([
            'unit_id' => $unit->id, 'skill_type' => 'reading',
            'title' => 'Reading Lesson', 'status' => 'active',
        ]);
        $this->actingAs($student)->postJson(route('student.reading.orchestrate', $lesson), [
            'question_id' => 999999, 'selected_answer' => 'B',
            'idempotency_key' => 'request-1',
        ])->assertNotFound();
    }
}
