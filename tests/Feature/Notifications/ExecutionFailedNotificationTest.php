<?php

use App\Enums\ExecutionStatus;
use App\Enums\TeamRole;
use App\Events\Workflows\WorkflowExecutionFailed;
use App\Jobs\RunWorkflowJob;
use App\Listeners\Workflows\NotifyAuthorOfExecutionFailure;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Notifications\ExecutionFailedNotification;
use App\Services\Workflow\ExecutionCancel;
use App\Services\Workflow\Log\ExecutionLogWriter;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * An active workflow whose run fails immediately (node type outside the
 * catalog — non-retryable validation failure, no HTTP involved).
 */
function failingWorkflow(?Team $team = null): Workflow
{
    $workflow = Workflow::factory()->for($team ?? Team::factory())->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->keyed('x')->ofType('ghost.type')->create();

    return $workflow;
}

/**
 * An active workflow whose run fails on a transient HTTP error.
 */
function httpWorkflow(): Workflow
{
    $workflow = Workflow::factory()->active()->create();

    $t = WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    $h = WorkflowNode::factory()->for($workflow)->keyed('h')->ofType('action.http')->withConfig([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/relay',
    ])->create();
    $o = WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();

    WorkflowEdge::factory()->for($workflow)->between($t, $h)->create();
    WorkflowEdge::factory()->for($workflow)->between($h, $o)->create();

    return $workflow;
}

function runFailingJob(WorkflowExecution $execution): void
{
    (new RunWorkflowJob($execution->id))->handle(
        app(WorkflowRunner::class),
        app(WorkflowGraphMapper::class),
        app(ExecutionLogWriter::class),
    );
}

test('a failed manual run notifies its author only', function () {
    [$author, $team] = teamWithMember();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    $foreigner = User::factory()->create();

    $execution = WorkflowExecution::factory()->for(failingWorkflow())->create(['user_id' => $author->id]);

    runFailingJob($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($author->notifications()->count())->toBe(1)
        ->and($teammate->notifications()->count())->toBe(0)
        ->and($foreigner->notifications()->count())->toBe(0)
        ->and($author->notifications()->first()->type)->toBe(ExecutionFailedNotification::class);
});

test('a webhook run notifies nobody', function () {
    $execution = WorkflowExecution::factory()->for(failingWorkflow())->webhook()->create();

    runFailingJob($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and(databaseNotificationCount())->toBe(0);
});

test('a scheduled run notifies nobody', function () {
    $execution = WorkflowExecution::factory()->for(failingWorkflow())->schedule()->create();

    runFailingJob($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and(databaseNotificationCount())->toBe(0);
});

test('a completed run notifies nobody', function () {
    $workflow = Workflow::factory()->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();

    $execution = WorkflowExecution::factory()->for($workflow)->create(['user_id' => User::factory()->create()->id]);

    runFailingJob($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and(databaseNotificationCount())->toBe(0);
});

test('a cancelled run notifies nobody', function () {
    $workflow = Workflow::factory()->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();

    $execution = WorkflowExecution::factory()->for($workflow)->create(['user_id' => User::factory()->create()->id]);

    ExecutionCancel::request($execution->id);

    runFailingJob($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Cancelled)
        ->and(databaseNotificationCount())->toBe(0);
});

test('the notification carries the workflow, the team slug and the node error without any payload', function () {
    [$author, $team] = teamWithMember();

    $execution = WorkflowExecution::factory()->for(failingWorkflow($team))->failed()->create(['user_id' => $author->id]);

    WorkflowExecutionFailed::dispatch($execution->refresh());

    $notification = $author->notifications()->first();

    expect($notification->data)->toBe([
        'workflowId' => $execution->workflow_id,
        'workflowName' => $execution->workflow->name,
        'executionId' => $execution->id,
        'teamSlug' => $team->slug,
        'nodeKey' => $execution->error['nodeKey'],
        'nodeType' => $execution->error['type'],
        'reason' => $execution->error['reason'],
        'message' => $execution->error['message'],
    ]);
});

test('the notification message is truncated to 200 characters', function () {
    [$author] = teamWithMember();

    $longMessage = str_repeat('é', 320);

    $execution = WorkflowExecution::factory()->for(failingWorkflow())->failed()->create([
        'user_id' => $author->id,
        'error' => [
            'nodeKey' => 'n2',
            'type' => 'action.http',
            'reason' => 'http_request_failed',
            'message' => $longMessage,
        ],
    ]);

    WorkflowExecutionFailed::dispatch($execution->refresh());

    $notification = $author->notifications()->first();

    expect(mb_strlen($notification->data['message']))->toBe(200)
        ->and(mb_substr($notification->data['message'], 0, 10))->toBe(mb_substr($longMessage, 0, 10));
});

test('a definitive failure after exhausted retries notifies the author exactly once', function () {
    Http::fake(['https://203.0.113.10/*' => fn () => throw new ConnectionException('net down')]);

    [$author] = teamWithMember();

    $execution = WorkflowExecution::factory()->for(httpWorkflow())->create(['user_id' => $author->id]);

    $job = new RunWorkflowJob($execution->id);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(2); // tries() default = 2 → exhausted
    $queueJob->shouldReceive('release')->never();

    $job->setJob($queueJob);
    $job->handle(app(WorkflowRunner::class), app(WorkflowGraphMapper::class), app(ExecutionLogWriter::class));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and(databaseNotificationCount())->toBe(1)
        ->and($author->notifications()->count())->toBe(1);
});

test('a brutal job failure notifies the author of a manual run', function () {
    [$author] = teamWithMember();

    $workflow = Workflow::factory()->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();

    $execution = WorkflowExecution::factory()->for($workflow)->running()->create(['user_id' => $author->id]);

    (new RunWorkflowJob($execution->id))->failed(new RuntimeException('boom'));

    $execution->refresh();

    expect($execution->error['reason'])->toBe('job_failed')
        ->and(databaseNotificationCount())->toBe(1)
        ->and($author->notifications()->first()->data['reason'])->toBe('job_failed');
});

test('a brutal job failure never notifies for authorless runs', function () {
    $execution = WorkflowExecution::factory()->for(failingWorkflow())->webhook()->running()->create();

    (new RunWorkflowJob($execution->id))->failed(new RuntimeException('boom'));

    $execution->refresh();

    expect($execution->error['reason'])->toBe('job_failed')
        ->and(databaseNotificationCount())->toBe(0);
});

test('dispatching the failure event reaches the author through the registered listener', function () {
    [$author] = teamWithMember();

    $execution = WorkflowExecution::factory()->for(failingWorkflow())->create(['user_id' => $author->id]);

    WorkflowExecutionFailed::dispatch($execution->refresh());

    expect($author->notifications()->count())->toBe(1);
});

test('a notification failure is reported and never propagated', function () {
    Exceptions::fake();

    $author = Mockery::mock(User::class);
    $author->shouldReceive('notify')->once()->andThrow(new RuntimeException('storage full'));

    $execution = Mockery::mock(WorkflowExecution::class);
    $execution->shouldReceive('getAttribute')->with('user')->andReturn($author);

    (new NotifyAuthorOfExecutionFailure)->handle(new WorkflowExecutionFailed($execution));

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'storage full');
});

/**
 * Total number of notifications stored in the database (all users).
 */
function databaseNotificationCount(): int
{
    return DatabaseNotification::query()->count();
}
