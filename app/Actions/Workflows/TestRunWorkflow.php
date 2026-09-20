<?php

namespace App\Actions\Workflows;

use App\Data\Workflow\ExecutionResult;
use App\Models\Workflow;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;

/**
 * Load the persisted graph, map it via the shared mapper and run it.
 *
 * Reused as-is by the public webhook endpoint (WebhookController) and
 * by the builder test-run: two triggers of the same engine (D19).
 *
 * Since phase 7 the graph mapping lives in WorkflowGraphMapper, shared
 * with RunWorkflowJob — the queued run maps the graph exactly like the
 * synchronous test-run does.
 */
class TestRunWorkflow
{
    public function __construct(
        private readonly WorkflowRunner $runner,
        private readonly WorkflowGraphMapper $mapper,
    ) {}

    /**
     * Run the workflow graph with the given sample input.
     *
     * @param  array<string, mixed>  $sampleInput
     */
    public function handle(Workflow $workflow, array $sampleInput): ExecutionResult
    {
        [$nodes, $edges] = $this->mapper->map($workflow);

        return $this->runner->run($nodes, $edges, $sampleInput);
    }
}
