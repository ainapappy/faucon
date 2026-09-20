<?php

namespace App\Http\Controllers\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\PublishWorkflowTemplateRequest;
use App\Models\Team;
use App\Services\Workflow\Exception\TemplateNotPublishableException;
use App\Services\Workflow\WorkflowTemplater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PublishWorkflowTemplateController extends Controller
{
    /**
     * Publish a workflow of the current team as a team template. The
     * template creation is served as a plain POST (no server flash): the
     * front modal shows its own toast with the gallery link on success.
     * A non-executable graph redirects back with errors.graph.
     */
    public function __invoke(PublishWorkflowTemplateRequest $request, Team $currentTeam, string $workflow, WorkflowTemplater $templater): RedirectResponse
    {
        $model = $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();

        Gate::authorize('publish', $model);

        try {
            $templater->publish(
                $model,
                $request->user(),
                $request->validated('name'),
                $request->validated('description'),
                $request->validated('category'),
            );
        } catch (TemplateNotPublishableException $exception) {
            return back()->withErrors(['graph' => $exception->firstMessage()]);
        }

        return back();
    }
}
