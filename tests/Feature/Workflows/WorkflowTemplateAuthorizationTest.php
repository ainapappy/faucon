<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowTemplate;

test('a system template is viewable by any authenticated user', function () {
    $template = WorkflowTemplate::factory()->system()->create();
    [$user] = teamWithMember(TeamRole::Member);

    expect($user->can('view', $template))->toBeTrue();
});

test('a team template is viewable by its owning team members only', function () {
    [$member, $team] = teamWithMember(TeamRole::Member);
    $template = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);

    $outsider = User::factory()->create();

    expect($member->can('view', $template))->toBeTrue()
        ->and($outsider->can('view', $template))->toBeFalse();
});

test('use requires the view plus the workflow create permission of the context team', function () {
    [$member, $team] = teamWithMember(TeamRole::Member);
    $system = WorkflowTemplate::factory()->system()->create();
    $own = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);
    $foreign = WorkflowTemplate::factory()->team()->create();

    expect($member->can('use', [$system, $team]))->toBeTrue()
        ->and($member->can('use', [$own, $team]))->toBeTrue()
        ->and($member->can('use', [$foreign, $team]))->toBeFalse();
});

test('use is denied to a user without the create permission of the context team', function () {
    $template = WorkflowTemplate::factory()->system()->create();

    $outsider = User::factory()->create();
    $someTeam = Team::factory()->create();

    expect($outsider->can('use', [$template, $someTeam]))->toBeFalse();
});

test('delete follows the delete permission of the owning team and never touches system templates', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $own = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);
    $system = WorkflowTemplate::factory()->system()->create();
    $foreign = WorkflowTemplate::factory()->team()->create();

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($owner->can('delete', $own))->toBeTrue()
        ->and($admin->can('delete', $own))->toBeTrue()
        ->and($member->can('delete', $own))->toBeFalse()
        ->and($owner->can('delete', $system))->toBeFalse()
        ->and($owner->can('delete', $foreign))->toBeFalse();
});

test('publish follows the update permission of the owning team', function () {
    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($team)->create();

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $outsider = User::factory()->create();

    expect($owner->can('publish', $workflow))->toBeTrue()
        ->and($admin->can('publish', $workflow))->toBeTrue()
        ->and($member->can('publish', $workflow))->toBeTrue()
        ->and($outsider->can('publish', $workflow))->toBeFalse();
});
