<?php

use App\Actions\Workflows\SaveWorkflowGraph;
use App\Models\Workflow;

test('every web response carries the three security headers', function () {
    $response = $this->get('/');

    $response->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

test('the public webhook response carries the security headers too', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);

    app(SaveWorkflowGraph::class)->handle($workflow, [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 0, 'positionY' => 0],
        ['key' => 'n2', 'type' => 'data.output', 'name' => 'Sortie', 'config' => [], 'positionX' => 0, 'positionY' => 0],
    ], [
        ['sourceNodeKey' => 'n1', 'targetNodeKey' => 'n2', 'sourceHandle' => 'out'],
    ]);

    $endpoint = $workflow->webhookEndpoint()->firstOrFail();

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['event' => 'order.created']);

    $response->assertStatus(202);

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});
