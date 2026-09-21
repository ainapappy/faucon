<?php

use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Corrupt the raw config column of one node (invalid JSON), bypassing the
 * Eloquent cast — simulates a hand-edited or truncated database row.
 */
function corruptNodeConfig(WorkflowNode $node, string $raw): void
{
    DB::table('workflow_nodes')->where('id', $node->getKey())->update(['config' => $raw]);
}

test('a corrupted node config fails the test run as invalid_config instead of crashing', function () {
    Http::preventStrayRequests();

    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    $http = WorkflowNode::factory()->for($workflow)->keyed('h')->ofType('action.http')->withConfig([
        'method' => 'GET',
        'url' => 'https://api.exemple.com/relay',
    ])->create();
    corruptNodeConfig($http, '{"method": "GET"');

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk();

    $payload = $response->json();

    // The cast yields null, the mapper coerces it to [], the handler
    // validation rejects it — a business result, never a 500 and never
    // an HTTP attempt.
    expect($payload['status'])->toBe('failed')
        ->and($payload['nodes'])->toBe([])
        ->and($payload['errors'])->toHaveCount(1)
        ->and($payload['errors'][0]['reason'])->toBe('invalid_config')
        ->and($payload['errors'][0]['nodeKey'])->toBe('h')
        ->and($payload['errors'][0]['type'])->toBe('action.http')
        ->and($payload['errors'][0]['message'])->toContain('URL');
});

test('the builder edit page serves the corrupted node with an empty config', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    $http = WorkflowNode::factory()->for($workflow)->keyed('h')->ofType('action.http')->withConfig([
        'method' => 'GET',
        'url' => 'https://api.exemple.com/relay',
    ])->create();
    corruptNodeConfig($http, '{"method": "GET"');

    WorkflowEdge::factory()->for($workflow)->between(
        $workflow->nodes()->where('key', 't')->firstOrFail(),
        $http,
    )->create();

    $this
        ->actingAs($user)
        ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('workflows/Edit')
                ->has('graph.nodes', 2)
                ->has('graph.edges', 1);

            // No contractual row order (SQLite serves the (workflow_id, key)
            // index): the corrupted node is looked up by key, and its config
            // must degrade to [] — never null, never a crash.
            $corrupted = collect($page->toArray()['props']['graph']['nodes'])->firstWhere('key', 'h');

            expect($corrupted['config'])->toBe([])
                ->and($corrupted['config'])->not->toBeNull();
        });
});
