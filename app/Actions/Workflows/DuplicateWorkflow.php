<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Support\Facades\DB;

class DuplicateWorkflow
{
    /**
     * Copy the workflow as a draft (same graph, new identity).
     *
     * Node keys are reused: uniqueness of (workflow_id, key) is per workflow.
     */
    public function handle(User $user, Workflow $workflow): Workflow
    {
        return DB::transaction(function () use ($user, $workflow): Workflow {
            $copy = new Workflow;

            $copy->team_id = $workflow->team_id;
            $copy->created_by = $user->id;
            $copy->name = $workflow->name.' (copie)';
            $copy->description = $workflow->description;
            $copy->status = WorkflowStatus::Draft;
            $copy->save();

            $copy->nodes()->createMany($workflow->nodes->map(fn (WorkflowNode $node): array => [
                'key' => $node->key,
                'type' => $node->type,
                'name' => $node->name,
                'config' => $node->config,
                'position_x' => $node->position_x,
                'position_y' => $node->position_y,
            ])->all());

            $copy->edges()->createMany($workflow->edges->map(fn (WorkflowEdge $edge): array => [
                'source_node_key' => $edge->source_node_key,
                'target_node_key' => $edge->target_node_key,
                'source_handle' => $edge->source_handle,
            ])->all());

            return $copy;
        });
    }
}
