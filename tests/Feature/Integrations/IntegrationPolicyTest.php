<?php

use App\Enums\TeamRole;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;

test('integration create follows the team role permissions', function () {
    [$owner, $ownerTeam] = teamWithMember(TeamRole::Owner);
    expect($owner->can('create', [Integration::class, $ownerTeam]))->toBeTrue('Owner should have [create] ability.');

    $adminTeam = Team::factory()->create();
    $admin = User::factory()->create();
    $adminTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    expect($admin->can('create', [Integration::class, $adminTeam]))->toBeTrue('Admin should have [create] ability.');

    $memberTeam = Team::factory()->create();
    $member = User::factory()->create();
    $memberTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
    expect($member->can('create', [Integration::class, $memberTeam]))->toBeFalse('Member should not have [create] ability.');
});

test('integration update follows the team role permissions', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->create();

    expect($owner->can('update', $integration))->toBeTrue('Owner should have [update] ability.');

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    expect($admin->can('update', $integration))->toBeTrue('Admin should have [update] ability.');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    expect($member->can('update', $integration))->toBeFalse('Member should not have [update] ability.');
});

test('integration delete follows the team role permissions', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->create();

    expect($owner->can('delete', $integration))->toBeTrue('Owner should have [delete] ability.');

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    expect($admin->can('delete', $integration))->toBeTrue('Admin should have [delete] ability.');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    expect($member->can('delete', $integration))->toBeFalse('Member should not have [delete] ability.');
});

test('integration test follows the update permission', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $integration = Integration::factory()->for($team)->genericHttp()->create();

    expect($owner->can('test', $integration))->toBeTrue('Owner should have [test] ability.');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    expect($member->can('test', $integration))->toBeFalse('Member should not have [test] ability.');
});

test('any team member can view an integration of their team', function () {
    [$member, $team] = teamWithMember(TeamRole::Member);
    $integration = Integration::factory()->for($team)->genericHttp()->create();

    expect($member->can('view', $integration))->toBeTrue('Member should have [view] ability.')
        ->and($member->can('viewAny', [Integration::class, $team]))->toBeTrue('Member should have [viewAny] ability.');
});

test('a non member can do nothing on a team integration', function () {
    $user = User::factory()->create();
    $integration = Integration::factory()->genericHttp()->create();
    $team = Team::factory()->create();

    foreach (['view', 'update', 'delete', 'test'] as $ability) {
        expect($user->can($ability, $integration))->toBeFalse("Non member should not have [{$ability}] ability.");
    }

    expect($user->can('viewAny', [Integration::class, $team]))->toBeFalse('Non member should not have [viewAny] ability.');
});
