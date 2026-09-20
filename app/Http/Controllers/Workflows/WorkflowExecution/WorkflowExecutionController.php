<?php

namespace App\Http\Controllers\Workflows\WorkflowExecution;

use App\Enums\ExecutionStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The executions history of the team (phase 7, maquette executions.html).
 *
 * The detail is a SHEET on this page (decision A1): the `execution` prop
 * carries the execution selected via `?execution={id}` and is the only prop
 * the client polls while it is pending or running.
 */
final class WorkflowExecutionController extends Controller
{
    /**
     * Executions per page for the history.
     */
    private const PerPage = 15;

    /**
     * List the team executions with filters and the selected one, if any.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [WorkflowExecution::class, $currentTeam]);

        $workflowId = $request->integer('workflow_id') ?: null;
        $status = $this->statusFilter($request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $executions = WorkflowExecution::query()
            ->where('team_id', $currentTeam->id)
            ->with('workflow:id,name')
            ->when($workflowId !== null, fn ($query) => $query->where('workflow_id', $workflowId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $this->applySearch($query, $search))
            ->latest()
            ->paginate(self::PerPage)
            ->withQueryString();

        return Inertia::render('workflows/executions/Index', [
            'executions' => $executions->through(
                fn (WorkflowExecution $execution) => $this->toListItem($execution),
            ),
            'execution' => $this->selectedExecution($request, $currentTeam),
            'workflows' => $this->workflowOptions($currentTeam),
            'filters' => [
                'workflow_id' => $workflowId,
                'status' => $status?->value,
                'q' => $search,
            ],
            'permissions' => $request->user()->toTeamPermissions($currentTeam),
        ]);
    }

    /**
     * Map a persisted execution to its list row.
     *
     * @return array<string, mixed>
     */
    private function toListItem(WorkflowExecution $execution): array
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

    /**
     * The execution selected through ?execution= — null when absent, not
     * found in this team, or not viewable (no leak through deep links).
     *
     * @return array<string, mixed>|null
     */
    private function selectedExecution(Request $request, Team $currentTeam): ?array
    {
        $id = $request->integer('execution');

        if ($id === 0) {
            return null;
        }

        $execution = WorkflowExecution::query()
            ->where('team_id', $currentTeam->id)
            ->with('workflow:id,name')
            ->find($id);

        if ($execution === null) {
            return null;
        }

        Gate::authorize('view', $execution);

        return array_merge($this->toListItem($execution), [
            'input' => $execution->input,
            'result' => $execution->result,
            'error' => $execution->error,
            'started_at' => $execution->started_at?->toIso8601String(),
            'finished_at' => $execution->finished_at?->toIso8601String(),
        ]);
    }

    /**
     * The workflow select options of the team (maquette filter).
     *
     * @return list<array{id: int, name: string}>
     */
    private function workflowOptions(Team $currentTeam): array
    {
        $options = [];

        foreach ($currentTeam->workflows()->orderBy('name')->get(['id', 'name']) as $workflow) {
            $options[] = ['id' => $workflow->id, 'name' => $workflow->name];
        }

        return $options;
    }

    /**
     * The validated status filter, null when empty, null+error otherwise —
     * an unknown value simply filters nothing out (defensive).
     */
    private function statusFilter(string $raw): ?ExecutionStatus
    {
        if ($raw === '') {
            return null;
        }

        $status = ExecutionStatus::tryFrom($raw);

        return $status;
    }

    /**
     * Search by execution id or workflow name.
     *
     * @param  Builder<WorkflowExecution>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        if (ctype_digit($search)) {
            $query->where(function ($inner) use ($search): void {
                $inner->whereKey((int) $search)
                    ->orWhereHas('workflow', fn ($workflow) => $workflow->where('name', 'like', "%{$search}%"));
            });

            return;
        }

        $query->whereHas('workflow', fn ($workflow) => $workflow->where('name', 'like', "%{$search}%"));
    }
}
