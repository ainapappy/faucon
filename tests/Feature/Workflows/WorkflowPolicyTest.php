<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;

test('team members can view a workflow of their team', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);

    $workflow = Workflow::factory()->for($team)->create();

    expect($user->can('view', $workflow))->toBeTrue();
});

test('non members can not view a workflow', function () {
    $user = User::factory()->create();
    $workflow = Workflow::factory()->create();

    expect($user->can('view', $workflow))->toBeFalse();
});

test('team members can view the workflows index of their team', function () {
    [$user, $team] = teamWithMember(TeamRole::Member);

    expect($user->can('viewAny', [Workflow::class, $team]))->toBeTrue();
});

test('non members can not view the workflows index of a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->can('viewAny', [Workflow::class, $team]))->toBeFalse();
});

test('a non member can do nothing on a team workflow', function () {
    $user = User::factory()->create();
    $workflow = Workflow::factory()->create();

    $abilities = ['view', 'update', 'delete', 'duplicate'];

    foreach ($abilities as $ability) {
        expect($user->can($ability, $workflow))->toBeFalse("Non member should not have [{$ability}] ability.");
    }
});

test('workflow create follows the team role permissions', function () {
    $abilities = ['create'];

    foreach ($abilities as $ability) {
        $ownerTeam = Team::factory()->create();
        $owner = User::factory()->create();
        $ownerTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        expect($owner->can($ability, [Workflow::class, $ownerTeam]))->toBeTrue("Owner should have [{$ability}] ability.");

        $adminTeam = Team::factory()->create();
        $admin = User::factory()->create();
        $adminTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        expect($admin->can($ability, [Workflow::class, $adminTeam]))->toBeTrue("Admin should have [{$ability}] ability.");

        $memberTeam = Team::factory()->create();
        $member = User::factory()->create();
        $memberTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        expect($member->can($ability, [Workflow::class, $memberTeam]))->toBeTrue("Member should have [{$ability}] ability.");
    }
});

test('workflow update follows the team role permissions', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($team)->create();

    expect($owner->can('update', $workflow))->toBeTrue('Owner should have [update] ability.');

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    expect($admin->can('update', $workflow))->toBeTrue('Admin should have [update] ability.');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($member->can('update', $workflow))->toBeTrue('Member should have [update] ability.');
});

test('workflow delete follows the team role permissions', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($team)->create();

    expect($owner->can('delete', $workflow))->toBeTrue('Owner should have [delete] ability.');

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    expect($admin->can('delete', $workflow))->toBeTrue('Admin should have [delete] ability.');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($member->can('delete', $workflow))->toBeFalse('Member should not have [delete] ability.');
});

test('workflow duplicate follows the create permission', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($team)->create();

    expect($owner->can('duplicate', $workflow))->toBeTrue('Owner should have [duplicate] ability.');

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    expect($admin->can('duplicate', $workflow))->toBeTrue('Admin should have [duplicate] ability.');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($member->can('duplicate', $workflow))->toBeTrue('Member should have [duplicate] ability.');
});

test('toTeamPermissions exposes the workflow booleans by role', function () {
    $ownerTeam = Team::factory()->create();
    $owner = User::factory()->create();
    $ownerTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $ownerPermissions = $owner->toTeamPermissions($ownerTeam);

    expect($ownerPermissions->canCreateWorkflow)->toBeTrue()
        ->and($ownerPermissions->canUpdateWorkflow)->toBeTrue()
        ->and($ownerPermissions->canDeleteWorkflow)->toBeTrue();

    $adminTeam = Team::factory()->create();
    $admin = User::factory()->create();
    $adminTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $adminPermissions = $admin->toTeamPermissions($adminTeam);

    expect($adminPermissions->canCreateWorkflow)->toBeTrue()
        ->and($adminPermissions->canUpdateWorkflow)->toBeTrue()
        ->and($adminPermissions->canDeleteWorkflow)->toBeTrue();

    $memberTeam = Team::factory()->create();
    $member = User::factory()->create();
    $memberTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
    $memberPermissions = $member->toTeamPermissions($memberTeam);

    expect($memberPermissions->canCreateWorkflow)->toBeTrue()
        ->and($memberPermissions->canUpdateWorkflow)->toBeTrue()
        ->and($memberPermissions->canDeleteWorkflow)->toBeFalse();
});

test('toTeamPermissions defaults to false without a role', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $permissions = $user->toTeamPermissions($team);

    expect($permissions->canCreateWorkflow)->toBeFalse()
        ->and($permissions->canUpdateWorkflow)->toBeFalse()
        ->and($permissions->canDeleteWorkflow)->toBeFalse();
});
