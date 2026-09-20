<?php

use App\Actions\Workflows\SaveWorkflowGraph;
use App\Jobs\RunWorkflowJob;
use App\Models\WebhookEndpoint;
use App\Models\WebhookRequest;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Save a webhook graph and return the endpoint.
 */
function webhookEndpointWithGraph(): WebhookEndpoint
{
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);

    app(SaveWorkflowGraph::class)->handle($workflow, [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 0, 'positionY' => 0],
        ['key' => 'n2', 'type' => 'data.output', 'name' => 'Sortie', 'config' => [], 'positionX' => 0, 'positionY' => 0],
    ], [
        ['sourceNodeKey' => 'n1', 'targetNodeKey' => 'n2', 'sourceHandle' => 'out'],
    ]);

    return $workflow->webhookEndpoint()->firstOrFail();
}

/**
 * Save a webhook to http graph and return the endpoint.
 */
function webhookHttpEndpoint(): WebhookEndpoint
{
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);

    app(SaveWorkflowGraph::class)->handle($workflow, [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 0, 'positionY' => 0],
        ['key' => 'n2', 'type' => 'action.http', 'name' => 'Requête HTTP', 'config' => [
            'method' => 'POST',
            'url' => 'https://203.0.113.10/relay',
            'body' => '{{ trigger.event }}',
        ], 'positionX' => 0, 'positionY' => 0],
    ], [
        ['sourceNodeKey' => 'n1', 'targetNodeKey' => 'n2', 'sourceHandle' => 'out'],
    ]);

    return $workflow->webhookEndpoint()->firstOrFail();
}

test('a valid webhook post dispatches a queued execution and answers 202', function () {
    Queue::fake();

    $endpoint = webhookEndpointWithGraph();

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), [
        'event' => 'order.created',
        'id' => 42,
    ]);

    $response->assertStatus(202)->assertJson(['status' => 'pending']);

    $executionId = $response->json('execution_id');

    expect($executionId)->toBeInt();

    Queue::assertPushed(RunWorkflowJob::class, fn (RunWorkflowJob $job) => $job->executionId === $executionId);

    $execution = WorkflowExecution::query()->findOrFail($executionId);

    expect($execution->status->value)->toBe('pending')
        ->and($execution->triggered_by->value)->toBe('webhook')
        ->and($execution->input)->toBe(['event' => 'order.created', 'id' => 42]);
});

test('an unknown token returns the same 404 shape as any other ineligibility', function () {
    webhookEndpointWithGraph();

    $response = $this->postJson(route('webhooks.handle', ['token' => str_repeat('a', 48)]), ['a' => 1]);

    $response->assertNotFound();

    expect($response->json())->toBe(['message' => 'Not found.']);
});

test('a draft workflow returns the same 404 shape', function () {
    $endpoint = webhookEndpointWithGraph();
    $endpoint->workflow->update(['status' => 'draft']);

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['a' => 1]);

    $response->assertNotFound();

    expect($response->json())->toBe(['message' => 'Not found.']);
});

test('a soft-deleted workflow returns the same 404 shape', function () {
    $endpoint = webhookEndpointWithGraph();
    $endpoint->workflow->delete();

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['a' => 1]);

    $response->assertNotFound();

    expect($response->json())->toBe(['message' => 'Not found.']);
});

test('an orphan endpoint without webhook node returns the same 404 shape', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);
    $token = str_repeat('b', 48);
    WebhookEndpoint::factory()->for($workflow)->withToken($token)->create();

    $response = $this->postJson(route('webhooks.handle', ['token' => $token]), ['a' => 1]);

    $response->assertNotFound();

    expect($response->json())->toBe(['message' => 'Not found.']);
});

test('a payload over 64 KiB is refused with 413', function () {
    $endpoint = webhookEndpointWithGraph();

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), [
        'blob' => str_repeat('a', 70000),
    ]);

    $response->assertStatus(413);
});

test('malformed json is refused with 422', function () {
    $endpoint = webhookEndpointWithGraph();

    $response = $this->call(
        'POST',
        route('webhooks.handle', ['token' => $endpoint->token]),
        [],
        [],
        [],
        ['HTTP_CONTENT_TYPE' => 'application/json'],
        '{"event": "broken',
    );

    $response->assertStatus(422);

    expect($response->json('message'))->toBe('Le payload doit être un JSON valide.');
});

test('a json list payload is refused with 422', function () {
    $endpoint = webhookEndpointWithGraph();

    $response = $this->call(
        'POST',
        route('webhooks.handle', ['token' => $endpoint->token]),
        [],
        [],
        [],
        ['HTTP_CONTENT_TYPE' => 'application/json'],
        json_encode([1, 2, 3]),
    );

    $response->assertStatus(422);

    expect($response->json('message'))->toBe('Le payload doit être un objet JSON.');
});

test('a json payload deeper than ten levels is refused with 422', function () {
    $endpoint = webhookEndpointWithGraph();

    $deep = [];
    $cursor = &$deep;

    foreach (range(1, 11) as $i) {
        $cursor['l'.$i] = [];
        $cursor = &$cursor['l'.$i];
    }

    $response = $this->call(
        'POST',
        route('webhooks.handle', ['token' => $endpoint->token]),
        [],
        [],
        [],
        ['HTTP_CONTENT_TYPE' => 'application/json'],
        (string) json_encode($deep),
    );

    $response->assertStatus(422);

    expect($response->json('message'))->toBe('Le JSON est trop profond (10 niveaux max).');
});

test('an oversized X-Request-Id header is refused with 422', function () {
    $endpoint = webhookEndpointWithGraph();

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['a' => 1], [
        'X-Request-Id' => str_repeat('x', 256),
    ]);

    $response->assertStatus(422);
});

test('a duplicated X-Request-Id triggers a single execution', function () {
    Queue::fake();

    $endpoint = webhookHttpEndpoint();

    $first = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['event' => 'order.created'], [
        'X-Request-Id' => 'req-abc-123',
    ]);

    $second = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['event' => 'order.created'], [
        'X-Request-Id' => 'req-abc-123',
    ]);

    $first->assertStatus(202);
    $second->assertOk();

    expect($second->json())->toBe(['status' => 'duplicate'])
        ->and(WebhookRequest::query()->count())->toBe(1)
        ->and(WebhookRequest::query()->first()->token_hash)->toBe($endpoint->token_hash)
        ->and(WebhookRequest::query()->first()->request_id_hash)->toBe(hash('sha256', 'req-abc-123'));

    Queue::assertPushed(RunWorkflowJob::class, 1);
});

test('requests without an X-Request-Id dispatch every time', function () {
    Queue::fake();

    $endpoint = webhookHttpEndpoint();

    $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['event' => 'a'])->assertStatus(202);
    $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['event' => 'a'])->assertStatus(202);

    expect(WebhookRequest::query()->count())->toBe(0);

    Queue::assertPushed(RunWorkflowJob::class, 2);
});

test('the webhook endpoint is rate limited per token', function () {
    config(['workflows.webhook.rate_limit_per_minute' => 2]);

    Queue::fake();

    $endpoint = webhookEndpointWithGraph();
    RateLimiter::clear('webhook:'.hash('sha256', $endpoint->token));

    $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['a' => 1])->assertStatus(202);
    $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['a' => 1])->assertStatus(202);

    $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['a' => 1])->assertStatus(429);
});

test('model:prune deletes only the requests outside the idempotence window', function () {
    $endpoint = webhookEndpointWithGraph();

    Carbon::setTestNow('2026-09-20 12:00:00');

    WebhookRequest::query()->insert([
        ['token_hash' => $endpoint->token_hash, 'request_id_hash' => hash('sha256', 'old'), 'received_at' => now()->subDays(2)],
        ['token_hash' => $endpoint->token_hash, 'request_id_hash' => hash('sha256', 'fresh'), 'received_at' => now()],
    ]);

    $this->artisan('model:prune');

    expect(WebhookRequest::query()->where('request_id_hash', hash('sha256', 'old'))->exists())->toBeFalse()
        ->and(WebhookRequest::query()->where('request_id_hash', hash('sha256', 'fresh'))->exists())->toBeTrue();

    Carbon::setTestNow();
});
