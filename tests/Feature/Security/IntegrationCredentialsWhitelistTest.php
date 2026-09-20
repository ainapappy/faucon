<?php

use App\Enums\TeamRole;
use App\Models\Integration;

test('generic_http credentials are whitelisted per type on creation', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), [
            'name' => 'HTTP filtered',
            'type' => 'generic_http',
            'credentials' => [
                'baseUrl' => 'https://api.exemple.com',
                'auth' => 'bearer',
                'token' => 'keep-me',
                'host' => 'smtp.exemple.com',
                'arbitrary' => 'must-not-persist',
            ],
        ])
        ->assertRedirect();

    $integration = Integration::query()->where('name', 'HTTP filtered')->firstOrFail();

    expect($integration->credentials)->toBe([
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'bearer',
        'token' => 'keep-me',
    ]);
});

test('smtp credentials are whitelisted per type on creation', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $this
        ->actingAs($user)
        ->postJson(route('integrations.store', ['current_team' => $team->slug]), [
            'name' => 'SMTP filtered',
            'type' => 'smtp',
            'credentials' => [
                'host' => 'smtp.exemple.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'no-reply@exemple.com',
                'password' => 's3cret-value',
                'from' => 'no-reply@exemple.com',
                'dsn' => 'smtp://evil.example.com',
                'arbitrary' => 'must-not-persist',
            ],
        ])
        ->assertRedirect();

    $integration = Integration::query()->where('name', 'SMTP filtered')->firstOrFail();

    expect($integration->credentials)->toBe([
        'host' => 'smtp.exemple.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'no-reply@exemple.com',
        'password' => 's3cret-value',
        'from' => 'no-reply@exemple.com',
    ]);
});

test('unknown credential keys are dropped on update while valid ones are kept', function () {
    [$user, $team] = teamWithMember(TeamRole::Owner);

    $integration = Integration::factory()->for($team)->genericHttp()->create();

    $this
        ->actingAs($user)
        ->patchJson(route('integrations.update', ['current_team' => $team->slug, 'integration' => $integration->id]), [
            'name' => 'Updated filtered',
            'type' => 'generic_http',
            'credentials' => [
                'baseUrl' => 'https://api.exemple.com',
                'auth' => 'none',
                'arbitrary' => 'must-not-persist',
            ],
        ])
        ->assertNoContent();

    expect($integration->fresh()->credentials)->toBe([
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'none',
    ]);
});
