<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

test('members can view their team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->can('view', $team))->toBeTrue();
});

test('non members can not view a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->can('view', $team))->toBeFalse();
});

test('a non member can do nothing on a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $abilities = ['update', 'delete', 'addMember', 'updateMember', 'removeMember', 'inviteMember', 'cancelInvitation', 'leave'];

    foreach ($abilities as $ability) {
        expect($user->can($ability, $team))->toBeFalse("Non member should not have [{$ability}] ability.");
    }
});

test('team permissions follow the member role', function () {
    $team = Team::factory()->create();

    $abilities = ['update', 'delete', 'addMember', 'updateMember', 'removeMember', 'inviteMember', 'cancelInvitation'];

    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    foreach ($abilities as $ability) {
        expect($owner->can($ability, $team))->toBeTrue("Owner should have [{$ability}] ability.");
    }

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    foreach ($abilities as $ability) {
        expect($member->can($ability, $team))->toBeFalse("Member should not have [{$ability}] ability.");
    }
});

test('personal teams can not be deleted or left', function () {
    $owner = User::factory()->create();
    $personalTeam = Team::factory()->personal()->create();

    $personalTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    expect($owner->can('delete', $personalTeam))->toBeFalse()
        ->and($owner->can('leave', $personalTeam))->toBeFalse();

    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    expect($owner->can('delete', $team))->toBeTrue();
});

test('any authenticated user can view the teams index', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Team::class))->toBeTrue();
});
