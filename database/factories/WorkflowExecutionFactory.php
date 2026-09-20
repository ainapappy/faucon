<?php

namespace Database\Factories;

use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowExecution>
 */
class WorkflowExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'team_id' => fn (array $attributes): int => (int) Workflow::query()->whereKey($attributes['workflow_id'])->value('team_id'),
            'user_id' => null,
            'triggered_by' => ExecutionTrigger::Manual,
            'status' => ExecutionStatus::Pending,
            'attempt' => 1,
            'started_at' => null,
            'finished_at' => null,
            'duration_ms' => null,
            'input' => null,
            'result' => null,
            'error' => null,
        ];
    }

    /**
     * The execution is currently running.
     */
    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => ExecutionStatus::Running,
            'started_at' => now()->subSeconds(3),
        ]);
    }

    /**
     * The execution completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExecutionStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2)->addMilliseconds(412),
            'duration_ms' => 412,
            'result' => [
                'status' => 'completed',
                'durationMs' => 412,
                'nodes' => [
                    ['nodeKey' => 'n1', 'type' => 'trigger.manual', 'name' => 'Manuel', 'status' => 'ok', 'durationMs' => 1, 'output' => [], 'error' => null],
                    ['nodeKey' => 'n2', 'type' => 'data.output', 'name' => 'Sortie', 'status' => 'ok', 'durationMs' => 0, 'output' => ['ok' => true], 'error' => null],
                ],
                'errors' => [],
            ],
        ]);
    }

    /**
     * The execution failed with an explicit node error.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExecutionStatus::Failed,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5)->addMilliseconds(980),
            'duration_ms' => 980,
            'error' => [
                'nodeKey' => 'n2',
                'type' => 'action.http',
                'reason' => 'http_request_failed',
                'message' => 'Le node « Requête HTTP » a échoué.',
            ],
            'result' => [
                'status' => 'failed',
                'durationMs' => 980,
                'nodes' => [
                    ['nodeKey' => 'n1', 'type' => 'trigger.manual', 'name' => 'Manuel', 'status' => 'ok', 'durationMs' => 1, 'output' => [], 'error' => null],
                    ['nodeKey' => 'n2', 'type' => 'action.http', 'name' => 'Requête HTTP', 'status' => 'error', 'durationMs' => 900, 'output' => [], 'error' => null],
                ],
                'errors' => [
                    ['nodeKey' => 'n2', 'type' => 'action.http', 'reason' => 'http_request_failed', 'message' => 'Le node « Requête HTTP » a échoué.'],
                ],
            ],
        ]);
    }

    /**
     * The execution was cancelled between two nodes.
     */
    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => ExecutionStatus::Cancelled,
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute()->addMilliseconds(150),
            'duration_ms' => 150,
        ]);
    }

    /**
     * The execution was launched by the public webhook endpoint.
     */
    public function webhook(): static
    {
        return $this->state(fn (): array => [
            'triggered_by' => ExecutionTrigger::Webhook,
            'user_id' => null,
        ]);
    }

    /**
     * The execution was launched by the scheduler.
     */
    public function schedule(): static
    {
        return $this->state(fn (): array => [
            'triggered_by' => ExecutionTrigger::Schedule,
            'user_id' => null,
        ]);
    }
}
