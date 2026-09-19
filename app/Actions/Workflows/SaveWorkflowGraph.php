<?php

namespace App\Actions\Workflows;

use App\Models\Workflow;
use Illuminate\Support\Facades\DB;

class SaveWorkflowGraph
{
    /**
     * Replace the whole graph of the workflow transactionally.
     *
     * @param  array<int, array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int|float, positionY: int|float}>  $nodes
     * @param  array<int, array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>  $edges
     */
    public function handle(Workflow $workflow, array $nodes, array $edges): void
    {
        DB::transaction(function () use ($workflow, $nodes, $edges): void {
            $workflow->edges()->delete();
            $workflow->nodes()->delete();

            $workflow->nodes()->createMany(collect($nodes)->map(fn (array $node): array => [
                'key' => $node['key'],
                'type' => $node['type'],
                'name' => $node['name'],
                'config' => $node['config'],
                'position_x' => (int) round((float) $node['positionX']),
                'position_y' => (int) round((float) $node['positionY']),
            ])->all());

            $workflow->edges()->createMany(collect($edges)->map(fn (array $edge): array => [
                'source_node_key' => $edge['sourceNodeKey'],
                'target_node_key' => $edge['targetNodeKey'],
                'source_handle' => $edge['sourceHandle'],
            ])->all());
        });
    }
}
