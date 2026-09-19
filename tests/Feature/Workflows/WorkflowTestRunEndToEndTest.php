<?php

use App\Models\Workflow;

/**
 * The executable demo graph (mirrors the builder mockup demo).
 *
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
function demoGraph(): array
{
    return graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'data.input'],
            ['key' => 'n3', 'type' => 'data.transform', 'config' => ['expression' => '{{ trigger.email }}']],
            ['key' => 'n4', 'type' => 'logic.condition', 'config' => ['expression' => '{{ n3.value }}', 'operator' => 'contains', 'value' => '@']],
            ['key' => 'n5', 'type' => 'data.output'],
            ['key' => 'n6', 'type' => 'data.transform', 'config' => ['expression' => 'Reçu : {{ trigger.email }}']],
            ['key' => 'n7', 'type' => 'data.output'],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2', 'handle' => 'out'],
            ['source' => 'n2', 'target' => 'n3', 'handle' => 'out'],
            ['source' => 'n3', 'target' => 'n4', 'handle' => 'out'],
            ['source' => 'n4', 'target' => 'n5', 'handle' => 'true'],
            ['source' => 'n4', 'target' => 'n6', 'handle' => 'false'],
            ['source' => 'n6', 'target' => 'n7', 'handle' => 'out'],
        ],
    );
}

test('the saved demo graph runs end to end through the true branch', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), demoGraph())
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{"email":"client@example.com"}',
        ]);

    $response->assertOk();

    $payload = $response->json();

    $nodes = collect($payload['nodes'])->keyBy(fn (array $node) => $node['nodeKey']);

    expect($payload['status'])->toBe('completed')
        ->and($payload['errors'])->toBe([])
        ->and($nodes['n2']['output'])->toBe(['email' => 'client@example.com'])
        ->and($nodes['n3']['output'])->toBe(['value' => 'client@example.com'])
        ->and($nodes['n4']['output'])->toBe(['branch' => 'true', 'value' => true])
        ->and($nodes['n5']['output'])->toBe(['value' => ['branch' => 'true', 'value' => true]])
        ->and($nodes['n6']['status'])->toBe('skipped')
        ->and($nodes['n7']['status'])->toBe('skipped');
});

test('the saved demo graph runs end to end through the false branch', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), demoGraph())
        ->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{"email":"not-an-email"}',
        ]);

    $response->assertOk();

    $payload = $response->json();

    $nodes = collect($payload['nodes'])->keyBy(fn (array $node) => $node['nodeKey']);

    expect($payload['status'])->toBe('completed')
        ->and($payload['errors'])->toBe([])
        ->and($nodes['n4']['output'])->toBe(['branch' => 'false', 'value' => false])
        ->and($nodes['n5']['status'])->toBe('skipped')
        ->and($nodes['n6']['output'])->toBe(['value' => 'Reçu : not-an-email'])
        ->and($nodes['n7']['output'])->toBe(['value' => ['value' => 'Reçu : not-an-email']]);
});
