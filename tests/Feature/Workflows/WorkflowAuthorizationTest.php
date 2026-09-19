<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;

test('guests are redirected to the login page when opening the builder', function () {
    [$owner, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this
        ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertRedirect(route('login'));
});

test('guests receive an unauthorized JSON response when saving a graph', function () {
    [$owner, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload())
        ->assertUnauthorized();
});

test('a member of another team is forbidden on the workflows of team A', function () {
    [$owner, $teamA] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($teamA)->create();

    $intruderTeam = Team::factory()->create();
    $intruder = User::factory()->create();
    $intruderTeam->members()->attach($intruder, ['role' => TeamRole::Owner->value]);

    $this
        ->actingAs($intruder)
        ->get(route('workflows.edit', ['current_team' => $teamA->slug, 'workflow' => $workflow->id]))
        ->assertForbidden();
});

test('a member of the current team gets 404 on the id of another team workflow', function () {
    [$user, $team] = teamWithMember();
    $foreign = Workflow::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $foreign->id]))
        ->assertNotFound();

    $this
        ->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $foreign->id]), graphPayload(
            nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
        ))
        ->assertNotFound();
});

test('a member can save the graph but can not delete the workflow', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $workflow = Workflow::factory()->for($team)->create();

    $this
        ->actingAs($member)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [['key' => 'n1', 'type' => 'trigger.manual']],
        ))
        ->assertNoContent();

    $this
        ->actingAs($member)
        ->deleteJson(route('workflows.destroy', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertForbidden();

    $this->assertDatabaseHas('workflows', ['id' => $workflow->id, 'deleted_at' => null]);
});

test('an admin can delete the workflow', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $workflow = Workflow::factory()->for($team)->create();

    $this
        ->actingAs($admin)
        ->deleteJson(route('workflows.destroy', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertRedirect(route('workflows.index', ['current_team' => $team->slug]));

    $this->assertSoftDeleted($workflow);
});

test('a member can not duplicate a workflow they can not even view', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $foreign = Workflow::factory()->create();

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($member)
        ->postJson(route('workflows.duplicate', ['current_team' => $team->slug, 'workflow' => $foreign->id]))
        ->assertNotFound();
});
