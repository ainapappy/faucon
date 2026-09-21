<?php

use App\Models\Team;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the gallery lists system and current team templates, never foreign ones', function () {
    [$user, $team] = teamWithMember();
    $system = WorkflowTemplate::factory()->system()->create(['name' => 'SystemTpl', 'category' => 'Support', 'description' => 'Desc systeme']);
    $mine = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id, 'name' => 'TeamTpl', 'category' => 'Ventes']);
    $foreign = WorkflowTemplate::factory()->team()->create(['name' => 'ForeignTpl']);

    $response = $this->actingAs($user)
        ->get(route('templates.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/Index')
            ->has('templates', 2)
            ->where('templates.0.id', $system->id)
            ->where('templates.0.name', 'SystemTpl')
            ->where('templates.0.origin', 'system')
            ->where('templates.0.nodesCount', 2)
            ->where('templates.0.graph.nodes.0.key', 'trigger')
            ->where('templates.0.graph.nodes.0.positionX', 100)
            // Preview-only projection (phase 13): the configs and the edge
            // handles never leave the server.
            ->missing('templates.0.graph.nodes.0.config')
            ->missing('templates.0.graph.edges.0.sourceHandle')
            ->where('templates.1.id', $mine->id)
            ->where('templates.1.origin', 'team')
            ->missing('templates.2')
        );
});

test('the gallery props include nodeTypes and permissions', function () {
    [$user, $team] = teamWithMember();

    $this->actingAs($user)
        ->get(route('templates.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/Index')
            ->has('templates', 0)
            ->has('nodeTypes', 17)
            ->has('permissions.canCreateWorkflow')
        );
});

test('an ordered gallery puts seeded system templates first', function () {
    [$user, $team] = teamWithMember();
    $system = WorkflowTemplate::factory()->system()->create(['name' => 'SystemTpl']);
    $mine = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id, 'name' => 'TeamTpl']);

    $response = $this->actingAs($user)
        ->get(route('templates.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/Index')
            ->has('templates', 2)
            ->where('templates.0.id', $system->id)
            ->where('templates.1.id', $mine->id)
        );
});

test('a guest is redirected to the login from the gallery', function () {
    $team = Team::factory()->create();

    $this->get(route('templates.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});
