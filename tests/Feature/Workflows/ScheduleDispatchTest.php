<?php

use App\Actions\Workflows\DispatchScheduledWorkflows;
use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Jobs\RunWorkflowJob;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * An active workflow with a valid schedule trigger: every day at 09:00.
 */
function scheduledWorkflow(): Workflow
{
    $workflow = Workflow::factory()->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('s')->ofType('trigger.schedule')->withConfig([
        'cron' => '0 9 * * *',
    ])->create();
    $t = WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();
    WorkflowEdge::factory()->for($workflow)->between(
        $workflow->nodes()->where('key', 's')->firstOrFail(),
        $t,
    )->create();

    return $workflow;
}

test('a due scheduled workflow is dispatched at its cron minute', function () {
    Queue::fake();
    Carbon::setTestNow('2026-09-21 09:00:00');

    $workflow = scheduledWorkflow();

    $count = app(DispatchScheduledWorkflows::class)->handle();

    expect($count)->toBe(1);

    Queue::assertPushed(RunWorkflowJob::class, 1);

    Carbon::setTestNow();
});

test('outside the cron minute nothing is dispatched', function () {
    Queue::fake();
    Carbon::setTestNow('2026-09-21 09:01:00');

    scheduledWorkflow();

    expect(app(DispatchScheduledWorkflows::class)->handle())->toBe(0)
        ->and(Queue::assertPushed(RunWorkflowJob::class, 0));

    Carbon::setTestNow();
});

test('inactive or deleted workflows are never dispatched', function () {
    Queue::fake();
    Carbon::setTestNow('2026-09-21 09:00:00');

    $draft = Workflow::factory()->create();
    WorkflowNode::factory()->for($draft)->ofType('trigger.schedule')->withConfig(['cron' => '0 9 * * *'])->create();

    $deleted = Workflow::factory()->active()->create();
    WorkflowNode::factory()->for($deleted)->ofType('trigger.schedule')->withConfig(['cron' => '0 9 * * *'])->create();
    $deleted->delete();

    expect(app(DispatchScheduledWorkflows::class)->handle())->toBe(0);

    Carbon::setTestNow();
});

test('an invalid cron expression is skipped without crashing the entry', function () {
    Queue::fake();
    Carbon::setTestNow('2026-09-21 09:00:00');

    $workflow = Workflow::factory()->active()->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.schedule')->withConfig(['cron' => 'not-a-cron'])->create();

    expect(app(DispatchScheduledWorkflows::class)->handle())->toBe(0);

    Carbon::setTestNow();
});

test('a scheduled dispatch creates the pending execution for the right workflow', function () {
    Queue::fake();
    Carbon::setTestNow('2026-09-21 09:00:00');

    $workflow = scheduledWorkflow();

    expect(app(DispatchScheduledWorkflows::class)->handle())->toBe(1);

    $execution = WorkflowExecution::query()->where('workflow_id', $workflow->id)->sole();

    expect($execution->status)->toBe(ExecutionStatus::Pending)
        ->and($execution->triggered_by)->toBe(ExecutionTrigger::Schedule)
        ->and($execution->user_id)->toBeNull();

    Queue::assertPushed(RunWorkflowJob::class, fn (RunWorkflowJob $job) => $job->executionId === $execution->id);

    Carbon::setTestNow();
});

test('the queued scheduled run completes with the cron in the trigger output', function () {
    Carbon::setTestNow('2026-09-21 09:00:00');

    $workflow = scheduledWorkflow();

    $execution = WorkflowExecution::factory()->schedule()->for($workflow)->create();

    (new RunWorkflowJob($execution->id))->handle(
        app(WorkflowRunner::class),
        app(WorkflowGraphMapper::class),
    );

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->result['nodes'][0]['type'])->toBe('trigger.schedule')
        ->and($execution->result['nodes'][0]['output']['cron'])->toBe('0 9 * * *')
        ->and($execution->result['nodes'][0]['output']['triggered_at'])->toBeString();

    Carbon::setTestNow();
});

test('the scheduler entry is registered', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('Dispatch due scheduled workflow runs')
        ->assertSuccessful();
});
