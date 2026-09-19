<?php

use App\Models\Workflow;
use App\Models\WorkflowNode;

test('a node without a type is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(nodes: [['key' => 'n1', 'type' => '']]);

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.type']);
});

test('a node with an unknown type is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(nodes: [['key' => 'n1', 'type' => 'trigger.unknown']]);

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.type']);
});

test('an edge referencing an unknown node key is rejected with an indexed message', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
        edges: [['source' => 'n1', 'target' => 'ghost']],
    );

    $response = $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['edges.0.targetNodeKey']);

    expect($response->json('errors')['edges.0.targetNodeKey'][0])->toContain('ghost');
});

test('a self edge is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
        edges: [['source' => 'n1', 'target' => 'n1']],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['edges.0.targetNodeKey']);
});

test('a duplicated connection is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'action.email'],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2'],
            ['source' => 'n1', 'target' => 'n2'],
        ],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['edges.1.sourceNodeKey']);
});

test('a cyclic graph is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'data.transform'],
            ['key' => 'n3', 'type' => 'action.delay'],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2'],
            ['source' => 'n2', 'target' => 'n3'],
            ['source' => 'n3', 'target' => 'n1'],
        ],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['edges'])
        ->assertJsonFragment(['edges' => [__('The graph contains a cycle.')]]);
});

test('more than one hundred nodes is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $nodes = [];

    foreach (range(1, 101) as $i) {
        $nodes[] = ['key' => "n{$i}", 'type' => 'data.input'];
    }

    $payload = graphPayload(nodes: $nodes);

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes']);
});

test('duplicated node keys are rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(nodes: [
        ['key' => 'n1', 'type' => 'trigger.manual'],
        ['key' => 'n1', 'type' => 'action.email'],
    ]);

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.1.key']);
});

test('an undeclared config key is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(nodes: [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'config' => ['not_a_field' => 'x']],
    ]);

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.config'])
        ->assertJsonFragment(['nodes.0.config' => [__('The configuration field ":field" is not declared for the node type ":type".', ['field' => 'not_a_field', 'type' => 'trigger.webhook'])]]);
});

test('a non scalar config value is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(nodes: [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'config' => ['path' => ['nested' => 'array']]],
    ]);

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.config.path']);
});

test('an unknown source handle is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'action.email'],
        ],
        edges: [['source' => 'n1', 'target' => 'n2', 'handle' => 'north']],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['edges.0.sourceHandle']);
});

test('activating without a trigger node is rejected with a status error', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonFragment(['status' => [__('A workflow needs at least one trigger node to be activated.')]]);
});

test('activating with a trigger node succeeds', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();

    $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'active'])
        ->assertNoContent();

    expect($workflow->fresh()->status->value)->toBe('active');
});
