<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowEdge>
 */
class WorkflowEdgeFactory extends Factory
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
            'source_node_key' => Str::uuid()->toString(),
            'target_node_key' => Str::uuid()->toString(),
            'source_handle' => 'out',
        ];
    }

    /**
     * Indicate the edge connects the given nodes of the same workflow.
     */
    public function between(WorkflowNode $source, WorkflowNode $target, ?string $handle = 'out'): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_id' => $source->workflow_id,
            'source_node_key' => $source->key,
            'target_node_key' => $target->key,
            'source_handle' => $handle,
        ]);
    }
}
