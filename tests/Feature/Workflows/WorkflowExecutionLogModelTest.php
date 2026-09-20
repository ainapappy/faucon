<?php

use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
use Illuminate\Database\Eloquent\MassPrunable;

test('log kinds cover the two row families', function () {
    expect(array_map(fn (ExecutionLogKind $kind) => $kind->value, ExecutionLogKind::cases()))->toBe([
        'node', 'event',
    ]);
});

test('log levels cover the three severities', function () {
    expect(array_map(fn (ExecutionLogLevel $level) => $level->value, ExecutionLogLevel::cases()))->toBe([
        'info', 'ok', 'error',
    ]);
});

test('a log row belongs to its execution and casts its payloads', function () {
    $execution = WorkflowExecution::factory()->create();

    $log = WorkflowExecutionLog::factory()->for($execution, 'execution')->okNode()->create([
        'input' => ['email' => 'person@example.com'],
        'output' => ['id' => 7],
    ]);

    expect($log->execution->is($execution))->toBeTrue()
        ->and($log->kind)->toBe(ExecutionLogKind::Node)
        ->and($log->input)->toBe(['email' => 'person@example.com'])
        ->and($log->output)->toBe(['id' => 7])
        ->and($log->error)->toBeNull();
});

test('the execution logs relation is ordered chronologically by id', function () {
    $execution = WorkflowExecution::factory()->create();

    $first = WorkflowExecutionLog::factory()->for($execution, 'execution')->create(['message' => 'Première ligne']);
    $second = WorkflowExecutionLog::factory()->for($execution, 'execution')->create(['message' => 'Seconde ligne']);

    expect($execution->logs->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('the factory default is an info event row', function () {
    $log = WorkflowExecutionLog::factory()->create();

    expect($log->kind)->toBe(ExecutionLogKind::Event)
        ->and($log->level)->toBe(ExecutionLogLevel::Info)
        ->and($log->node_key)->toBeNull()
        ->and($log->status)->toBeNull()
        ->and($log->message)->toBeString()
        ->and($log->offset_ms)->toBeInt()
        ->and($log->attempt)->toBe(1);
});

test('the factory provides the four node states', function () {
    $queued = WorkflowExecutionLog::factory()->queued()->create();
    $ok = WorkflowExecutionLog::factory()->okNode()->create();
    $error = WorkflowExecutionLog::factory()->errorNode()->create();
    $skipped = WorkflowExecutionLog::factory()->skipped()->create();

    foreach ([$queued, $ok, $error, $skipped] as $row) {
        expect($row->kind)->toBe(ExecutionLogKind::Node)
            ->and($row->node_key)->toBeString();
    }

    expect($queued->status)->toBe('queued')
        ->and($queued->duration_ms)->toBeNull()
        ->and($ok->status)->toBe('ok')
        ->and($ok->level)->toBe(ExecutionLogLevel::Ok)
        ->and($ok->duration_ms)->toBeInt()
        ->and($ok->output)->toBeArray()
        ->and($error->status)->toBe('error')
        ->and($error->level)->toBe(ExecutionLogLevel::Error)
        ->and($error->error)->toBeArray()
        ->and($skipped->status)->toBe('skipped');
});

test('both models are mass-prunable', function () {
    expect(in_array(MassPrunable::class, class_uses_recursive(WorkflowExecutionLog::class)))->toBeTrue()
        ->and(in_array(MassPrunable::class, class_uses_recursive(WorkflowExecution::class)))->toBeTrue();
});

test('log pruning only targets rows older than the retention window', function () {
    config(['workflows.logs.retention_days' => 30]);

    $execution = WorkflowExecution::factory()->create();

    WorkflowExecutionLog::factory()->for($execution, 'execution')->count(2)->create(['created_at' => now()->subDays(31)]);
    WorkflowExecutionLog::factory()->for($execution, 'execution')->create(['created_at' => now()->subDays(5)]);

    expect((new WorkflowExecutionLog)->prunable()->count())->toBe(2);
});

test('execution pruning only targets final states older than their window', function () {
    config(['workflows.execution.retention_days' => 90]);

    $old = now()->subDays(91);

    WorkflowExecution::factory()->completed()->create(['created_at' => $old]);
    WorkflowExecution::factory()->failed()->create(['created_at' => $old]);
    WorkflowExecution::factory()->cancelled()->create(['created_at' => $old]);
    WorkflowExecution::factory()->running()->create(['created_at' => $old]);
    WorkflowExecution::factory()->create(['created_at' => $old]);
    WorkflowExecution::factory()->completed()->create(['created_at' => now()->subDays(10)]);

    expect((new WorkflowExecution)->prunable()->count())->toBe(3);
});

test('deleting an execution cascades to its logs', function () {
    $execution = WorkflowExecution::factory()->create();

    WorkflowExecutionLog::factory()->for($execution, 'execution')->count(3)->create();

    $execution->delete();

    expect(WorkflowExecutionLog::query()->where('workflow_execution_id', $execution->id)->count())->toBe(0);
});
