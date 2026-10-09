# M01 — Pedagogical Agent Orchestrator (initial vertical slice)

## Scope
The M01 service is an application-layer coordinator over existing Laravel 13 learning services. It does **not** replace the current reading/vocabulary controllers or their scoring logic. This first vertical slice runs the sequence:

1. DiagnosticEngine::classify
2. AdaptationPolicy::decideHintLevel (including teacher override)
3. ScaffoldingEngine::generate only when the policy level is greater than zero

The caller supplies the question, selected answer, adaptation signals, scaffolding context, and optional teacher policy. The orchestrator overwrites any caller-provided hint level and error code with trusted engine results. The teacher's disable-LLM flag can be passed as force_template_only.

## Execution and trace
Call `app(App\\Services\\Orchestration\\AgentOrchestrator::class)->run($input)` from a trusted application service or controller. The result includes `workflow_id`, `status`, `diagnosis`, `decision`, `scaffolding`, and ordered `trace`. Trace events are also sent to the configured Laravel application log, excluding answer text, passage text, and student PII. No HTTP route is exposed in this slice.

## Verification
Run `php artisan test --filter=AgentOrchestratorTest`. Tests cover routing, teacher override propagation, skipping unnecessary scaffolding, invalid input, and failure handling.

## Not yet implemented
Durable queryable trace storage, idempotency, timeout/retry policy, persisted workflow state, assessment agent, learner-modeling agent, integration with live controllers, teacher dashboard, and end-to-end workflow tests. These require subsequent M01 increments and are not claimed complete.
