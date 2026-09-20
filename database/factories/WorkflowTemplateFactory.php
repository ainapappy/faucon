<?php

namespace Database\Factories;

use App\Enums\TemplateOrigin;
use App\Models\Team;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    /**
     * The minimal VALID graph every template is born with (builder payload
     * shape, camelCase): manual trigger → output, edge on the `out` handle.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    final public const array MinimalGraph = [
        'nodes' => [
            ['key' => 'trigger', 'type' => 'trigger.manual', 'name' => 'Manuel', 'config' => [], 'positionX' => 100, 'positionY' => 200],
            ['key' => 'output', 'type' => 'data.output', 'name' => 'Sortie', 'config' => [], 'positionX' => 420, 'positionY' => 200],
        ],
        'edges' => [
            ['sourceNodeKey' => 'trigger', 'targetNodeKey' => 'output', 'sourceHandle' => 'out'],
        ],
    ];

    /**
     * Define the model's default state — a TEAM template by default (the
     * system state is explicit), with a valid minimal graph so instantiation
     * always works out of the box.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'origin' => TemplateOrigin::Team,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['Support', 'Ventes', 'Données']),
            'graph' => self::MinimalGraph,
        ];
    }

    /**
     * A system (global) template: no team, `origin` = system.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => null,
            'origin' => TemplateOrigin::System,
        ]);
    }

    /**
     * A team template: owning team + `origin` = team (explicit mirror of
     * the system state — the default definition is already a team template).
     */
    public function team(): static
    {
        return $this->state(fn (array $attributes): array => [
            'origin' => TemplateOrigin::Team,
        ]);
    }

    /**
     * Override the stored snapshot graph.
     *
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     */
    public function withGraph(array $graph): static
    {
        return $this->state(fn (array $attributes): array => [
            'graph' => $graph,
        ]);
    }
}
