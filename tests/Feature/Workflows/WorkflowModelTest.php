<?php

use App\Enums\WorkflowStatus;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Database\UniqueConstraintViolationException;

test('a workflow belongs to its team and creator', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->create([
        'team_id' => $team->id,
        'created_by' => $user->id,
    ]);

    expect($workflow->team->is($team))->toBeTrue()
        ->and($workflow->creator->is($user))->toBeTrue();
});

test('nodes and edges belong to their workflow', function () {
    $workflow = Workflow::factory()->create();

    $node = WorkflowNode::factory()->for($workflow)->create();
    $edge = WorkflowEdge::factory()->for($workflow)->create();

    expect($node->workflow->is($workflow))->toBeTrue()
        ->and($edge->workflow->is($workflow))->toBeTrue()
        ->and($workflow->nodes->contains($node))->toBeTrue()
        ->and($workflow->edges->contains($edge))->toBeTrue();
});

test('the trigger node relation returns only a trigger node', function () {
    $workflow = Workflow::factory()->create();

    $trigger = WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('data.input')->create();

    expect($workflow->triggerNode->is($trigger))->toBeTrue();
});

test('the trigger node relation returns null without trigger nodes', function () {
    $workflow = Workflow::factory()->create();

    WorkflowNode::factory()->for($workflow)->ofType('data.input')->create();

    expect($workflow->triggerNode)->toBeNull();
});

test('node keys are unique per workflow but reusable across workflows', function () {
    $workflow = Workflow::factory()->create();
    $other = Workflow::factory()->create();

    WorkflowNode::factory()->for($workflow)->keyed('node-1')->create();
    WorkflowNode::factory()->for($other)->keyed('node-1')->create();

    WorkflowNode::factory()->for($workflow)->keyed('node-1')->create();
})->throws(UniqueConstraintViolationException::class);

test('soft delete keeps nodes and edges in the database', function () {
    $workflow = Workflow::factory()->create();

    $source = WorkflowNode::factory()->for($workflow)->create();
    $target = WorkflowNode::factory()->for($workflow)->create();
    WorkflowEdge::factory()->for($workflow)->between($source, $target)->create();

    $workflow->delete();

    $this->assertSoftDeleted($workflow);
    $this->assertDatabaseCount('workflow_nodes', 2);
    $this->assertDatabaseCount('workflow_edges', 1);
});

test('force delete cascades nodes and edges', function () {
    $workflow = Workflow::factory()->create();

    $source = WorkflowNode::factory()->for($workflow)->create();
    $target = WorkflowNode::factory()->for($workflow)->create();
    WorkflowEdge::factory()->for($workflow)->between($source, $target)->create();

    $workflow->forceDelete();

    $this->assertDatabaseCount('workflows', 0);
    $this->assertDatabaseCount('workflow_nodes', 0);
    $this->assertDatabaseCount('workflow_edges', 0);
});

test('workflow status is cast to the enum with a draft default', function () {
    $draft = Workflow::factory()->create();
    $active = Workflow::factory()->active()->create();

    expect($draft->status)->toBe(WorkflowStatus::Draft)
        ->and($draft->status->value)->toBe('draft')
        ->and($active->status)->toBe(WorkflowStatus::Active);
});

test('workflow nodes cast config to an array and keep integer positions', function () {
    $workflow = Workflow::factory()->create();

    $node = WorkflowNode::factory()
        ->for($workflow)
        ->ofType('trigger.webhook')
        ->withConfig(['method' => 'POST', 'path' => 'hooks/leads'])
        ->at(120, -80)
        ->create();

    $node = $node->fresh();

    expect($node->config)->toBe(['method' => 'POST', 'path' => 'hooks/leads'])
        ->and($node->key)->toBeString()
        ->and($node->position_x)->toBeInt()->toBe(120)
        ->and($node->position_y)->toBeInt()->toBe(-80);
});

test('the workflow factory can create a trashed workflow', function () {
    $workflow = Workflow::factory()->trashed()->create();

    expect($workflow->trashed())->toBeTrue();
});
