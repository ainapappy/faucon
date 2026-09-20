<?php

use App\Models\Workflow;
use App\Models\WorkflowEdge;

test('saving the graph persists nodes and edges', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.webhook', 'positionX' => 60, 'positionY' => 200],
            ['key' => 'n2', 'type' => 'ai.classification', 'config' => ['model' => 'fake/demo'], 'positionX' => 330, 'positionY' => 150],
            ['key' => 'n3', 'type' => 'action.email', 'positionX' => 600, 'positionY' => 100],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2', 'handle' => 'out'],
            ['source' => 'n2', 'target' => 'n3'],
        ],
    );

    $response = $this
        ->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload);

    $response->assertNoContent();

    $this->assertDatabaseCount('workflow_nodes', 3);
    $this->assertDatabaseCount('workflow_edges', 2);

    $webhook = $workflow->nodes()->where('key', 'n1')->firstOrFail();

    expect($webhook->name)->toBe('trigger.webhook')
        ->and($webhook->config)->toBe([])
        ->and($webhook->position_x)->toBe(60)
        ->and($webhook->position_y)->toBe(200)
        ->and($workflow->edges()->where('source_node_key', 'n1')->firstOrFail()->source_handle)->toBe('out')
        ->and($workflow->edges()->where('source_node_key', 'n2')->firstOrFail()->source_handle)->toBeNull();
});

test('re saving the graph replaces the previous graph cleanly', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $first = graphPayload(
        nodes: [
            ['key' => 'old-1', 'type' => 'trigger.manual'],
            ['key' => 'old-2', 'type' => 'action.email'],
        ],
        edges: [['source' => 'old-1', 'target' => 'old-2']],
    );

    $this
        ->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $first)
        ->assertNoContent();

    $second = graphPayload(
        nodes: [
            ['key' => 'new-1', 'type' => 'trigger.schedule', 'config' => ['cron' => '0 9 * * 1']],
            ['key' => 'new-2', 'type' => 'data.input'],
            ['key' => 'new-3', 'type' => 'action.email'],
        ],
        edges: [['source' => 'new-1', 'target' => 'new-3']],
    );

    $this
        ->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $second)
        ->assertNoContent();

    $this->assertDatabaseCount('workflow_nodes', 3);
    $this->assertDatabaseCount('workflow_edges', 1);

    expect($workflow->nodes()->where('key', 'old-1')->exists())->toBeFalse()
        ->and($workflow->nodes()->where('key', 'new-1')->exists())->toBeTrue()
        ->and(WorkflowEdge::query()->count())->toBe(1);
});

test('saving an empty graph clears the workflow', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $full = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
    );

    $this
        ->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $full)
        ->assertNoContent();

    $this
        ->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload())
        ->assertNoContent();

    $this->assertDatabaseCount('workflow_nodes', 0);
    $this->assertDatabaseCount('workflow_edges', 0);
});
