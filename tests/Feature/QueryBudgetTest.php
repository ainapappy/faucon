<?php

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Notifications\ExecutionFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Query-budget pins (phase 13, B1) — characterization tests that fail the
 * day an index page grows an N+1. Each count filters the query log the way
 * DashboardTest does: on the business table name, so framework noise (auth,
 * session, teams pivot) stays out of scope. Scaling the fixture (5 → 15
 * rows) inside the test is the anti-N+1 proof: the count must not grow.
 */
test('workflows.index stays within a bounded number of business queries', function () {
    [$user, $team] = teamWithMember();

    $workflows = Workflow::factory()->for($team)->count(5)->create();
    foreach (['trigger.manual', 'trigger.webhook', 'trigger.schedule'] as $index => $type) {
        WorkflowNode::factory()->for($workflows[$index])->ofType($type)->create();
    }

    DB::enableQueryLog();

    $this->actingAs($user)
        ->get(route('workflows.index', ['current_team' => $team->slug]))
        ->assertOk();

    $fiveWorkflows = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'workflow'))
        ->count();

    Workflow::factory()->for($team)->count(10)->create();

    DB::flushQueryLog();

    $this->actingAs($user)
        ->get(route('workflows.index', ['current_team' => $team->slug]))
        ->assertOk();

    $business = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'workflow'));

    DB::disableQueryLog();

    // Expected 3, at any volume: (a) the workflow list — withCount('nodes')
    // rides along as a scalar subselect of that same query, (b) the
    // triggerNode eager load (workflow_nodes), (c) the template categories
    // (workflow_templates). withCount and with never merge.
    expect($fiveWorkflows)->toBe(3)
        ->and($business)->toHaveCount(3);
});

test('workflow-executions.index stays within a bounded number of business queries', function () {
    [$user, $team] = teamWithMember();

    $workflows = Workflow::factory()->for($team)->count(3)->create();
    $selected = WorkflowExecution::factory()->for($workflows[2])->create();
    WorkflowExecution::factory()->for($workflows[0])->count(2)->create();
    WorkflowExecution::factory()->for($workflows[1])->count(2)->create();

    DB::enableQueryLog();

    $this->actingAs($user)
        ->get(route('workflow-executions.index', ['current_team' => $team->slug]))
        ->assertOk();

    $fiveExecutions = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'workflow'))
        ->count();

    WorkflowExecution::factory()->for($workflows[0])->count(5)->create();
    WorkflowExecution::factory()->for($workflows[1])->count(5)->create();

    DB::flushQueryLog();

    $this->actingAs($user)
        ->get(route('workflow-executions.index', ['current_team' => $team->slug]))
        ->assertOk();

    $plain = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'workflow'));

    DB::flushQueryLog();

    $this->actingAs($user)
        ->get(route('workflow-executions.index', ['current_team' => $team->slug, 'execution' => $selected->id]))
        ->assertOk();

    $deepLink = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'workflow'));

    DB::disableQueryLog();

    // Plain page — expected 4, at any volume: (a) the paginate() count
    // aggregate, (b) the page rows, (c) the eager `workflow:id,name` of the
    // page, (d) the team workflow filter options.
    //
    // Deep link — exactly +3: (e) the find on workflow_executions, (f) its
    // eager workflow, (g) its eager logs. The view policy's team lookup and
    // the shared bell feed never match the 'workflow' filter.
    expect($fiveExecutions)->toBe(4)
        ->and($plain)->toHaveCount(4)
        ->and($deepLink)->toHaveCount(7);
});

test('the shared notifications prop costs exactly two queries', function () {
    $user = User::factory()->create();
    $workflow = Workflow::factory()->create();
    $execution = WorkflowExecution::factory()->for($workflow)->failed()->create();

    foreach (range(1, 7) as $index) {
        $user->notify(new ExecutionFailedNotification($execution->refresh()));
    }

    DB::enableQueryLog();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertOk();

    $business = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'notification'));

    DB::disableQueryLog();

    // Expected 2, for any unread volume: the bell feed is one count plus
    // one take(5) get — never a per-notification query.
    expect($business)->toHaveCount(2);
});
