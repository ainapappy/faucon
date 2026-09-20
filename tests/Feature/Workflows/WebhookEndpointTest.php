<?php

use App\Actions\Workflows\EnsureWebhookEndpoint;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Workflow;

/**
 * A webhook-triggered graph used by the endpoint tests.
 *
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
function webhookGraph(): array
{
    return graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.webhook'],
            ['key' => 'n2', 'type' => 'data.output'],
        ],
        edges: [['source' => 'n1', 'target' => 'n2']],
    );
}

test('ensure creates the endpoint when the graph contains a webhook node', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    expect($workflow->webhookEndpoint()->exists())->toBeTrue()
        ->and(WebhookEndpoint::query()->count())->toBe(1);
});

test('ensure is idempotent — two calls leave a single endpoint', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    $action = app(EnsureWebhookEndpoint::class);

    $first = $action->handle($workflow);
    $second = $action->handle($workflow);

    expect($first)->not->toBeNull()
        ->and($second?->id)->toBe($first?->id)
        ->and(WebhookEndpoint::query()->count())->toBe(1);
});

test('regenerating the token replaces both the token and its hash', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    $endpoint = $workflow->webhookEndpoint()->firstOrFail();
    $oldToken = $endpoint->token;
    $oldHash = $endpoint->token_hash;

    $response = $this->actingAs($user)
        ->postJson(route('workflows.webhook.regenerate', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk()->assertJsonStructure(['url']);

    $endpoint->refresh();

    expect($endpoint->token)->not->toBe($oldToken)
        ->and($endpoint->token_hash)->not->toBe($oldHash)
        ->and($endpoint->token_hash)->toBe(WebhookEndpoint::hashToken($endpoint->token))
        ->and($response->json('url'))->toContain($endpoint->token);
});

test('removing the webhook node from the graph deletes the endpoint', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    expect($workflow->webhookEndpoint()->exists())->toBeTrue();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
            edges: [],
        ))
        ->assertNoContent();

    expect($workflow->webhookEndpoint()->exists())->toBeFalse()
        ->and(WebhookEndpoint::query()->count())->toBe(0);
});

test('the webhook url endpoint returns the decrypted token in the app url', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    $token = $workflow->webhookEndpoint()->firstOrFail()->token;

    $response = $this->actingAs($user)
        ->getJson(route('workflows.webhook.url', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk()->assertJsonStructure(['url']);

    expect($response->json('url'))->toStartWith(config('app.url'))
        ->and($response->json('url'))->toContain('/webhooks/'.$token);
});

test('a member of another team cannot regenerate the token', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($owner)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    $intruderTeam = Team::factory()->create();
    $intruder = User::factory()->create();
    $intruderTeam->members()->attach($intruder, ['role' => TeamRole::Owner->value]);

    $this->actingAs($intruder)
        ->postJson(route('workflows.webhook.regenerate', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->getJson(route('workflows.webhook.url', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertForbidden();
});

test('the webhook token appears in no page prop or html', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), webhookGraph())
        ->assertNoContent();

    $token = $workflow->webhookEndpoint()->firstOrFail()->token;

    $response = $this->actingAs($user)
        ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertOk();

    expect($response->getContent())->not->toContain($token)
        ->and(Str::length($token))->toBe(48);
});
