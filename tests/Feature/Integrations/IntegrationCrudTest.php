<?php

use App\Enums\TeamRole;
use App\Models\Integration;
use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('a generic_http integration is created and stored team-scoped', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $payload = [
        'name' => 'API Interne',
        'type' => 'generic_http',
        'credentials' => [
            'baseUrl' => 'https://api.exemple.com',
            'auth' => 'none',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), $payload);

    $integration = Integration::query()->where('name', 'API Interne')->firstOrFail();

    $response->assertRedirect(route('integrations.index', ['current_team' => $team->slug]));

    expect($integration->team_id)->toBe($team->id)
        ->and($integration->type->value)->toBe('generic_http')
        ->and($integration->credentials)->toBe(['baseUrl' => 'https://api.exemple.com', 'auth' => 'none']);
});

test('an smtp integration is created with its connection settings', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $payload = [
        'name' => 'SMTP principal',
        'type' => 'smtp',
        'credentials' => [
            'host' => 'smtp.exemple.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'no-reply@exemple.com',
            'password' => 's3cret-value',
        ],
    ];

    $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), $payload)
        ->assertRedirect();

    $integration = Integration::query()->where('name', 'SMTP principal')->firstOrFail();

    expect($integration->type->value)->toBe('smtp')
        ->and($integration->credentials)->toBe($payload['credentials']);
});

test('a creation with an unknown type is rejected', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), [
            'name' => 'Inconnue',
            'type' => 'slack',
            'credentials' => ['anything' => true],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('a generic_http creation without baseUrl is rejected with a French message', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), [
            'name' => 'Sans URL',
            'type' => 'generic_http',
            'credentials' => ['auth' => 'none'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credentials.baseUrl'])
        ->assertJsonFragment([
            'credentials.baseUrl' => ['L’URL de base est requise.'],
        ]);
});

test('a bearer integration without a token is rejected', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), [
            'name' => 'Bearer incomplet',
            'type' => 'generic_http',
            'credentials' => [
                'baseUrl' => 'https://api.exemple.com',
                'auth' => 'bearer',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credentials.token']);
});

test('an smtp port outside the 1..65535 range is rejected', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $ports = [0, 70000];

    foreach ($ports as $port) {
        $this
            ->actingAs($user)
            ->postJson(route('integrations.store', ['current_team' => $team->slug]), [
                'name' => 'Port invalide',
                'type' => 'smtp',
                'credentials' => ['host' => 'smtp.exemple.com', 'port' => $port],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credentials.port']);
    }
});

test('credentials are encrypted at rest and decryptable on read', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $plaintext = 's3cret-bearer-token-value';

    Integration::factory()->for($team)->create([
        'type' => 'generic_http',
        'credentials' => ['baseUrl' => 'https://api.exemple.com', 'auth' => 'bearer', 'token' => $plaintext],
    ]);

    $raw = DB::table('integrations')->value('credentials');

    expect($raw)->toBeString()
        ->and($raw)->not->toBe(json_encode($plaintext))
        ->and(str_contains($raw, $plaintext))->toBeFalse()
        ->and(Integration::query()->sole()->credentials['token'])->toBe($plaintext);
});

test('an update without credentials leaves them unchanged', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->create([
        'name' => 'Avant',
        'credentials' => ['baseUrl' => 'https://api.exemple.com', 'auth' => 'bearer', 'token' => 'keep-me'],
    ]);

    $this
        ->actingAs($user)
        ->patchJson(route('integrations.update', ['current_team' => $team->slug, 'integration' => $integration->id]), [
            'name' => 'Après',
            'type' => 'generic_http',
        ])
        ->assertNoContent();

    expect($integration->fresh()->name)->toBe('Après')
        ->and($integration->fresh()->credentials)->toBe(['baseUrl' => 'https://api.exemple.com', 'auth' => 'bearer', 'token' => 'keep-me']);
});

test('an update with credentials replaces them entirely', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->create([
        'credentials' => ['baseUrl' => 'https://api.exemple.com', 'auth' => 'none'],
    ]);

    $replacement = [
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'bearer',
        'token' => 'new-token',
    ];

    $this
        ->actingAs($user)
        ->patchJson(route('integrations.update', ['current_team' => $team->slug, 'integration' => $integration->id]), [
            'name' => 'API renommée',
            'type' => 'generic_http',
            'credentials' => $replacement,
        ])
        ->assertNoContent();

    expect($integration->fresh()->credentials)->toBe($replacement);
});

test('destroying an integration hard deletes the row', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $integration->id]));

    $response->assertRedirect(route('integrations.index', ['current_team' => $team->slug]));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Integration deleted.')]);

    $this->assertDatabaseCount('integrations', 0);
});

test('the integrations index exposes summaries without any credentials', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);
    $integration = Integration::factory()->for($team)->genericHttp()->withCredentials([
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'bearer',
        'token' => 'super-secret-value',
    ])->testSucceeded()->create(['name' => 'API publique']);

    $response = $this
        ->actingAs($user)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Integrations')
            ->has('integrations', 1)
            ->has('integrations.0', fn (Assert $summary) => $summary
                ->where('id', $integration->id)
                ->where('name', 'API publique')
                ->where('type', 'generic_http')
                ->has('lastTestedAt')
                ->where('lastTestSucceeded', true)
                ->where('usedByWorkflows', 0)
                ->where('meta', 'https://api.exemple.com')
                ->missing('credentials')
            )
            ->has('permissions.canCreateIntegration')
            ->has('permissions.canUpdateIntegration')
            ->has('permissions.canDeleteIntegration')
        );
});

test('the index counts the workflows using each integration', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);
    $used = Integration::factory()->for($team)->genericHttp()->create(['name' => 'A utilisée']);
    $unused = Integration::factory()->for($team)->smtp()->create(['name' => 'B inutilisée']);

    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('action.http')->withConfig(['integration_id' => (string) $used->id])->create();
    $otherWorkflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($otherWorkflow)->ofType('action.http')->withConfig(['integration_id' => (string) $used->id])->create();

    $response = $this
        ->actingAs($user)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('integrations.0.usedByWorkflows', 2)
            ->where('integrations.1.usedByWorkflows', 0)
        );
});

test('an integration of another team is not reachable through this team', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $otherTeam = Team::factory()->create();
    $foreign = Integration::factory()->for($otherTeam)->genericHttp()->create();

    $this
        ->actingAs($user)
        ->patchJson(route('integrations.update', ['current_team' => $team->slug, 'integration' => $foreign->id]), [
            'name' => 'Piratage',
            'type' => 'generic_http',
            'credentials' => ['baseUrl' => 'https://api.exemple.com', 'auth' => 'none'],
        ])
        ->assertNotFound();

    $this
        ->actingAs($user)
        ->deleteJson(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $foreign->id]))
        ->assertNotFound();
});

test('the summary meta exposes only non-secret connection data', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);

    [$user, $team] = teamWithMember(TeamRole::Member);
    Integration::factory()->for($team)->genericHttp()->withCredentials([
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'bearer',
        'token' => 'secret-token-value',
    ])->create(['name' => 'A http']);
    Integration::factory()->for($team)->smtp()->withCredentials([
        'host' => 'smtp.exemple.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'secret-user',
        'password' => 'secret-password-value',
    ])->create(['name' => 'B smtp']);
    Integration::factory()->for($team)->genericHttp()->withCredentials([
        'auth' => 'none',
    ])->create(['name' => 'C sans baseUrl']);
    $longBaseUrl = 'https://api.exemple.com/'.str_repeat('long', 60);
    Integration::factory()->for($team)->genericHttp()->withCredentials([
        'baseUrl' => $longBaseUrl,
    ])->create(['name' => 'D trop longue']);

    $response = $this
        ->actingAs($user)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('integrations.0.meta', 'https://api.exemple.com')
            ->where('integrations.1.meta', 'smtp.exemple.com:465')
            ->where('integrations.2.meta', null)
            ->where('integrations.3.meta', mb_substr($longBaseUrl, 0, 120))
        );
});
