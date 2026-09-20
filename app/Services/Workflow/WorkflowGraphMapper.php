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

    /**
     * Map the persisted graph to the builder payload shape (template
     * snapshot): the exact Phase 3 camelCase contract, positions included
     * as integers (they are rounded at graph save time).
     *
     * @return array{
     *     nodes: list<array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int, positionY: int}>,
     *     edges: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>,
     * }
     */
    public function snapshot(Workflow $workflow): array
    {
        $workflow->loadMissing(['nodes', 'edges']);

        $nodes = array_values($workflow->nodes->map(fn (WorkflowNode $node): array => [
            'key' => $node->key,
            'type' => $node->type,
            'name' => $node->name,
            'config' => $node->config ?? [],
            'positionX' => $node->position_x,
            'positionY' => $node->position_y,
        ])->all());

        $edges = array_values($workflow->edges->map(fn (WorkflowEdge $edge): array => [
            'sourceNodeKey' => $edge->source_node_key,
            'targetNodeKey' => $edge->target_node_key,
            'sourceHandle' => $edge->source_handle,
        ])->all());

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Map a stored snapshot (template graph) to the engine shapes — pure
     * counterpart of snapshot(). Used by validation over a raw snapshot and
     * available for a future import; instantiate() does not go through it
     * (it recreates DB rows, not engine arrays).
     *
     * @param  array{nodes: list<array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int|float, positionY: int|float}>, edges: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>}  $graph
     * @return array{
     *     0: list<array{key: string, type: string, name: string, config: array<string, mixed>}>,
     *     1: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>
     * }
     */
    public static function fromSnapshot(array $graph): array
    {
        $nodes = array_map(fn (array $node): array => [
            'key' => $node['key'],
            'type' => $node['type'],
            'name' => $node['name'],
            'config' => $node['config'],
        ], $graph['nodes']);

        $edges = array_map(fn (array $edge): array => [
            'sourceNodeKey' => $edge['sourceNodeKey'],
            'targetNodeKey' => $edge['targetNodeKey'],
            'sourceHandle' => $edge['sourceHandle'],
        ], $graph['edges']);

        return [$nodes, $edges];
    }
}
