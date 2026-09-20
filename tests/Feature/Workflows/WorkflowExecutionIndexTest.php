<?php

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Testing\TestResponse;

/**
 * Sign in as the team owner and open the executions page.
 */
function openExecutions(User $user, mixed $team, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->get(route('workflow-executions.index', ['current_team' => $team->slug] + $query));
}

test('guests are redirected to the login page', function () {
    [$user, $team] = teamWithMember();

    $this->get(route('workflow-executions.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('the history is paginated by fifteen per page', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->count(20)->for($workflow)->create();

    $response = openExecutions($user, $team);

    $response->assertInertia(
        fn ($page) => $page
            // Component assertion intentionally omitted: the page belongs to
            // the front lot — this test pins the server-side contract.
            // The paginator is serialized flat (data + total), not wrapped in meta.
            ->has('executions.data', 15)
            ->where('executions.total', 20)
            ->etc(),
    );
});

test('the workflow filter narrows the history to one workflow', function () {
    [$user, $team] = teamWithMember();
    $workflowA = Workflow::factory()->for($team)->create();
    $workflowB = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->count(3)->for($workflowA)->create();
    WorkflowExecution::factory()->count(2)->for($workflowB)->create();

    openExecutions($user, $team, ['workflow_id' => $workflowB->id])
        ->assertInertia(
            fn ($page) => $page
                ->has('executions.data', 2)
                ->has('filters.workflow_id'),
        );
});

test('the status filter narrows the history', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->count(2)->completed()->for($workflow)->create();
    WorkflowExecution::factory()->failed()->for($workflow)->create();

    openExecutions($user, $team, ['status' => 'failed'])
        ->assertInertia(
            fn ($page) => $page->has('executions.data', 1),
        );
});

test('the search matches the workflow name or an execution id', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['name' => 'Relay Zot']);
    $other = Workflow::factory()->for($team)->create(['name' => 'Ingestion']);

    $byName = WorkflowExecution::factory()->for($workflow)->create();
    $byId = WorkflowExecution::factory()->for($other)->create();

    openExecutions($user, $team, ['q' => 'zot'])
        ->assertInertia(fn ($page) => $page->has('executions.data', 1)
            ->where('executions.data.0.id', $byName->id));

    openExecutions($user, $team, ['q' => (string) $byId->id])
        ->assertInertia(fn ($page) => $page->has('executions.data', 1)
            ->where('executions.data.0.id', $byId->id));
});

test('the selected execution deep link carries the full detail', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $execution = WorkflowExecution::factory()->for($workflow)->failed()->create();

    openExecutions($user, $team, ['execution' => $execution->id])
        ->assertInertia(fn ($page) => $page
            ->has('execution.id')
            ->where('execution.id', $execution->id)
            ->where('execution.status', 'failed')
            ->has('execution.result')
            ->has('execution.error'));
});
