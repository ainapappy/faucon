<?php

namespace App\Services\Workflow;

use App\Models\WorkflowExecution;

/**
 * The single source of truth of the execution "list" projection (phase 10, D3):
 * extracted from WorkflowExecutionController::toListItem — external behavior
 * unchanged, both the executions history and the dashboard consume it.
 *
 * Static and pure: presentation mapping, not a service dependency.
 */
final class ExecutionPresenter
{
    /**
     * List row served to Inertia (camelCase) — shared by the executions
     * history and the dashboard.
     *
     * @return array{id: int, status: string, triggered_by: string, attempt: int, duration_ms: int|null, created_at: string, workflow: array{id: int, name: string}}
     */
    public static function listItem(WorkflowExecution $execution): array
    {
        return [
            'id' => $execution->id,
            'status' => $execution->status->value,
            'triggered_by' => $execution->triggered_by->value,
            'attempt' => $execution->attempt,
            'duration_ms' => $execution->duration_ms,
            'created_at' => $execution->created_at->toIso8601String(),
            'workflow' => [
                'id' => $execution->workflow->id,
                'name' => $execution->workflow->name,
            ],
        ];
    }
}
