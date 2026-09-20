<?php

use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Carbon\CarbonImmutable;

test('execution statuses cover the five lifecycle states', function () {
    expect(array_map(fn (ExecutionStatus $status) => $status->value, ExecutionStatus::cases()))->toBe([
        'pending', 'running', 'completed', 'failed', 'cancelled',
    ]);
});

test('execution triggers cover the three launchers', function () {
    expect(array_map(fn (ExecutionTrigger $trigger) => $trigger->value, ExecutionTrigger::cases()))->toBe([
        'manual', 'webhook', 'schedule',
    ]);
});

test('an execution belongs to its workflow and inherits its team', function () {
    $workflow = Workflow::factory()->create();

    $execution = WorkflowExecution::factory()->for($workflow)->create();

    expect($execution->workflow->is($workflow))->toBeTrue()
        ->and($execution->team->is($workflow->team))->toBeTrue();
});

test('a manual execution may reference its initiator', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->create(['team_id' => $team->id]);

    $execution = WorkflowExecution::factory()->for($workflow)->create(['user_id' => $user->id]);

    expect($execution->user->is($user))->toBeTrue();
});

test('webhook and schedule executions have no initiator', function () {
    $webhook = WorkflowExecution::factory()->webhook()->create();
    $schedule = WorkflowExecution::factory()->schedule()->create();

    expect($webhook->user)->toBeNull()
        ->and($webhook->triggered_by)->toBe(ExecutionTrigger::Webhook)
        ->and($schedule->triggered_by)->toBe(ExecutionTrigger::Schedule);
});

test('the factory defaults to a pending manual execution', function () {
    $execution = WorkflowExecution::factory()->create();

    expect($execution->status)->toBe(ExecutionStatus::Pending)
        ->and($execution->triggered_by)->toBe(ExecutionTrigger::Manual)
        ->and($execution->attempt)->toBe(1)
        ->and($execution->started_at)->toBeNull()
        ->and($execution->finished_at)->toBeNull()
        ->and($execution->duration_ms)->toBeNull()
        ->and($execution->result)->toBeNull()
        ->and($execution->error)->toBeNull();
});

test('the factory provides a completed state with timings and result', function () {
    $execution = WorkflowExecution::factory()->completed()->create();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->started_at)->not->toBeNull()
        ->and($execution->finished_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeInt()
        ->and($execution->result['status'])->toBe('completed')
        ->and($execution->result['nodes'])->toBeArray()
        ->and($execution->result['errors'])->toBe([]);
});

test('the factory provides a failed state with an explicit error', function () {
    $execution = WorkflowExecution::factory()->failed()->create();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error['nodeKey'])->toBeString()
        ->and($execution->error['type'])->toBeString()
        ->and($execution->error['reason'])->toBeString()
        ->and($execution->error['message'])->toBeString()
        ->and($execution->result['errors'])->not->toBe([]);
});

test('the factory provides running and cancelled states', function () {
    $running = WorkflowExecution::factory()->running()->create();
    $cancelled = WorkflowExecution::factory()->cancelled()->create();

    expect($running->status)->toBe(ExecutionStatus::Running)
        ->and($running->started_at)->not->toBeNull()
        ->and($running->finished_at)->toBeNull()
        ->and($cancelled->status)->toBe(ExecutionStatus::Cancelled)
        ->and($cancelled->finished_at)->not->toBeNull()
        ->and($cancelled->error)->toBeNull();
});

test('final states are recognized', function () {
    expect(WorkflowExecution::factory()->create()->isFinal())->toBeFalse()
        ->and(WorkflowExecution::factory()->running()->create()->isFinal())->toBeFalse()
        ->and(WorkflowExecution::factory()->completed()->create()->isFinal())->toBeTrue()
        ->and(WorkflowExecution::factory()->failed()->create()->isFinal())->toBeTrue()
        ->and(WorkflowExecution::factory()->cancelled()->create()->isFinal())->toBeTrue();
});

test('json columns are cast to arrays and timings to dates', function () {
    $execution = WorkflowExecution::factory()->create([
        'input' => ['email' => 'person@example.com'],
    ]);

    expect($execution->input)->toBe(['email' => 'person@example.com'])
        ->and($execution->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($execution->started_at)->toBeNull();
});
