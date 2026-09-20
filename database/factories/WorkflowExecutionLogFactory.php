<?php

namespace Database\Factories;

use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowExecutionLog>
 */
class WorkflowExecutionLogFactory extends Factory
{
    /**
     * Define the model's default state: an info event row (journal line).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_execution_id' => WorkflowExecution::factory(),
            'attempt' => 1,
            'kind' => ExecutionLogKind::Event,
            'node_key' => null,
            'node_type' => null,
            'node_name' => null,
            'status' => null,
            'duration_ms' => null,
            'message' => 'Exécution démarrée (déclencheur : Manuel).',
            'level' => ExecutionLogLevel::Info,
            'input' => null,
            'output' => null,
            'error' => null,
            'offset_ms' => fake()->numberBetween(0, 5000),
        ];
    }

    /**
     * A node row pre-inserted at attempt open (timeline step waiting).
     */
    public function queued(): static
    {
        return $this->state(fn (): array => [
            'kind' => ExecutionLogKind::Node,
            'node_key' => fake()->uuid(),
            'node_type' => 'trigger.manual',
            'node_name' => 'Déclencheur manuel',
            'status' => 'queued',
            'duration_ms' => null,
            'message' => null,
            'level' => ExecutionLogLevel::Info,
            'offset_ms' => 0,
        ]);
    }

    /**
     * A node row updated after a successful execution.
     */
    public function okNode(): static
    {
        return $this->state(fn (): array => [
            'kind' => ExecutionLogKind::Node,
            'node_key' => fake()->uuid(),
            'node_type' => 'trigger.manual',
            'node_name' => 'Déclencheur manuel',
            'status' => 'ok',
            'duration_ms' => fake()->numberBetween(1, 900),
            'message' => 'Node « Déclencheur manuel » terminé en 12 ms.',
            'level' => ExecutionLogLevel::Ok,
            'output' => ['ok' => true],
            'offset_ms' => fake()->numberBetween(1, 5000),
        ]);
    }

    /**
     * A node row updated after a failed execution.
     */
    public function errorNode(): static
    {
        return $this->state(fn (): array => [
            'kind' => ExecutionLogKind::Node,
            'node_key' => fake()->uuid(),
            'node_type' => 'action.http',
            'node_name' => 'Requête HTTP',
            'status' => 'error',
            'duration_ms' => fake()->numberBetween(1, 900),
            'message' => 'Le node a échoué : Le service distant n’a pas répondu.',
            'level' => ExecutionLogLevel::Error,
            'error' => [
                'nodeKey' => null,
                'type' => 'action.http',
                'reason' => 'network_error',
                'message' => 'Le service distant n’a pas répondu.',
            ],
            'offset_ms' => fake()->numberBetween(1, 5000),
        ]);
    }

    /**
     * A node never executed when the attempt closed (cancel, retry…).
     */
    public function skipped(): static
    {
        return $this->state(fn (): array => [
            'kind' => ExecutionLogKind::Node,
            'node_key' => fake()->uuid(),
            'node_type' => 'data.output',
            'node_name' => 'Sortie',
            'status' => 'skipped',
            'duration_ms' => null,
            'message' => null,
            'level' => ExecutionLogLevel::Info,
            'offset_ms' => 0,
        ]);
    }
}
