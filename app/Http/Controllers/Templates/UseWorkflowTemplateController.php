<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowTemplater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class UseWorkflowTemplateController extends Controller
{
    /**
     * Instantiate a template in the current team and open the builder on
     * the fresh draft. The resolution goes through visibleFor: a system OR
     * own-team template — never another team's (the Policy re-checks the
     * consistency of the template against the context team).
     */
    public function __invoke(Request $request, Team $currentTeam, string $template, WorkflowTemplater $templater): RedirectResponse
    {
        $model = WorkflowTemplate::query()
            ->visibleFor($currentTeam)
            ->whereKey($template)
            ->firstOrFail();

        Gate::authorize('use', [$model, $currentTeam]);

        $workflow = $templater->instantiate($model, $currentTeam, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow created from template.')]);

        return to_route('workflows.edit', ['current_team' => $currentTeam->slug, 'workflow' => $workflow->id]);
    }
}
