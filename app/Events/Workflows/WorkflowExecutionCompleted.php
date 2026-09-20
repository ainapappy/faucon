<?php

namespace App\Events\Workflows;

use App\Models\WorkflowExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The execution completed successfully (phase 7).
 *
 * Dispatched by RunWorkflowJob on the persisted transition to completed —
 * once per execution, never on intermediate states.
 */
final class WorkflowExecutionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WorkflowExecution $execution) {}
}
