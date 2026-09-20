<?php

use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
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
use App\Services\Workflow\Log\ExecutionLogWriter;
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
        app(ExecutionLogWriter::class),
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

    $job->handle(app(WorkflowRunner::class), app(WorkflowGraphMapper::class), app(ExecutionLogWriter::class));

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

    $job->handle(app(WorkflowRunner::class, ['timeoutMs' => 5000]), app(WorkflowGraphMapper::class), app(ExecutionLogWriter::class));

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

    $job->handle(app(WorkflowRunner::class), app(WorkflowGraphMapper::class), app(ExecutionLogWriter::class));

    $execution->refresh();

    expect($execution->attempt)->toBe(2)
        ->and($execution->status)->toBe(ExecutionStatus::Completed);
});

test('a successful run writes the timeline rows, the journal and a lightened result', function () {
    Event::fake([WorkflowExecutionStarted::class, WorkflowExecutionCompleted::class]);

    $execution = WorkflowExecution::factory()
        ->for(jobSimpleWorkflow())
        ->create(['input' => ['email' => 'client@example.com']]);

    runJobNow($execution);

    $execution->refresh();

    $events = $execution->logs()->where('kind', 'event')->orderBy('id')->get();
    $nodes = $execution->logs()->where('kind', 'node')->orderBy('id')->get()->keyBy('node_key');

    expect($events->pluck('message')->all())->toBe([
        'Exécution démarrée (déclencheur : Manuel).',
        'Exécution terminée avec succès.',
    ])
        ->and($nodes->keys()->sort()->values()->all())->toBe(['o', 't'])
        ->and($nodes->map->status->sort()->values()->all())->toBe(['ok', 'ok'])
        ->and($nodes['t']->input)->toBe(['email' => 'client@example.com'])
        ->and($nodes['t']->output)->toBe(['email' => 'client@example.com'])
        ->and($nodes['o']->output)->toBe(['value' => ['email' => 'client@example.com']])
        ->and($execution->result['status'])->toBe('completed')
        ->and($execution->result['nodes'][0]['nodeKey'])->toBe('t')
        ->and($execution->result['nodes'][0])->not->toHaveKey('output')
        ->and($execution->result['nodes'][0])->not->toHaveKey('input')
        ->and($execution->result['errors'])->toBe([]);

    Event::assertDispatched(WorkflowExecutionCompleted::class);
});

test('a failing node writes the error row and the failure journal line', function () {
    Event::fake([WorkflowExecutionFailed::class]);
    Http::preventStrayRequests();
    Http::fake(['https://203.0.113.10/*' => Http::response(['err' => true], 500)]);

    $execution = WorkflowExecution::factory()->for(jobHttpWorkflow())->create();

    runJobNow($execution);
    $execution->refresh();

    $httpRow = $execution->logs()->where('node_key', 'h')->sole();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($httpRow->status)->toBe('error')
        ->and($httpRow->level)->toBe(ExecutionLogLevel::Error)
        ->and($httpRow->message)->toBe('Le node a échoué : La requête HTTP a échoué (statut 500).')
        ->and($httpRow->error['reason'])->toBe('http_request_failed')
        ->and($execution->logs()->where('kind', 'event')->orderBy('id')->get()->last()->message)
        ->toBe('Exécution échouée : La requête HTTP a échoué (statut 500).');

    Event::assertDispatchedTimes(WorkflowExecutionFailed::class, 1);
});

test('a retry keeps the first-attempt rows and writes the retry journal line', function () {
    Event::fake([WorkflowExecutionFailed::class]);
    Http::fake(['https://203.0.113.10/*' => fn () => throw new ConnectionException('net down')]);

    $execution = WorkflowExecution::factory()->for(jobHttpWorkflow())->create();

    $job = new RunWorkflowJob($execution->id);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('release')->once()->with(30);

    $job->setJob($queueJob);

    $job->handle(app(WorkflowRunner::class), app(WorkflowGraphMapper::class), app(ExecutionLogWriter::class));

    $execution->refresh();

    $nodes = $execution->logs()->where('kind', 'node')->orderBy('id')->get()->keyBy('node_key');
    $events = $execution->logs()->where('kind', 'event')->orderBy('id')->get();

    expect($nodes->map->status->sort()->values()->all())->toBe(['error', 'ok', 'skipped'])
        ->and($nodes->map->attempt->unique()->values()->all())->toBe([1])
        ->and($events->pluck('message')->all())->toBe([
            'Exécution démarrée (déclencheur : Manuel).',
            'Retry programmé (tentative 2/2 dans 30 s).',
        ])
        ->and($events->last()->level)->toBe(ExecutionLogLevel::Info)
        ->and($execution->result)->toBeNull();
});

test('an inactive workflow writes only the failure journal line', function () {
    Event::fake([WorkflowExecutionFailed::class]);

    $workflow = jobSimpleWorkflow();
    $workflow->update(['status' => 'draft']);

    $execution = WorkflowExecution::factory()->for($workflow)->create();

    runJobNow($execution);
    $execution->refresh();

    $events = $execution->logs()->orderBy('id')->get();

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe(ExecutionLogKind::Event)
        ->and($events[0]->level)->toBe(ExecutionLogLevel::Error)
        ->and($events[0]->message)->toBe('Exécution échouée : Ce workflow n’est plus actif et ne peut pas être exécuté.')
        // No attempt start: the offset is computed from now() — sub-ms noise tolerated.
        ->and($events[0]->offset_ms)->toBeLessThan(100);
});

test('a cancel flag before the run writes only the cancelled journal line', function () {
    $workflow = jobSimpleWorkflow();
    $execution = WorkflowExecution::factory()->for($workflow)->create();

    ExecutionCancel::request($execution->id);

    runJobNow($execution);
    $execution->refresh();

    $events = $execution->logs()->orderBy('id')->get();

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe(ExecutionLogKind::Event)
        ->and($events[0]->level)->toBe(ExecutionLogLevel::Info)
        ->and($events[0]->message)->toBe('Exécution annulée.')
        // No attempt start: the offset is computed from now() — sub-ms noise tolerated.
        ->and($events[0]->offset_ms)->toBeLessThan(100)
        ->and($execution->status)->toBe(ExecutionStatus::Cancelled);
});

test('a cancel mid-run skips the remaining queued rows', function () {
    $execution = WorkflowExecution::factory()->for(jobHttpWorkflow())->create();

    Http::fake([
        'https://203.0.113.10/*' => function () use ($execution) {
            ExecutionCancel::request($execution->id);

            return Http::response(['ok' => true], 200);
        },
    ]);

    runJobNow($execution);
    $execution->refresh();

    $nodes = $execution->logs()->where('kind', 'node')->orderBy('id')->get()->keyBy('node_key');
    $events = $execution->logs()->where('kind', 'event')->orderBy('id')->get();

    expect($nodes->map->status->sort()->values()->all())->toBe(['ok', 'ok', 'skipped'])
        ->and($events->pluck('message')->all())->toBe([
            'Exécution démarrée (déclencheur : Manuel).',
            'Exécution annulée.',
        ])
        ->and($execution->status)->toBe(ExecutionStatus::Cancelled);
});

test('failed() closes the orphan queued rows and writes the failure once', function () {
    $execution = WorkflowExecution::factory()->running()->for(jobSimpleWorkflow())->create();

    // The attempt was already opened (rows pre-inserted) when the worker died.
    app(ExecutionLogWriter::class)->openAttempt($execution, 1, [
        ['key' => 't', 'type' => 'trigger.manual', 'name' => 'Manuel', 'config' => []],
        ['key' => 'o', 'type' => 'data.output', 'name' => 'Sortie', 'config' => []],
    ], now());

    (new RunWorkflowJob($execution->id))->failed(new RuntimeException('boom'));

    $nodes = $execution->logs()->where('kind', 'node')->orderBy('id')->get();
    $events = $execution->logs()->where('kind', 'event')->orderBy('id')->get();

    expect($nodes->pluck('status')->all())->toBe(['skipped', 'skipped'])
        ->and($events->pluck('message')->all())
        ->toBe(['Exécution échouée : Une erreur interne a interrompu l’exécution.']);

    // Second call: the execution is final — no additional event row.
    (new RunWorkflowJob($execution->id))->failed(new RuntimeException('again'));

    expect($execution->logs()->where('kind', 'event')->count())->toBe(1);
});

test('a webhook payload secret is redacted in the rows but execution input stays raw', function () {
    $workflow = Workflow::factory()->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.webhook')->create();
    WorkflowNode::factory()->for($workflow)->keyed('o')->ofType('data.output')->create();
    WorkflowEdge::factory()->for($workflow)->between(
        $workflow->nodes()->where('key', 't')->firstOrFail(),
        $workflow->nodes()->where('key', 'o')->firstOrFail(),
    )->create();

    $execution = WorkflowExecution::factory()
        ->for($workflow)
        ->webhook()
        ->create(['input' => ['email' => 'person@example.com', 'api_key' => 'sk_live_secret']]);

    runJobNow($execution);
    $execution->refresh();

    $triggerRow = $execution->logs()->where('node_key', 't')->sole();

    expect($triggerRow->input)->toBe(['email' => 'person@example.com', 'api_key' => '[masqué]'])
        ->and($execution->input)->toBe(['email' => 'person@example.com', 'api_key' => 'sk_live_secret']);
});
