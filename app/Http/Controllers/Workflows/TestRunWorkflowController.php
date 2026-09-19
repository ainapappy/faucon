<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\TestRunWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\TestRunWorkflowRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TestRunWorkflowController extends Controller
{
    /**
     * Run the workflow with the sample input and always answer 200 with the
     * execution result (validation/execution failures are results, not HTTP
     * errors).
     */
    public function __invoke(TestRunWorkflowRequest $request, Team $currentTeam, string $workflow, TestRunWorkflow $testRunWorkflow): JsonResponse
    {
        $model = $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();

        Gate::authorize('update', $model);

        return response()->json(
            $testRunWorkflow->handle($model, $request->sampleInput())->toArray()
        );
    }
}
