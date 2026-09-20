<?php

use App\Enums\TeamRole;
use App\Jobs\RunWorkflowJob;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Services\Workflow\ExecutionCancel;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;

/**
 * A pending execution for a simple workflow, plus its endpoint params.
 *
 * @return array{0: WorkflowExecution, 1: User, 2: mixed}
 */
function cancelScenario(): array
{
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->active()->create();
    $t = WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    $o = WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();
    WorkflowEdge::factory()->for($workflow)->between($t, $o)->create();

    $execution = WorkflowExecution::factory()->for($workflow)->running()->create();

    return [$execution, $user, $team];
}

test('cancelling a running execution sets the flag and answers cancelling', function () {
    [$execution, $user, $team] = cancelScenario();

    $this->actingAs($user)
        ->postJson(route('workflow-executions.cancel', ['current_team' => $team->slug, 'execution' => $execution->id]))
        ->assertOk()
        ->assertJson(['status' => 'cancelling']);

    expect(ExecutionCancel::requested($execution->id))->toBeTrue();
});

test('cancelling a final execution answers explicitly without setting the flag', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->active()->create();
    $execution = WorkflowExecution::factory()->for($workflow)->completed()->create();

    $response = $this->actingAs($user)
        ->postJson(route('workflow-executions.cancel', ['execution' => $execution->id] + ['current_team' => $team->slug]));

    $response->assertOk();

    expect($response->json('status'))->toBe('completed')
        ->and($response->json('message'))->toBeString()
        ->and(ExecutionCancel::requested($execution->id))->toBeFalse();
});

test('the full loop: the endpoint flag turns the queued run into cancelled', function () {
    [$execution, $user, $team] = cancelScenario();

    $this->actingAs($user)
        ->postJson(route('workflow-executions.cancel', ['current_team' => $team->slug, 'execution' => $execution->id]))
        ->assertOk();

    // The worker picks the job up after the cancel request.
    (new RunWorkflowJob($execution->id))->handle(
        app(WorkflowRunner::class),
        app(WorkflowGraphMapper::class),
    );

    $execution->refresh();

    expect($execution->status->value)->toBe('cancelled')
        ->and(ExecutionCancel::requested($execution->id))->toBeFalse();
});

test('a user of another team is forbidden to cancel', function () {
    [$execution, $user, $team] = cancelScenario();

    $intruderTeam = Team::factory()->create();
    $intruder = User::factory()->create();
    $intruderTeam->members()->attach($intruder, ['role' => TeamRole::Owner->value]);

    $this->actingAs($intruder)
        ->postJson(route('workflow-executions.cancel', ['current_team' => $team->slug, 'execution' => $execution->id]))
        ->assertForbidden();

    expect(ExecutionCancel::requested($execution->id))->toBeFalse();
});

test('a team member gets 404 on a foreign execution id', function () {
    [$user, $team] = teamWithMember();

    $foreignExecution = WorkflowExecution::factory()->create();

    $this->actingAs($user)
        ->postJson(route('workflow-executions.cancel', ['current_team' => $team->slug, 'execution' => $foreignExecution->id]))
        ->assertNotFound();
});
