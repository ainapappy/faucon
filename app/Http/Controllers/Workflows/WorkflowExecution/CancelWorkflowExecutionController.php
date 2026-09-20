<?php

namespace App\Http\Controllers\Workflows\WorkflowExecution;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WorkflowExecution;
use App\Services\Workflow\ExecutionCancel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Request the cancellation of a pending or running execution (phase 7).
 *
 * This endpoint NEVER writes the status: it sets the cache flag, the job
 * turns it into the persisted `cancelled` state (at the start of an attempt
 * or between two nodes). Cancelling an already-final execution answers an
 * explicit 200 — never an error.
 */
final class CancelWorkflowExecutionController extends Controller
{
    /**
     * Set the cancellation flag, or explain that it is too late.
     */
    public function __invoke(Team $currentTeam, string $execution): JsonResponse
    {
        $model = WorkflowExecution::query()
            ->where('team_id', $currentTeam->id)
            ->whereKey($execution)
            ->firstOrFail();

        Gate::authorize('cancel', $model);

        if ($model->isFinal()) {
            return response()->json([
                'status' => $model->status->value,
                'message' => __('Cette exécution est déjà terminée — rien à annuler.'),
            ]);
        }

        ExecutionCancel::request($model->id);

        return response()->json(['status' => 'cancelling']);
    }
}
