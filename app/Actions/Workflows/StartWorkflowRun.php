<?php

namespace App\Actions\Workflows;

use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Jobs\RunWorkflowJob;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;

/**
 * The single place that creates a pending execution and dispatches its job.
 *
 * Used by the three triggers — the manual run endpoint, the public webhook
 * and the scheduler entry — so creation and dispatch never drift apart.
 * The webhook idempotence happens UPSTREAM of this call (WebhookController).
 */
class StartWorkflowRun
{
    /**
     * Create the execution (pending) and dispatch its queued run.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(Workflow $workflow, ExecutionTrigger $trigger, array $input, ?User $user): WorkflowExecution
    {
        $execution = WorkflowExecution::query()->create([
            'workflow_id' => $workflow->id,
            'team_id' => $workflow->team_id,
            'user_id' => $user?->id,
            'triggered_by' => $trigger,
            'status' => ExecutionStatus::Pending,
            'input' => $input,
        ]);

        RunWorkflowJob::dispatch($execution->id);

        return $execution;
    }
}
