<?php

use App\Enums\TeamRole;
use App\Jobs\RunWorkflowJob;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Support\Facades\Queue;

/**
 * Sign in a team member and return the pair.
 *
 * @return array{0: User, 1: Team}
 */
function runTeamMember(TeamRole $role = TeamRole::Owner): array
{
    [$user, $team] = teamWithMember($role);

    return [$user, $team];
}

test('running a workflow creates a pending execution and dispatches the job', function () {
    Queue::fake();

    [$user, $team] = runTeamMember();

    $workflow = Workflow::factory()->for($team)->active()->create();

    $response = $this->actingAs($user)
        ->post(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertRedirect();

    Queue::assertPushed(RunWorkflowJob::class, 1);

    $execution = WorkflowExecution::query()->sole();

    expect($execution->status->value)->toBe('pending')
        ->and($execution->triggered_by->value)->toBe('manual')
        ->and($execution->user?->is($user))->toBeTrue()
        ->and($execution->team->is($team))->toBeTrue();
});

test('running with an input payload stores it on the execution', function () {
    Queue::fake();

    [$user, $team] = runTeamMember();

    $workflow = Workflow::factory()->for($team)->active()->create();

    $this->actingAs($user)
        ->postJson(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => ['email' => 'person@example.com'],
        ])
        ->assertRedirect();

    expect(WorkflowExecution::query()->sole()->input)->toBe(['email' => 'person@example.com']);
});

test('running a draft workflow is refused with 422 and dispatches nothing', function () {
    Queue::fake();

    [$user, $team] = runTeamMember();

    $workflow = Workflow::factory()->for($team)->create(); // draft

    $response = $this->actingAs($user)
        ->postJson(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertStatus(422);

    Queue::assertNothingPushed();
    expect(WorkflowExecution::query()->count())->toBe(0);
});

test('a regular member can run a workflow (all roles hold the update permission)', function () {
    Queue::fake();

    [$owner, $team] = teamWithMember(TeamRole::Owner);
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $workflow = Workflow::factory()->for($team)->active()->create();

    $this->actingAs($member)
        ->post(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
        ->assertRedirect();

    Queue::assertPushed(RunWorkflowJob::class, 1);
    expect(WorkflowExecution::query()->where('user_id', $member->id)->exists())->toBeTrue();
});

test('a user of another team is forbidden to run a workflow of team A', function () {
    Queue::fake();

    [$owner, $teamA] = teamWithMember(TeamRole::Owner);
    $workflow = Workflow::factory()->for($teamA)->active()->create();

    $intruderTeam = Team::factory()->create();
    $intruder = User::factory()->create();
    $intruderTeam->members()->attach($intruder, ['role' => TeamRole::Owner->value]);

    $this->actingAs($intruder)
        ->post(route('workflows.run', ['current_team' => $teamA->slug, 'workflow' => $workflow->id]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('a workflow of another team cannot be run', function () {
    Queue::fake();

    [$user, $team] = runTeamMember();
    $foreign = Workflow::factory()->active()->create();

    $this->actingAs($user)
        ->post(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $foreign->id]))
        ->assertNotFound();

    Queue::assertNothingPushed();
});
