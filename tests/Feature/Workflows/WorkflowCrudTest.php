<?php

use App\Enums\WorkflowStatus;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Inertia\Testing\AssertableInertia as Assert;

test('the workflows index renders the list of the current team', function () {
    [$user, $team] = teamWithMember();

    $mine = Workflow::factory()->for($team)->create(['name' => 'Mine']);
    $other = Workflow::factory()->create(['name' => 'Other team workflow']);

    WorkflowNode::factory()->for($mine)->ofType('trigger.webhook')->create();
    WorkflowNode::factory()->for($mine)->ofType('data.input')->create();

    $response = $this
        ->actingAs($user)
        ->get(route('workflows.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflows/Index')
            ->has('workflows', 1)
            ->where('workflows.0.id', $mine->id)
            ->where('workflows.0.name', 'Mine')
            ->where('workflows.0.status', 'draft')
            ->where('workflows.0.nodesCount', 2)
            ->where('workflows.0.triggerType', 'trigger.webhook')
            ->has('nodeTypes', 14)
            ->has('permissions.canCreateWorkflow')
            ->missing('workflows.0.deleted_at')
        );
});

test('the workflows index orders by latest first', function () {
    [$user, $team] = teamWithMember();

    Workflow::factory()->for($team)->create(['name' => 'Oldest', 'created_at' => now()->subMinute()]);
    Workflow::factory()->for($team)->create(['name' => 'Newest']);

    $response = $this
        ->actingAs($user)
        ->get(route('workflows.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflows/Index')
            ->where('workflows.0.name', 'Newest')
            ->where('workflows.1.name', 'Oldest')
        );
});

test('a workflow is created as an empty draft and redirects to the builder', function () {
    [$user, $team] = teamWithMember();

    $response = $this
        ->actingAs($user)
        ->postJson(route('workflows.store', ['current_team' => $team->slug]), [
            'name' => 'Support client IA',
        ]);

    $workflow = Workflow::query()->where('name', 'Support client IA')->firstOrFail();

    $response->assertRedirect(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Workflow created.')]);

    expect($workflow->status->value)->toBe('draft')
        ->and($workflow->team_id)->toBe($team->id)
        ->and($workflow->created_by)->toBe($user->id)
        ->and($workflow->nodes)->toHaveCount(0)
        ->and($workflow->edges)->toHaveCount(0);
});

test('a workflow cannot be created without a name', function () {
    [$user, $team] = teamWithMember();

    $this
        ->actingAs($user)
        ->postJson(route('workflows.store', ['current_team' => $team->slug]), [
            'description' => 'no name',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('a workflow can be renamed and described', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['name' => 'Before']);

    $this
        ->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'name' => 'After',
            'description' => 'Renamed workflow',
        ])
        ->assertNoContent();

    expect($workflow->fresh()->name)->toBe('After')
        ->and($workflow->fresh()->description)->toBe('Renamed workflow');
});

test('pausing an active workflow returns it to draft', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->active()->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();

    $this
        ->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'status' => 'draft',
        ])
        ->assertNoContent();

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::Draft);
});

test('a workflow is soft deleted with its nodes and edges preserved', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $source = WorkflowNode::factory()->for($workflow)->create();
    WorkflowEdge::factory()->for($workflow)->between(
        $source,
        WorkflowNode::factory()->for($workflow)->create(),
    )->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('workflows.destroy', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertRedirect(route('workflows.index', ['current_team' => $team->slug]));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Workflow deleted.')]);

    $this->assertSoftDeleted($workflow);
    $this->assertDatabaseCount('workflow_nodes', 2);
    $this->assertDatabaseCount('workflow_edges', 1);
});

test('a workflow is duplicated as a draft copy with an identical graph', function () {
    [$user, $team] = teamWithMember();
    $original = Workflow::factory()->for($team)->create([
        'name' => 'Original',
        'description' => 'Some description',
    ]);

    $trigger = WorkflowNode::factory()->for($original)->ofType('trigger.manual')->keyed('k1')->at(10, 20)->create();
    $email = WorkflowNode::factory()->for($original)->ofType('action.email')->keyed('k2')->at(300, 20)->withConfig(['to' => 'x'])->create();
    WorkflowEdge::factory()->for($original)->between($trigger, $email, 'out')->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('workflows.duplicate', ['current_team' => $team->slug, 'workflow' => $original->id]));

    $copy = Workflow::query()->where('name', 'Original (copie)')->firstOrFail();

    $response->assertRedirect(route('workflows.index', ['current_team' => $team->slug]));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Workflow duplicated.')]);

    expect($copy->status->value)->toBe('draft')
        ->and($copy->id)->not->toBe($original->id)
        ->and($copy->team_id)->toBe($team->id)
        ->and($copy->created_by)->toBe($user->id);

    $copyNodes = $copy->nodes->keyBy('key');
    $originalNodes = $original->nodes->keyBy('key');

    expect($copyNodes->keys()->toArray())->toBe($originalNodes->keys()->toArray());

    foreach ($originalNodes as $key => $node) {
        expect($copyNodes[$key]->type)->toBe($node->type)
            ->and($copyNodes[$key]->position_x)->toBe($node->position_x)
            ->and($copyNodes[$key]->position_y)->toBe($node->position_y);
    }

    $originalEdges = $original->edges->map(fn ($edge) => [$edge->source_node_key, $edge->target_node_key, $edge->source_handle])->toArray();
    $copyEdges = $copy->edges->map(fn ($edge) => [$edge->source_node_key, $edge->target_node_key, $edge->source_handle])->toArray();

    expect($copyEdges)->toBe($originalEdges)
        ->and($original->nodes()->count())->toBe(2)
        ->and($original->edges()->count())->toBe(1);
});
