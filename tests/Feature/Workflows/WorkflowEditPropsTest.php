<?php

use App\Enums\TeamRole;
use App\Models\Integration;
use App\Models\Workflow;
use Inertia\Testing\AssertableInertia as Assert;

test('the builder edit page exposes the team integrations without credentials', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);
    Integration::factory()->for($team)->genericHttp()->withCredentials([
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'bearer',
        'token' => 'super-secret-value',
    ])->create(['name' => 'API builder']);
    Integration::factory()->for($team)->smtp()->create(['name' => 'SMTP builder']);

    $workflow = Workflow::factory()->for($team)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflows/Edit')
            ->has('integrations', 2)
            ->where('integrations.0.name', 'API builder')
            ->where('integrations.0.meta', 'https://api.exemple.com')
            ->missing('integrations.0.credentials')
            ->missing('integrations.0.credentials.token')
            ->where('integrations.1.name', 'SMTP builder')
            ->where('integrations.1.meta', 'smtp.exemple.com:587')
            ->missing('integrations.1.credentials')
        );
});

test('the builder edit page exposes an empty integrations list without any integration', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);
    $workflow = Workflow::factory()->for($team)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflows/Edit')
            ->has('integrations', 0)
        );
});
