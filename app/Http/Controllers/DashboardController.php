<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\Workflow\DashboardMetrics;
use App\Services\Workflow\ExecutionPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * The recent-executions list size.
     */
    private const RecentExecutions = 8;

    /**
     * The recent-workflows list size.
     */
    private const RecentWorkflows = 6;

    public function __construct(private readonly DashboardMetrics $metrics) {}

    public function __invoke(Request $request, Team $currentTeam): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation): array => [
                'id' => $invitation->id,
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            // Preserved unchanged (D1): invitations of the visiting user.
            'pendingInvitations' => $pendingInvitations,
            'stats' => $this->metrics->stats($currentTeam),
            'recentExecutions' => $this->recentExecutions($currentTeam),
            'recentWorkflows' => $this->recentWorkflows($currentTeam),
            'analytics' => Inertia::defer(fn () => $this->metrics->analytics($currentTeam)),
            'permissions' => $request->user()->toTeamPermissions($currentTeam),
        ]);
    }

    /**
     * The 8 most recent team executions, projected exactly like the
     * executions history (D3).
     *
     * @return list<array<string, mixed>>
     */
    private function recentExecutions(Team $currentTeam): array
    {
        $executions = WorkflowExecution::query()
            ->where('team_id', $currentTeam->id)
            ->with('workflow:id,name')
            ->latest()
            ->limit(self::RecentExecutions)
            ->get();

        $rows = [];

        foreach ($executions as $execution) {
            $rows[] = ExecutionPresenter::listItem($execution);
        }

        return $rows;
    }

    /**
     * The 6 most recently updated team workflows, with their node count and
     * their last run (scalar subselects — one single query, no N+1).
     *
     * @return list<array<string, mixed>>
     */
    private function recentWorkflows(Team $currentTeam): array
    {
        /** @var Collection<int, Workflow> $workflows */
        $workflows = Workflow::query()
            ->where('team_id', $currentTeam->id)
            ->withCount('nodes')
            ->addSelect([
                'last_execution_status' => WorkflowExecution::query()
                    ->select('status')
                    ->whereColumn('workflow_id', 'workflows.id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'last_execution_created_at' => WorkflowExecution::query()
                    ->select('created_at')
                    ->whereColumn('workflow_id', 'workflows.id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::RecentWorkflows)
            ->get();

        $rows = [];

        foreach ($workflows as $workflow) {
            $lastStatus = $workflow->getAttribute('last_execution_status');
            $lastCreatedAt = $workflow->getAttribute('last_execution_created_at');

            $rows[] = [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'status' => $workflow->status->value,
                'nodesCount' => (int) $workflow->nodes_count,
                'lastExecution' => $lastStatus === null ? null : [
                    'status' => (string) $lastStatus,
                    'createdAt' => Carbon::parse((string) $lastCreatedAt)->toIso8601String(),
                ],
            ];
        }

        return $rows;
    }
}
