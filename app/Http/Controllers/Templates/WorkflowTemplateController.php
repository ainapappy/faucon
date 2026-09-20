<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\NodeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowTemplateController extends Controller
{
    /**
     * Display the template gallery: the system templates plus the current
     * team's own — never another team's. Order by id: the seed order is the
     * display order (featured card = first system template).
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [WorkflowTemplate::class, $currentTeam]);

        $templates = WorkflowTemplate::query()
            ->visibleFor($currentTeam)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'origin' => $template->origin->value,
                'nodesCount' => count($template->graph['nodes'] ?? []),
                'graph' => $template->graph,
            ])
            ->all();

        return Inertia::render('templates/Index', [
            'templates' => $templates,
            'nodeTypes' => NodeCatalog::all(),
            'permissions' => $request->user()->toTeamPermissions($currentTeam),
        ]);
    }
}
