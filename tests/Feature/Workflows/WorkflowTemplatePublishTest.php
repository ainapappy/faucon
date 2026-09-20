<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowGraphMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A valid manual > output workflow (publishable as-is).
 */
function publishableWorkflow(Team $team, string $name = 'Mon workflow'): Workflow
{
    $workflow = Workflow::factory()->for($team)->create(['name' => $name, 'description' => 'Desc']);

    $trigger = WorkflowNode::factory()->for($workflow)->ofType('trigger.manual')->keyed('trigger')->at(100, 200)->create();
    $output = WorkflowNode::factory()->for($workflow)->ofType('data.output')->keyed('output')->at(420, 200)->create();

    WorkflowEdge::factory()->for($workflow)->between($trigger, $output, 'out')->create();

    return $workflow;
}

test('an authorized member publishes a valid workflow and sees the team template', function () {
    [$user, $team] = teamWithMember();
    $workflow = publishableWorkflow($team);

    $response = $this->actingAs($user)
        ->post(route('workflows.publish', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'name' => 'Mon template',
            'description' => 'Ma description',
            'category' => 'Ventes',
        ]);

    $response->assertRedirect();

    $template = WorkflowTemplate::query()->where('team_id', $team->id)->where('name', 'Mon template')->firstOrFail();

    expect($template->description)->toBe('Ma description')
        ->and($template->category)->toBe('Ventes')
        ->and($template->graph)->toBe(app(WorkflowGraphMapper::class)->snapshot($workflow->refresh()))
        ->and($template->origin->value)->toBe('team');
});

test('publishing a non-executable workflow redirects back with a graph error', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('data.input')->keyed('entree')->create();

    $response = $this->actingAs($user)
        ->from(route('workflows.index', ['current_team' => $team->slug]))
        ->post(route('workflows.publish', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'name' => 'Mon template',
            'description' => null,
            'category' => 'Ventes',
        ]);

    $response->assertRedirect(route('workflows.index', ['current_team' => $team->slug]));
    $response->assertSessionHasErrors(['graph' => 'Le workflow doit contenir exactement un node déclencheur.']);

    expect(WorkflowTemplate::query()->count())->toBe(0);
});

test('publishing is forbidden to a non member of the team', function () {
    [$user, $team] = teamWithMember();
    $workflow = publishableWorkflow($team);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('workflows.publish', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'name' => 'X',
            'category' => 'Y',
        ])
        ->assertForbidden();

    expect(WorkflowTemplate::query()->count())->toBe(0);
});

test('the workflow of another team is a 404', function () {
    [$user, $team] = teamWithMember();
    $foreign = publishableWorkflow(Team::factory()->create(), 'Autre');

    $this->actingAs($user)
        ->post(route('workflows.publish', ['current_team' => $team->slug, 'workflow' => $foreign->id]), [
            'name' => 'X',
            'category' => 'Y',
        ])
        ->assertNotFound();

    expect(WorkflowTemplate::query()->count())->toBe(0);
});

test('the workflows index exposes the template categories visible to the team', function () {
    [$user, $team] = teamWithMember();
    publishableWorkflow($team);

    WorkflowTemplate::factory()->system()->create(['category' => 'Support']);
    WorkflowTemplate::factory()->team()->create(['team_id' => $team->id, 'category' => 'Données']);
    WorkflowTemplate::factory()->team()->create(['category' => 'Secrète']);

    $response = $this->actingAs($user)
        ->get(route('workflows.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflows/Index')
            ->where('templateCategories', ['Support', 'Données'])
        );
});
