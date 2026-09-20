<?php

use App\Enums\TeamRole;
use App\Models\Integration;
use App\Services\Integration\ConnectionTester;
use App\Services\Integration\HttpClient;
use App\Services\Integration\SmtpTransportFactory;
use Illuminate\Support\Facades\Http;

test('a successful http connection test persists the test state', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->withCredentials([
        'baseUrl' => 'https://203.0.113.10',
        'auth' => 'none',
    ])->create();

    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10' => Http::response('ok', 200),
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('integrations.test', ['current_team' => $team->slug, 'integration' => $integration->id]));

    $response->assertOk()
        ->assertJson(['ok' => true]);

    $integration->refresh();

    expect($integration->last_test_succeeded)->toBeTrue()
        ->and($integration->last_tested_at)->not->toBeNull();
});

test('an authentication refusal reports a failed test without technical details', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->withCredentials([
        'baseUrl' => 'https://203.0.113.10',
        'auth' => 'none',
    ])->create();

    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10' => Http::response('denied', 401),
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('integrations.test', ['current_team' => $team->slug, 'integration' => $integration->id]));

    $response->assertOk()
        ->assertJson(['ok' => false])
        ->assertJsonPath('message', 'Connexion établie mais authentification refusée (statut 401).');

    $integration->refresh();

    expect($integration->last_test_succeeded)->toBeFalse()
        ->and($integration->last_tested_at)->not->toBeNull();
});

test('a failing smtp probe reports a failed test', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->smtp()->create();

    $tester = new ConnectionTester(
        new HttpClient,
        new SmtpTransportFactory,
        smtpProbe: fn (): never => throw new RuntimeException('Connection refused'),
    );
    $this->instance(ConnectionTester::class, $tester);

    $response = $this
        ->actingAs($user)
        ->postJson(route('integrations.test', ['current_team' => $team->slug, 'integration' => $integration->id]));

    $response->assertOk()
        ->assertJson(['ok' => false]);

    $integration->refresh();

    expect($integration->last_test_succeeded)->toBeFalse()
        ->and($integration->last_tested_at)->not->toBeNull();
});

test('a successful smtp probe reports a successful test', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->smtp()->create();

    $tester = new ConnectionTester(
        new HttpClient,
        new SmtpTransportFactory,
        smtpProbe: function (array $credentials): void {},
    );
    $this->instance(ConnectionTester::class, $tester);

    $response = $this
        ->actingAs($user)
        ->postJson(route('integrations.test', ['current_team' => $team->slug, 'integration' => $integration->id]));

    $response->assertOk()
        ->assertJson(['ok' => true, 'message' => 'Connexion SMTP établie.']);

    $integration->refresh();

    expect($integration->last_test_succeeded)->toBeTrue();
});

test('a member cannot test an integration connection', function () {
    [$member, $team] = teamWithMember(TeamRole::Member);
    $integration = Integration::factory()->for($team)->genericHttp()->create();

    $this
        ->actingAs($member)
        ->postJson(route('integrations.test', ['current_team' => $team->slug, 'integration' => $integration->id]))
        ->assertForbidden();
});
