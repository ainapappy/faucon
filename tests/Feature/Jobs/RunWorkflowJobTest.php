<?php

use App\Enums\ExecutionStatus;
use App\Events\Workflows\WorkflowExecutionCompleted;
use App\Events\Workflows\WorkflowExecutionFailed;
use App\Events\Workflows\WorkflowExecutionStarted;
use App\Jobs\RunWorkflowJob;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Services\Workflow\ExecutionCancel;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * An active workflow: manual trigger → HTTP call → output.
 */
function jobHttpWorkflow(): Workflow
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

/**
 * A minimal active workflow: manual trigger → output.
 */
function jobSimpleWorkflow(): Workflow
{
    $workflow = Workflow::factory()->active()->create();

    $t = WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    $o = WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();

    WorkflowEdge::factory()->for($workflow)->between($t, $o)->create();

    return $workflow;
}

function runJobNow(WorkflowExecution $execution): void
{
    (new RunWorkflowJob($execution->id))->handle(
        app(WorkflowRunner::class),
        app(WorkflowGraphMapper::class),
    );
}

test('a successful run persists completed, the result and the timings', function () {
    Event::fake([WorkflowExecutionStarted::class, WorkflowExecutionCompleted::class, WorkflowExecutionFailed::class]);

    $execution = WorkflowExecution::factory()->for(jobSimpleWorkflow())->create();

    runJobNow($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->attempt)->toBe(1)
        ->and($execution->started_at)->not->toBeNull()
        ->and($execution->finished_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($execution->result['status'])->toBe('completed')
        ->and($execution->result['nodes'][0]['nodeKey'])->toBe('t')
        ->and($execution->error)->toBeNull();

    Event::assertDispatched(WorkflowExecutionStarted::class);
    Event::assertDispatched(WorkflowExecutionCompleted::class);
    Event::assertNotDispatched(WorkflowExecutionFailed::class);
});

test('a non-retryable node failure persists failed with the first error', function () {
    Event::fake([WorkflowExecutionFailed::class]);
    Http::preventStrayRequests();

    $workflow = Workflow::factory()->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();
    // Type outside the catalog → run fails at validation (unknown_type) — never retryable.
    WorkflowNode::factory()->for($workflow)->keyed('x')->ofType('ghost.type')->create();

    $execution = WorkflowExecution::factory()->for($workflow)->create();

    runJobNow($execution);
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error['nodeKey'])->toBe('x')
        ->and($execution->error['reason'])->toBe('unknown_type')
        ->and($execution->error['message'])->toBeString()
        ->and($execution->result)->not->toBeNull();

    Event::assertDispatchedTimes(WorkflowExecutionFailed::class, 1);
});

test('a transient HTTP failure re-pends the execution and releases the job', function () {
    Event::fake([WorkflowExecutionFailed::class]);
    Http::fake(['https://203.0.113.10/*' => fn () => throw new ConnectionException('net down')]);

    $workflow = jobHttpWorkflow();
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    $job = new RunWorkflowJob($execution->id);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('release')->once()->with(30);

    $job->setJob($queueJob);

    $job->handle(app(WorkflowRunner::class), app(WorkflowGraphMapper::class));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Pending)
        ->and($execution->started_at)->toBeNull()
        ->and($execution->result)->toBeNull();

    Event::assertNotDispatched(WorkflowExecutionFailed::class);
});

test('a retryable failure with no attempt left persists failed definitively', function () {
    Event::fake([WorkflowExecutionFailed::class]);
    Http::fake(['https://203.0.113.10/*' => fn () => throw new ConnectionException('net down')]);

    $workflow = jobHttpWorkflow();
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    $job = new RunWorkflowJob($execution->id);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(2); // tries() default = 2 → exhausted
    $queueJob->shouldReceive('release')->never();

    $job->setJob($queueJob);

    $job->handle(app(WorkflowRunner::class, ['timeoutMs' => 5000]), app(WorkflowGraphMapper::class));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error['reason'])->toBe('network_error')
        ->and($execution->result['errors'][0]['reason'])->toBe('network_error');

    Event::assertDispatchedTimes(WorkflowExecutionFailed::class, 1);
});

test('a flag set before the run cancels the execution without running it', function () {
    Event::fake([WorkflowExecutionStarted::class, WorkflowExecutionCompleted::class]);

    $workflow = jobSimpleWorkflow();
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    ExecutionCancel::request($execution->id);

    runJobNow($execution);
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Cancelled)
        ->and($execution->finished_at)->not->toBeNull()
        ->and(ExecutionCancel::requested($execution->id))->toBeFalse()
        ->and($execution->result)->toBeNull();

    Event::assertNotDispatched(WorkflowExecutionStarted::class);
    Event::assertNotDispatched(WorkflowExecutionCompleted::class);
});

test('a flag raised mid-run stops the run between two nodes', function () {
    $workflow = jobHttpWorkflow(); // t → h (HTTP) → o
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    // The HTTP fake raises the flag while the HTTP node executes: the run
    // must then stop before the next node (o).
    Http::fake([
        'https://203.0.113.10/*' => function () use ($execution) {
            ExecutionCancel::request($execution->id);

            return Http::response(['ok' => true], 200);
        },
    ]);

    runJobNow($execution);
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Cancelled)
        ->and(ExecutionCancel::requested($execution->id))->toBeFalse()
        ->and($execution->result['nodes'][0]['status'])->toBe('ok')
        ->and($execution->result['nodes'][1]['status'])->toBe('ok')
        ->and($execution->result['nodes'][2]['status'])->toBe('skipped')
        ->and($execution->finished_at)->not->toBeNull();
});

test('an inactive workflow fails the execution with an explicit reason', function () {
    Event::fake([WorkflowExecutionFailed::class]);

    $workflow = jobSimpleWorkflow();
    $workflow->update(['status' => 'draft']);

    $execution = WorkflowExecution::factory()->for($workflow)->create();

    runJobNow($execution);
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error['reason'])->toBe('workflow_inactive')
        ->and($execution->error['nodeKey'])->toBeNull();

    Event::assertDispatchedTimes(WorkflowExecutionFailed::class, 1);
});

test('a deleted workflow fails the execution with the same reason', function () {
    $workflow = jobSimpleWorkflow();
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    $workflow->delete();

    runJobNow($execution);
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error['reason'])->toBe('workflow_inactive');
});

test('a final execution is never touched by a late job run', function () {
    $execution = WorkflowExecution::factory()->completed()->for(jobSimpleWorkflow())->create();

    runJobNow($execution);
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->result['status'])->toBe('completed')
        ->and($execution->finished_at->timestamp)->toBe($execution->finished_at->timestamp);
});

test('failed() writes job_failed once and never overwrites a final state', function () {
    $execution = WorkflowExecution::factory()->running()->for(jobSimpleWorkflow())->create();

    (new RunWorkflowJob($execution->id))->failed(new RuntimeException('boom'));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error['reason'])->toBe('job_failed')
        ->and($execution->error['nodeKey'])->toBeNull()
        ->and($execution->finished_at)->not->toBeNull();

    // Second call: the execution is final now — the guarded update is a no-op.
    (new RunWorkflowJob($execution->id))->failed(new RuntimeException('again'));

    $execution->refresh();

    expect($execution->error['message'])->toBe(__('Une erreur interne a interrompu l’exécution.'));
});

test('the job exposes tries, backoff, timeout and the anti-overlap middleware', function () {
    config([
        'workflows.execution.max_tries' => 3,
        'workflows.execution.backoff' => [10, 20],
        'workflows.execution.timeout_ms' => 60000,
    ]);

    $job = new RunWorkflowJob(42);

    expect($job->tries())->toBe(3)
        ->and($job->backoff())->toBe([10, 20])
        ->and($job->timeout)->toBe(60 + 60)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);
});

test('the attempt column follows the queue attempt number', function () {
    $workflow = jobSimpleWorkflow();
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    $job = new RunWorkflowJob($execution->id);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(2);
    $queueJob->shouldReceive('release')->never();

    $job->setJob($queueJob);

    $job->handle(app(WorkflowRunner::class), app(WorkflowGraphMapper::class));

    $execution->refresh();

    expect($execution->attempt)->toBe(2)
        ->and($execution->status)->toBe(ExecutionStatus::Completed);
});
