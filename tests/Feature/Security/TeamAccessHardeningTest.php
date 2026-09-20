<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the team edit page hides invitation codes from members who cannot cancel invitations', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);

    $invitation = TeamInvitation::factory()->for($team)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Edit')
            ->has('invitations', 1)
            ->where('invitations.0.id', $invitation->id)
            ->where('invitations.0.email', $invitation->email)
            ->where('invitations.0.role_label', TeamRole::Member->label())
            ->has('invitations.0.created_at')
            ->missing('invitations.0.code')
        );
});

test('the team edit page exposes invitation codes to members who can cancel invitations', function () {
    [$user, $team] = teamWithMember(TeamRole::Admin);

    $invitation = TeamInvitation::factory()->for($team)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Edit')
            ->has('invitations', 1)
            ->where('invitations.0.id', $invitation->id)
            ->where('invitations.0.code', $invitation->code)
            ->where('invitations.0.email', $invitation->email)
        );
});

test('the dashboard exposes invitation codes to the invited user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.id', $invitation->id)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.name', 'Laravel Team')
            ->where('pendingInvitations.0.team.slug', $team->slug)
        );
});

test('switching teams stays authorized by the team policy', function () {
    [$member, $team] = teamWithMember(TeamRole::Member);
    $outsider = User::factory()->create();
    $currentTeamBefore = $outsider->fresh()->current_team_id;

    $this
        ->actingAs($member)
        ->post(route('teams.switch', $team))
        ->assertRedirect();

    expect($member->fresh()->current_team_id)->toEqual($team->id);

    $this
        ->actingAs($outsider)
        ->post(route('teams.switch', $team))
        ->assertForbidden();

    expect($outsider->fresh()->current_team_id)->toEqual($currentTeamBefore);
});
