<?php

namespace App\Services\Workflow;

use App\Data\Workflow\DashboardAnalytics;
use App\Data\Workflow\DashboardStats;
use App\Enums\ExecutionStatus;
use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Support\Collection;

/**
 * Team-scoped dashboard aggregates (phase 10, D2) — the ONLY new query code.
 *
 * Two aggregate queries per method, no N+1 by construction. Enum values and
 * bounds always travel as bindings (never interpolated): `date()` and
 * `CASE WHEN` are valid on SQLite (tests) AND MariaDB (dev).
 *
 * No cache (A6): the 90-day retention bounds the volume; revisit at phase 13.
 */
final class DashboardMetrics
{
    /**
     * The last 30 days of the daily series, ending today (inclusive).
     */
    private const DailyDays = 30;

    /**
     * The window (days) of the top-workflows ranking.
     */
    private const TopWorkflowsDays = 7;

    /**
     * Maximum number of top workflows served.
     */
    private const TopWorkflowsLimit = 5;

    /**
     * Team KPIs: 1 workflow aggregate + 1 conditional-executions aggregate.
     */
    public function stats(Team $team): DashboardStats
    {
        $workflows = Workflow::query()
            ->where('team_id', $team->id)
            ->selectRaw('count(*) as total_workflows, sum(case when status = ? then 1 else 0 end) as active_workflows', [
                WorkflowStatus::Active->value,
            ])
            ->toBase()
            ->first();

        $executions = WorkflowExecution::query()
            ->where('team_id', $team->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('count(*) as executions_7d')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as executions_24h', [now()->subDay()])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [ExecutionStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [ExecutionStatus::Failed->value])
            ->selectRaw('avg(case when status = ? then duration_ms end) as avg_duration_ms', [ExecutionStatus::Completed->value])
            ->toBase()
            ->first();

        $completed = (int) $executions->completed;
        $failed = (int) $executions->failed;
        $finished = $completed + $failed;

        return new DashboardStats(
            activeWorkflows: (int) $workflows->active_workflows,
            totalWorkflows: (int) $workflows->total_workflows,
            executions24h: (int) $executions->executions_24h,
            executions7d: (int) $executions->executions_7d,
            successRate7d: $finished === 0 ? null : round($completed / $finished * 100, 1),
            failures7d: $failed,
            averageDurationMs7d: $executions->avg_duration_ms === null ? null : (int) round((float) $executions->avg_duration_ms),
        );
    }

    /**
     * The contiguous daily series (30 days, gap-filled in PHP) + the top
     * workflows of the last 7 days: 2 aggregate queries.
     */
    public function analytics(Team $team): DashboardAnalytics
    {
        $daily = $this->dailySeries($team);
        $topWorkflows = $this->topWorkflows($team);

        return new DashboardAnalytics(
            daily: $daily,
            topWorkflows: $topWorkflows,
        );
    }

    /**
     * @return list<array{date: string, total: int, completed: int, failed: int}>
     */
    private function dailySeries(Team $team): array
    {
        $rows = WorkflowExecution::query()
            ->where('team_id', $team->id)
            ->where('created_at', '>=', now()->subDays(self::DailyDays - 1)->startOfDay())
            ->selectRaw('date(created_at) as day')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [ExecutionStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [ExecutionStatus::Failed->value])
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];

        for ($offset = self::DailyDays - 1; $offset >= 0; $offset--) {
            $date = now()->subDays($offset)->toDateString();
            $row = $rows->get($date);

            $series[] = [
                'date' => $date,
                'total' => (int) ($row->total ?? 0),
                'completed' => (int) ($row->completed ?? 0),
                'failed' => (int) ($row->failed ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{id: int, name: string, runs: int}>
     */
    private function topWorkflows(Team $team): array
    {
        /** @var Collection<int, object{id: int, name: string, runs: int}> $rows */
        $rows = WorkflowExecution::query()
            ->where('workflow_executions.team_id', $team->id)
            ->where('workflow_executions.created_at', '>=', now()->subDays(self::TopWorkflowsDays))
            ->join('workflows', 'workflows.id', '=', 'workflow_executions.workflow_id')
            ->whereNull('workflows.deleted_at')
            ->groupBy('workflow_executions.workflow_id', 'workflows.name')
            ->orderByDesc('runs')
            ->orderBy('workflows.name')
            ->limit(self::TopWorkflowsLimit)
            ->selectRaw('workflow_executions.workflow_id as id, workflows.name as name, count(*) as runs')
            ->get();

        $top = [];

        foreach ($rows as $row) {
            $top[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'runs' => (int) $row->runs,
            ];
        }

        return $top;
    }
}
