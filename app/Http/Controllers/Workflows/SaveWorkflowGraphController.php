<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\SaveWorkflowGraph;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\SaveWorkflowGraphRequest;
use App\Models\Team;
use App\Models\Workflow;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;

class SaveWorkflowGraphController extends Controller
{
    /**
     * Replace the whole graph of the workflow.
     */
    public function __invoke(SaveWorkflowGraphRequest $request, Team $currentTeam, string $workflow, SaveWorkflowGraph $saveWorkflowGraph): HttpResponse
    {
        $model = $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();

        Gate::authorize('update', $model);

        $saveWorkflowGraph->handle($model, $request->input('nodes'), $request->input('edges'));

        return response()->noContent();
    }
}
