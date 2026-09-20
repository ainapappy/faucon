<?php

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
use Illuminate\Support\Facades\Artisan;

test('model:prune removes old log rows and old final executions, keeps live ones', function () {
    config([
        'workflows.logs.retention_days' => 30,
        'workflows.execution.retention_days' => 90,
    ]);

    $workflow = Workflow::factory()->create();

    // A final execution older than 90 days: pruned, its logs cascaded away.
    $oldCompleted = WorkflowExecution::factory()->completed()->for($workflow)->create(['created_at' => now()->subDays(91)]);
    WorkflowExecutionLog::factory()->for($oldCompleted, 'execution')->count(2)->create(['created_at' => now()->subDays(90)]);

    // A recent final execution: its old log rows are pruned, it stays.
    $recent = WorkflowExecution::factory()->completed()->for($workflow)->create(['created_at' => now()->subDays(10)]);
    WorkflowExecutionLog::factory()->for($recent, 'execution')->count(2)->create(['created_at' => now()->subDays(31)]);
    WorkflowExecutionLog::factory()->for($recent, 'execution')->create(['created_at' => now()->subDays(5)]);

    // A pending and a running execution, however old, are NEVER pruned.
    $oldRunning = WorkflowExecution::factory()->running()->for($workflow)->create(['created_at' => now()->subDays(91)]);
    $oldPending = WorkflowExecution::factory()->for($workflow)->create(['created_at' => now()->subDays(91)]);

    Artisan::call('model:prune');

    expect(WorkflowExecution::query()->find($oldCompleted->id))->toBeNull()
        ->and(WorkflowExecutionLog::query()->where('workflow_execution_id', $oldCompleted->id)->count())->toBe(0)
        ->and(WorkflowExecutionLog::query()->where('created_at', '<=', now()->subDays(31))->count())->toBe(0)
        ->and(WorkflowExecutionLog::query()->where('created_at', '>=', now()->subDays(5))->count())->toBe(1)
        ->and(WorkflowExecution::query()->find($recent->id))->not->toBeNull()
        ->and(WorkflowExecution::query()->find($oldRunning->id))->not->toBeNull()
        ->and(WorkflowExecution::query()->find($oldPending->id))->not->toBeNull();
});
