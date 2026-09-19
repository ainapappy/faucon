<?php

namespace App\Services\Workflow;

/**
 * Pure graph structure service: node lookup, successor filtering and input
 * merging. Knows no node type and no runner.
 *
 * Successor rule: an edge is followed when the handler announces no branch
 * (all outgoing edges are default edges) or when its source handle is null
 * (always followed) or equals the announced branch.
 */
final class GraphTraverser
{
    /**
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     * @param  list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>  $edges
     */
    public function __construct(
        private readonly array $nodes,
        private readonly array $edges,
    ) {}

    /**
     * Get the node shape for a key, or null when unknown.
     *
     * @return array{key: string, type: string, name: string, config: array<string, mixed>}|null
     */
    public function node(string $key): ?array
    {
        foreach ($this->nodes as $node) {
            if ($node['key'] === $key) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Keys of the successors to follow after a node executed, in edge order.
     *
     * @return list<string>
     */
    public function successorsOf(string $nodeKey, ?string $branch): array
    {
        $successors = [];

        foreach ($this->edges as $edge) {
            if ($edge['sourceNodeKey'] !== $nodeKey) {
                continue;
            }

            $handle = $edge['sourceHandle'];

            if ($branch !== null && $handle !== null && $handle !== $branch) {
                continue;
            }

            $successors[] = $edge['targetNodeKey'];
        }

        return $successors;
    }

    /**
     * Merge the outputs of ALREADY EXECUTED upstream nodes — one
     * contribution per upstream node (not per edge), edge order, the last
     * one wins.
     *
     * @param  array<string, array<string, mixed>>  $executedOutputs
     * @return array<string, mixed>
     */
    public function mergeInputs(string $nodeKey, array $executedOutputs): array
    {
        $merged = [];
        $contributed = [];

        foreach ($this->edges as $edge) {
            if ($edge['targetNodeKey'] !== $nodeKey) {
                continue;
            }

            $source = $edge['sourceNodeKey'];

            if (isset($contributed[$source]) || ! isset($executedOutputs[$source])) {
                continue;
            }

            $contributed[$source] = true;

            foreach ($executedOutputs[$source] as $varKey => $value) {
                $merged[$varKey] = $value;
            }
        }

        return $merged;
    }
}
