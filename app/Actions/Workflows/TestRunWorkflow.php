<?php

namespace App\Actions\Workflows;

use App\Data\Workflow\ExecutionResult;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\Workflow\WorkflowRunner;

/**
 * Load the persisted graph, map it to the engine shapes and run it.
 *
 * The mapping is inline with PHPDoc shapes: a shared GraphSnapshot DTO would
 * wait for phase 7 (execution snapshot) to justify itself (YAGNI).
 */
class TestRunWorkflow
{
    public function __construct(private readonly WorkflowRunner $runner) {}

    /**
     * Run the workflow graph with the given sample input.
     *
     * @param  array<string, mixed>  $sampleInput
     */
    public function handle(Workflow $workflow, array $sampleInput): ExecutionResult
    {
        $workflow->loadMissing(['nodes', 'edges']);

        $nodes = array_values($workflow->nodes->map(fn (WorkflowNode $node): array => [
            'key' => $node->key,
            'type' => $node->type,
            'name' => $node->name,
            'config' => $node->config ?? [],
        ])->all());

        $edges = array_values($workflow->edges->map(fn (WorkflowEdge $edge): array => [
            'sourceNodeKey' => $edge->source_node_key,
            'targetNodeKey' => $edge->target_node_key,
            'sourceHandle' => $edge->source_handle,
        ])->all());

        return $this->runner->run($nodes, $edges, $sampleInput);
    }
}
