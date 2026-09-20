<?php

namespace App\Services\Workflow;

use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;

/**
 * Maps a persisted graph to the engine shapes (nodes/edges arrays).
 *
 * Extracted from TestRunWorkflow in phase 7 so the queued job and the
 * synchronous test-run share the exact same mapping — one place of truth.
 */
final class WorkflowGraphMapper
{
    /**
     * Map the persisted graph of a workflow (nodes/edges must be loadable).
     *
     * @return array{
     *     0: list<array{key: string, type: string, name: string, config: array<string, mixed>}>,
     *     1: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>
     * }
     */
    public function map(Workflow $workflow): array
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

        return [$nodes, $edges];
    }
}
