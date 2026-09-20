<?php

namespace App\Data\Workflow;

/**
 * Team KPIs of the dashboard (phase 10, D1/D2).
 *
 * successRate7d = completed / (completed + failed) of the last 7 days, in
 * percent (1 decimal) — cancelled is deliberately outside the denominator.
 * null when nothing finished (the front renders « — »). averageDurationMs7d
 * averages duration_ms of COMPLETED executions only; null when none.
 */
final readonly class DashboardStats
{
    public function __construct(
        public int $activeWorkflows,
        public int $totalWorkflows,
        public int $executions24h,
        public int $executions7d,
        public ?float $successRate7d,
        public int $failures7d,
        public ?int $averageDurationMs7d,
    ) {}
}
