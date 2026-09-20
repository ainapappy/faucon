<?php

namespace App\Actions\Workflows;

use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Jobs\RunWorkflowJob;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

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
     * Throws when the team ai budget is exhausted (S10) — the refusal
     * happens BEFORE any execution row or job is created.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Workflow $workflow, ExecutionTrigger $trigger, array $input, ?User $user): WorkflowExecution
    {
        $this->enforceAiBudget($workflow);

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

    /**
     * Consume the team ai budget (S10): every ai.* node of the graph costs
     * one call against the per-minute team limit. The count runs against
     * the database — the eager-loaded nodes may be partial (the scheduler
     * entry only loads the trigger.schedule nodes). A refusal happens when
     * spending the cost would exceed the budget (so a budget of zero
     * blocks every ai run); workflows without ai nodes never touch it.
     */
    private function enforceAiBudget(Workflow $workflow): void
    {
        $aiNodes = $workflow->nodes()
            ->where('type', 'like', 'ai.%')
            ->count();

        if ($aiNodes === 0) {
            return;
        }

        $key = 'ai-call|team:'.$workflow->team_id;

        $budget = (int) config('workflows.rate_limits.ai_calls_per_minute', 30);

        if (RateLimiter::attempts($key) + $aiNodes > $budget) {
            throw ValidationException::withMessages([
                'workflow' => __('Budget d’appels IA de l’équipe épuisé pour cette minute.'),
            ]);
        }

        RateLimiter::increment($key, 60, $aiNodes);
    }
}
