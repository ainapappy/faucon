<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'status' => WorkflowStatus::Draft,
        ];
    }

    /**
     * Indicate that the workflow is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowStatus::Active,
        ]);
    }

    /**
     * Indicate that the workflow has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
