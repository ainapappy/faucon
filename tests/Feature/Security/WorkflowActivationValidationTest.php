<?php

use App\Models\Workflow;
use App\Models\WorkflowNode;

test('activating a workflow with two triggers is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();

    $response = $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($response->json('errors')['status'][0])->toContain('un seul node déclencheur')
        ->and($workflow->fresh()->status->value)->toBe('draft');
});

test('activating a workflow with an incomplete action config is rejected', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('action.http')->create();

    $response = $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($response->json('errors')['status'][0])->toContain('L’URL est requise.');
});

test('activating a valid workflow succeeds', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('action.http')->withConfig(['url' => 'https://exemple.com/webhook'])->create();

    $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'active'])
        ->assertNoContent();

    expect($workflow->fresh()->status->value)->toBe('active');
});

test('deactivating a workflow never validates the graph', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();

    $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'draft'])
        ->assertNoContent();

    expect($workflow->fresh()->status->value)->toBe('draft');
});

test('renaming a workflow never validates the graph', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();

    $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['name' => 'Nouveau nom'])
        ->assertNoContent();
});

test('re submitting active on an already active workflow is not blocked', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);
    WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->create();
    WorkflowNode::factory()->for($workflow)->ofType('action.http')->create();

    $this->actingAs($user)
        ->patchJson(route('workflows.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['status' => 'active'])
        ->assertNoContent();

    expect($workflow->fresh()->status->value)->toBe('active');
});
