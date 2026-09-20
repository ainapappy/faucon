<?php

namespace App\Services\Workflow;

use App\Actions\Workflows\EnsureWebhookEndpoint;
use App\Enums\TemplateOrigin;
use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\Exception\TemplateNotPublishableException;
use Illuminate\Support\Facades\DB;

/**
 * Template operations over workflows (phase 9): instantiate a template
 * into a fresh draft, duplicate a workflow and publish a workflow as a
 * team template. The single place of truth for graph copying — the
 * DuplicateWorkflow action is folded into duplicate().
 */
final class WorkflowTemplater
{
    public function __construct(
        private readonly WorkflowValidator $validator,
        private readonly WorkflowGraphMapper $mapper,
        private readonly EnsureWebhookEndpoint $ensureWebhookEndpoint,
    ) {}

    /**
     * Instantiate a template in the team: a draft workflow named after the
     * template (as-is — two instantiations coexist on purpose) with a
     * faithful copy of the graph. Node keys are reused: uniqueness of
     * (workflow_id, key) is per workflow.
     *
     * The snapshot is NOT re-validated here: the invariant "a corrupt
     * template never exists" is guaranteed at write time (publish + tested
     * seeder), so re-validating would only tax every "Use" without ever
     * firing. Webhook templates become immediately triggerable: the
     * endpoint is ensured right after the transaction.
     */
    public function instantiate(WorkflowTemplate $template, Team $team, User $user): Workflow
    {
        $workflow = DB::transaction(function () use ($template, $team, $user): Workflow {
            $workflow = new Workflow;

            $workflow->team_id = $team->id;
            $workflow->created_by = $user->id;
            $workflow->name = $template->name;
            $workflow->description = $template->description;
            $workflow->status = WorkflowStatus::Draft;
            $workflow->save();

            $this->copyGraphFromSnapshot($workflow, $template->graph);

            return $workflow;
        });

        $this->ensureWebhookEndpoint->handle($workflow);

        return $workflow;
    }

    /**
     * Copy the workflow as a draft (same graph, new identity, name suffixed
     * « (copie) ») — behavior moved as-is from DuplicateWorkflow (phase 3,
     * amendment A2), plus the webhook endpoint sync for symmetry with
     * instantiate(). The original is never mutated.
     */
    public function duplicate(Workflow $workflow, User $user): Workflow
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

            $this->ensureWebhookEndpoint->handle($copy);

            return $copy;
        });
    }

    /**
     * Snapshot the validated graph of a workflow into a team template.
     *
     * Validation is mandatory before publishing: a non-executable workflow
     * (no or several triggers, invalid config, missing handler, cycle)
     * refuses the publication. A re-publish creates a SECOND template —
     * never an upsert (no template management in phase 9).
     *
     * @throws TemplateNotPublishableException carrying the validator errors
     */
    public function publish(Workflow $workflow, User $user, string $name, ?string $description, string $category): WorkflowTemplate
    {
        $errors = $this->validator->validate(...$this->mapper->map($workflow));

        if ($errors !== []) {
            throw new TemplateNotPublishableException($errors);
        }

        return DB::transaction(function () use ($category, $description, $name, $user, $workflow): WorkflowTemplate {
            $template = new WorkflowTemplate;

            $template->team_id = $workflow->team_id;
            $template->created_by = $user->id;
            $template->origin = TemplateOrigin::Team;
            $template->name = $name;
            $template->description = $description;
            $template->category = $category;
            $template->graph = $this->mapper->snapshot($workflow);
            $template->save();

            return $template;
        });
    }

    /**
     * Recreate nodes/edges rows from a stored snapshot (camelCase →
     * columns), mirroring SaveWorkflowGraph's mapping.
     *
     * @param  array{nodes: list<array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int|float, positionY: int|float}>, edges: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>}  $graph
     */
    private function copyGraphFromSnapshot(Workflow $workflow, array $graph): void
    {
        $workflow->nodes()->createMany(collect($graph['nodes'])->map(fn (array $node): array => [
            'key' => $node['key'],
            'type' => $node['type'],
            'name' => $node['name'],
            'config' => $node['config'],
            'position_x' => (int) round((float) $node['positionX']),
            'position_y' => (int) round((float) $node['positionY']),
        ])->all());

        $workflow->edges()->createMany(collect($graph['edges'])->map(fn (array $edge): array => [
            'source_node_key' => $edge['sourceNodeKey'],
            'target_node_key' => $edge['targetNodeKey'],
            'source_handle' => $edge['sourceHandle'],
        ])->all());
    }
}
