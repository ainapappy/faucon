<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\DuplicateWorkflow;
use App\Data\Workflow\NodeDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\CreateWorkflowRequest;
use App\Http\Requests\Workflows\UpdateWorkflowRequest;
use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\Workflow\NodeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    /**
     * Display the workflow list of the current team.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Workflow::class, $currentTeam]);

        $workflows = $currentTeam->workflows()
            ->withCount('nodes')
            ->with('triggerNode:workflow_id,type')
            ->latest()
            ->get()
            ->map(fn (Workflow $workflow) => $this->toListItem($workflow))
            ->all();

        return Inertia::render('workflows/Index', [
            'workflows' => $workflows,
            'nodeTypes' => $this->nodeTypes(),
            'permissions' => $request->user()->toTeamPermissions($currentTeam),
        ]);
    }

    /**
     * Store a newly created workflow.
     */
    public function store(CreateWorkflowRequest $request, Team $currentTeam, CreateWorkflow $createWorkflow): RedirectResponse
    {
        Gate::authorize('create', [Workflow::class, $currentTeam]);

        $workflow = $createWorkflow->handle(
            $request->user(),
            $currentTeam,
            $request->validated('name'),
            $request->validated('description'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow created.')]);

        return to_route('workflows.edit', ['current_team' => $currentTeam->slug, 'workflow' => $workflow->id]);
    }

    /**
     * Show the workflow builder.
     */
    public function edit(Request $request, Team $currentTeam, string $workflow): Response
    {
        $model = $this->findWorkflow($currentTeam, $workflow);

        Gate::authorize('view', $model);

        $model->load(['nodes', 'edges']);

        return Inertia::render('workflows/Edit', [
            'workflow' => [
                'id' => $model->id,
                'name' => $model->name,
                'description' => $model->description,
                'status' => $model->status->value,
            ],
            'graph' => [
                'nodes' => $model->nodes->map(fn (WorkflowNode $node) => [
                    'key' => $node->key,
                    'type' => $node->type,
                    'name' => $node->name,
                    'config' => $node->config ?? [],
                    'positionX' => $node->position_x,
                    'positionY' => $node->position_y,
                ])->all(),
                'edges' => $model->edges->map(fn (WorkflowEdge $edge) => [
                    'id' => $edge->id,
                    'sourceNodeKey' => $edge->source_node_key,
                    'targetNodeKey' => $edge->target_node_key,
                    'sourceHandle' => $edge->source_handle,
                ])->all(),
            ],
            'nodeTypes' => $this->nodeTypes(),
            'permissions' => $request->user()->toTeamPermissions($currentTeam),
        ]);
    }

    /**
     * Update the workflow metadata (name, description, status).
     */
    public function update(UpdateWorkflowRequest $request, Team $currentTeam, string $workflow): HttpResponse
    {
        $model = $this->findWorkflow($currentTeam, $workflow);

        Gate::authorize('update', $model);

        $model->fill($request->validated());
        $model->save();

        return response()->noContent();
    }

    /**
     * Remove the specified workflow (soft delete).
     */
    public function destroy(Team $currentTeam, string $workflow): RedirectResponse
    {
        $model = $this->findWorkflow($currentTeam, $workflow);

        Gate::authorize('delete', $model);

        $model->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow deleted.')]);

        return to_route('workflows.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * Duplicate the specified workflow as a draft copy.
     */
    public function duplicate(Request $request, Team $currentTeam, string $workflow, DuplicateWorkflow $duplicateWorkflow): RedirectResponse
    {
        $model = $this->findWorkflow($currentTeam, $workflow);

        Gate::authorize('duplicate', $model);

        $duplicateWorkflow->handle($request->user(), $model);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow duplicated.')]);

        return to_route('workflows.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * Find the workflow through the current team (never a global id).
     */
    private function findWorkflow(Team $currentTeam, string $workflow): Workflow
    {
        return $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();
    }

    /**
     * Map a workflow to the minimal list item shape (D5 payload).
     *
     * @return array{id: int, name: string, description: string|null, status: string, nodesCount: int, triggerType: string|null}
     */
    private function toListItem(Workflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'status' => $workflow->status->value,
            'nodesCount' => $workflow->nodes_count,
            'triggerType' => $workflow->triggerNode?->type,
        ];
    }

    /**
     * Get the node catalog as plain arrays for the Inertia props.
     *
     * @return array<string, NodeDefinition>
     */
    private function nodeTypes(): array
    {
        return NodeCatalog::all();
    }
}
