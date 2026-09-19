<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowNode>
 */
class WorkflowNodeFactory extends Factory
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
            'key' => Str::uuid()->toString(),
            'type' => 'trigger.manual',
            'name' => fake()->words(2, true),
            'config' => null,
            'position_x' => 0,
            'position_y' => 0,
        ];
    }

    /**
     * Indicate the node type id (`category.type`).
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * Indicate the node configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'config' => $config,
        ]);
    }

    /**
     * Indicate the node canvas position.
     */
    public function at(int $x, int $y): static
    {
        return $this->state(fn (array $attributes) => [
            'position_x' => $x,
            'position_y' => $y,
        ]);
    }

    /**
     * Indicate the node key.
     */
    public function keyed(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }
}
