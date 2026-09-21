<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;

/**
 * Default executable graph used by the endpoint tests.
 *
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
function testRunGraph(): array
{
    return graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'data.transform', 'config' => ['expression' => '{{ trigger.email }}']],
            ['key' => 'n3', 'type' => 'logic.condition', 'config' => ['expression' => '{{ n2.value }}', 'operator' => 'contains', 'value' => '@']],
            ['key' => 'n4', 'type' => 'data.output'],
            ['key' => 'n5', 'type' => 'data.transform', 'config' => ['expression' => 'Reçu : {{ trigger.email }}']],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2', 'handle' => 'out'],
            ['source' => 'n2', 'target' => 'n3', 'handle' => 'out'],
            ['source' => 'n3', 'target' => 'n4', 'handle' => 'true'],
            ['source' => 'n3', 'target' => 'n5', 'handle' => 'false'],
        ],
    );
}

test('a valid workflow runs and returns the serialized execution result', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), testRunGraph())
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{"email":"client@example.com"}',
        ]);

    $response->assertOk()->assertJsonStructure([
        'status',
        'durationMs',
        'nodes' => [
            '*' => ['nodeKey', 'type', 'name', 'status', 'durationMs', 'output', 'error'],
        ],
        'errors',
    ]);

    $payload = $response->json();

    expect($payload['status'])->toBe('completed')
        ->and($payload['errors'])->toBe([])
        ->and($payload['durationMs'])->toBeInt()
        ->and($payload['durationMs'])->toBeGreaterThanOrEqual(0)
        ->and(array_column($payload['nodes'], 'nodeKey'))->toBe(['n1', 'n2', 'n3', 'n4', 'n5'])
        ->and($payload['nodes'][1]['status'])->toBe('ok')
        ->and($payload['nodes'][1]['output'])->toBe(['value' => 'client@example.com'])
        ->and($payload['nodes'][2]['output'])->toBe(['branch' => 'true', 'value' => true]);
});

test('the sample input is visible through the trigger alias', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), testRunGraph())
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{"email":"other@exemple.fr"}',
        ]);

    $payload = $response->json();

    expect($payload['status'])->toBe('completed')
        ->and($payload['nodes'][0]['output'])->toBe(['email' => 'other@exemple.fr']);
});

test('a workflow without any trigger returns 200 with a failed result', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [['key' => 'n2', 'type' => 'data.transform']],
            edges: [],
        ))
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk();

    $payload = $response->json();

    expect($payload['status'])->toBe('failed')
        ->and($payload['nodes'])->toBe([])
        ->and($payload['errors'][0]['reason'])->toBe('no_trigger')
        ->and($payload['errors'][0]['nodeKey'])->toBeNull();
});

test('a workflow without any node returns 200 with a failed result', function () {
    [$user, $team] = teamWithMember();
    // A fresh factory workflow: 0 node, 0 edge.
    $workflow = Workflow::factory()->for($team)->create();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk();

    $payload = $response->json();

    expect($payload['status'])->toBe('failed')
        ->and($payload['nodes'])->toBe([])
        ->and($payload['errors'][0]['reason'])->toBe('no_trigger')
        ->and($payload['errors'][0]['nodeKey'])->toBeNull();
});

test('a workflow containing a handlerless type returns 200 with handler_missing', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [
                ['key' => 'n1', 'type' => 'trigger.manual'],
                ['key' => 'n2', 'type' => 'action.delay'],
            ],
            edges: [['source' => 'n1', 'target' => 'n2']],
        ))
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk();

    $payload = $response->json();

    expect($payload['status'])->toBe('failed')
        ->and($payload['errors'][0]['reason'])->toBe('handler_missing')
        ->and($payload['errors'][0]['nodeKey'])->toBe('n2')
        ->and($payload['errors'][0]['type'])->toBe('action.delay')
        ->and($payload['errors'][0]['message'])->toContain('action.delay');
});

test('a malformed JSON input is rejected with 422 on the input key', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{"email":',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['input'])
        ->assertJsonFragment(['input' => ["L'input doit être un JSON valide."]]);
});

test('a non object JSON input is rejected with 422', function (string $payload) {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['input' => $payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['input'])
        ->assertJsonFragment(['input' => ['L\'input doit être un objet JSON.']]);
})->with([
    'a JSON list' => ['["a","b"]'],
    'a JSON scalar' => ['42'],
    'a JSON string' => ['"texte"'],
]);

test('an oversized or too deep JSON input is rejected with 422', function (callable $payloadFor) {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['input' => $payloadFor()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['input']);
})->with([
    'over 10000 characters' => [fn (): string => '{"k":"'.str_repeat('a', 10001).'"}'],
    'eleven levels deep' => [fn (): string => '{"a":{"a":{"a":{"a":{"a":{"a":{"a":{"a":{"a":{"a":{"a":1}}}}}}}}}}}'],
]);

test('a missing input defaults to an empty sample and still runs', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
            edges: [],
        ))
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk();

    $payload = $response->json();

    expect($payload['status'])->toBe('completed')
        ->and($payload['nodes'][0]['output'])->toBe([]);
});

test('a workflow from another team cannot be test run', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertNotFound();
});

test('a non member is forbidden to test run', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertForbidden();
});

test('a guest is unauthorized to test run', function () {
    [$owner, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertUnauthorized();
});
