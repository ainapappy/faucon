<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\StartWorkflowRun;
use App\Enums\ExecutionTrigger;
use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\RunWorkflowRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Launch a queued run of a workflow from the UI (phase 7).
 *
 * The synchronous test-run keeps its own endpoint — this one never blocks
 * the request: it creates a pending execution, dispatches the job and
 * answers immediately with a toast carrying the deep link to the execution.
 */
final class RunWorkflowController extends Controller
{
    /**
     * Dispatch the run and redirect back with the execution link.
     */
    public function __invoke(RunWorkflowRequest $request, Team $currentTeam, string $workflow, StartWorkflowRun $startWorkflowRun): RedirectResponse
    {
        $model = $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();

        Gate::authorize('update', $model);

        if ($model->status !== WorkflowStatus::Active) {
            throw ValidationException::withMessages([
                'workflow' => __('Seul un workflow actif peut être exécuté.'),
            ]);
        }

        $execution = $startWorkflowRun->handle(
            $model,
            ExecutionTrigger::Manual,
            $request->runInput(),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Exécution :id lancée.', ['id' => $execution->id]),
            'href' => route('workflow-executions.index', [
                'current_team' => $currentTeam->slug,
                'execution' => $execution->id,
            ]),
        ]);

        return redirect()->back();
    }
}
