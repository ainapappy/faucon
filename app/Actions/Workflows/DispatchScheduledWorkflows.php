<?php

namespace App\Actions\Workflows;

use App\Enums\ExecutionTrigger;
use App\Enums\WorkflowStatus;
use App\Models\Workflow;
use Cron\CronExpression;
use Throwable;

/**
 * One scheduler entry dispatches every due scheduled workflow (phase 7).
 *
 * Runs every minute: a workflow is due when its trigger.schedule cron is
 * due at the current minute. Missing or invalid cron expressions are
 * skipped and reported — a broken node never crashes the scheduler entry.
 */
class DispatchScheduledWorkflows
{
    public function __construct(private readonly StartWorkflowRun $startWorkflowRun) {}

    /**
     * Dispatch a run for every due active workflow; returns the count.
     */
    public function handle(): int
    {
        $dispatched = 0;

        Workflow::query()
            ->where('status', WorkflowStatus::Active->value)
            ->whereHas('nodes', fn ($query) => $query->where('type', 'trigger.schedule'))
            ->with(['nodes' => fn ($query) => $query->where('type', 'trigger.schedule')])
            ->each(function (Workflow $workflow) use (&$dispatched): void {
                try {
                    $node = $workflow->nodes->first();

                    $cron = is_array($node?->config) ? (string) ($node->config['cron'] ?? '') : '';

                    if ($cron === '' || ! CronExpression::factory($cron)->isDue(now())) {
                        return;
                    }

                    $this->startWorkflowRun->handle($workflow, ExecutionTrigger::Schedule, [], null);

                    $dispatched++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        return $dispatched;
    }
}
