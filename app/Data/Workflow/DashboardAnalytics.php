<?php

namespace App\Data\Workflow;

/**
 * Deferred analytics payload of the dashboard (phase 10, D2).
 *
 * daily is a CONTIGUOUS series of 30 days ending today (gap-filled back
 * side — the chart never interpolates anything), date is 'YYYY-MM-DD'.
 * topWorkflows are the 5 most run workflows of the last 7 days, runs desc.
 */
final readonly class DashboardAnalytics
{
    /**
     * @param  list<array{date: string, total: int, completed: int, failed: int}>  $daily
     * @param  list<array{id: int, name: string, runs: int}>  $topWorkflows
     */
    public function __construct(
        public array $daily,
        public array $topWorkflows,
    ) {}
}
