<?php

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
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
            ->missing('execution.result')
            ->has('execution.logs', 0)
            ->has('execution.error'));
});

test('the selected execution exposes its journal as a camelCase projection', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $execution = WorkflowExecution::factory()->for($workflow)->create();

    WorkflowExecutionLog::factory()->for($execution, 'execution')->create([
        'message' => 'Exécution démarrée (déclencheur : Manuel).',
        'offset_ms' => 0,
    ]);
    WorkflowExecutionLog::factory()->for($execution, 'execution')->okNode()->create([
        'node_key' => 'n1',
        'node_type' => 'trigger.manual',
        'node_name' => 'Manuel',
        'input' => ['email' => 'person@example.com'],
        'output' => ['ok' => true],
        'offset_ms' => 12,
    ]);
    WorkflowExecutionLog::factory()->for($execution, 'execution')->queued()->create([
        'node_key' => 'n2',
    ]);

    openExecutions($user, $team, ['execution' => $execution->id])
        ->assertInertia(fn ($page) => $page
            ->has('execution.logs', 3)
            ->where('execution.logs.0.kind', 'event')
            ->where('execution.logs.0.message', 'Exécution démarrée (déclencheur : Manuel).')
            ->where('execution.logs.0.offsetMs', 0)
            ->where('execution.logs.1.kind', 'node')
            ->where('execution.logs.1.nodeKey', 'n1')
            ->where('execution.logs.1.nodeType', 'trigger.manual')
            ->where('execution.logs.1.nodeName', 'Manuel')
            ->where('execution.logs.1.status', 'ok')
            ->where('execution.logs.1.level', 'ok')
            ->has('execution.logs.1.durationMs')
            ->where('execution.logs.1.input', ['email' => 'person@example.com'])
            ->where('execution.logs.1.output', ['ok' => true])
            ->where('execution.logs.1.offsetMs', 12)
            ->where('execution.logs.2.status', 'queued')
            ->missing('execution.result'));
});

test('the journal projection is ordered by row id', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $execution = WorkflowExecution::factory()->for($workflow)->create();

    $first = WorkflowExecutionLog::factory()->for($execution, 'execution')->create(['message' => 'Ligne 1']);
    $second = WorkflowExecutionLog::factory()->for($execution, 'execution')->create(['message' => 'Ligne 2']);

    openExecutions($user, $team, ['execution' => $execution->id])
        ->assertInertia(fn ($page) => $page
            ->where('execution.logs.0.id', $first->id)
            ->where('execution.logs.1.id', $second->id));
});

test('the history list items never carry logs or a result', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->create();

    openExecutions($user, $team)
        ->assertInertia(fn ($page) => $page
            ->missing('executions.data.0.logs')
            ->missing('executions.data.0.result'));
});

test('the page exposes the configured logs retention in days', function () {
    [$user, $team] = teamWithMember();

    config(['workflows.logs.retention_days' => 45]);

    openExecutions($user, $team)
        ->assertInertia(fn ($page) => $page
            ->where('logs_retention_days', 45));
});

test('a deep link to another team execution leaks nothing', function () {
    [$user, $team] = teamWithMember();
    $foreign = WorkflowExecution::factory()->completed()->create();

    openExecutions($user, $team, ['execution' => $foreign->id])
        ->assertInertia(fn ($page) => $page
            ->where('execution', null));
});
