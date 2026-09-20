<?php

use App\Actions\Workflows\DispatchScheduledWorkflows;
use App\Actions\Workflows\SaveWorkflowGraph;
use App\Jobs\RunWorkflowJob;
use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

/**
 * An active workflow of the team holding a manual trigger and the given
 * number of ai nodes.
 */
function aiWorkflow(Team $team, int $aiNodes = 1): Workflow
{
    $workflow = Workflow::factory()->for($team)->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();

    foreach (range(1, $aiNodes) as $i) {
        WorkflowNode::factory()->for($workflow)->keyed('a'.$i)->ofType('ai.classification')->withConfig([
            'model' => 'fake/demo',
            'prompt' => 'Classe le message client',
            'labels' => 'lead, spam',
        ])->create();
    }

    return $workflow;
}

test('the 11th test-run of the same user within a minute is rejected with 429', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->active()->create();

    RateLimiter::clear('workflow-run:'.$user->id.'|'.$team->id);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['input' => '{}'])
            ->assertOk();
    }

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['input' => '{}']);

    $response->assertStatus(429);
});

test('a user of another team is not limited by the same workflow-run key', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->active()->create();

    RateLimiter::clear('workflow-run:'.$user->id.'|'.$team->id);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), ['input' => '{}'])
            ->assertOk();
    }

    [$otherUser, $otherTeam] = teamWithMember();

    $otherWorkflow = Workflow::factory()->for($otherTeam)->active()->create();

    $response = $this->actingAs($otherUser)
        ->postJson(route('workflows.test-run', ['current_team' => $otherTeam->slug, 'workflow' => $otherWorkflow->id]), ['input' => '{}']);

    $response->assertOk();
});

test('the third run of an ai workflow is refused with 422 when the team budget is spent', function () {
    config(['workflows.rate_limits.ai_calls_per_minute' => 2]);

    Queue::fake();

    [$user, $team] = teamWithMember();

    $workflow = aiWorkflow($team);

    RateLimiter::clear('ai-call|team:'.$team->id);

    $this->actingAs($user)->postJson(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))->assertRedirect();
    $this->actingAs($user)->postJson(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))->assertRedirect();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

    $response->assertStatus(422);

    expect($response->json('errors.workflow.0'))->toBe('Budget d’appels IA de l’équipe épuisé pour cette minute.')
        ->and(WorkflowExecution::query()->count())->toBe(2);

    Queue::assertPushed(RunWorkflowJob::class, 2);
});

test('a workflow without ai nodes is never affected by the ai budget', function () {
    config(['workflows.rate_limits.ai_calls_per_minute' => 1]);

    Queue::fake();

    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->active()->create();

    WorkflowNode::factory()->for($workflow)->keyed('t')->ofType('trigger.manual')->create();

    RateLimiter::clear('ai-call|team:'.$team->id);

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($user)
            ->postJson(route('workflows.run', ['current_team' => $team->slug, 'workflow' => $workflow->id]))
            ->assertRedirect();
    }

    expect(WorkflowExecution::query()->count())->toBe(3);

    Queue::assertPushed(RunWorkflowJob::class, 3);
});

test('a webhook run over the ai budget answers 422 and dispatches nothing', function () {
    config(['workflows.rate_limits.ai_calls_per_minute' => 0]);

    Queue::fake();

    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->create(['status' => 'active']);

    app(SaveWorkflowGraph::class)->handle($workflow, [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 0, 'positionY' => 0],
        ['key' => 'n2', 'type' => 'ai.classification', 'name' => 'IA', 'config' => [
            'model' => 'fake/demo',
            'prompt' => 'Classe : {{ trigger.event }}',
            'labels' => 'lead, spam',
        ], 'positionX' => 0, 'positionY' => 0],
    ], [
        ['sourceNodeKey' => 'n1', 'targetNodeKey' => 'n2', 'sourceHandle' => 'out'],
    ]);

    $endpoint = $workflow->webhookEndpoint()->firstOrFail();

    $response = $this->postJson(route('webhooks.handle', ['token' => $endpoint->token]), ['event' => 'order.created']);

    $response->assertStatus(422);

    expect(WorkflowExecution::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('a scheduled ai workflow over budget is skipped without breaking the other dispatches', function () {
    config(['workflows.rate_limits.ai_calls_per_minute' => 0]);

    Queue::fake();
    Exceptions::fake();

    [$user, $team] = teamWithMember();

    $aiWorkflow = Workflow::factory()->for($team)->active()->create();

    WorkflowNode::factory()->for($aiWorkflow)->keyed('s')->ofType('trigger.schedule')->withConfig([
        'cron' => '* * * * *',
    ])->create();
    WorkflowNode::factory()->for($aiWorkflow)->keyed('a')->ofType('ai.classification')->withConfig([
        'model' => 'fake/demo',
        'prompt' => 'Classe le message client',
        'labels' => 'lead, spam',
    ])->create();

    $plainWorkflow = Workflow::factory()->for($team)->active()->create();

    WorkflowNode::factory()->for($plainWorkflow)->keyed('s')->ofType('trigger.schedule')->withConfig([
        'cron' => '* * * * *',
    ])->create();
    WorkflowNode::factory()->for($plainWorkflow)->keyed('o')->ofType('data.output')->create();

    $dispatched = app(DispatchScheduledWorkflows::class)->handle();

    expect($dispatched)->toBe(1)
        ->and(WorkflowExecution::query()->where('workflow_id', $aiWorkflow->id)->exists())->toBeFalse()
        ->and(WorkflowExecution::query()->where('workflow_id', $plainWorkflow->id)->exists())->toBeTrue();

    Queue::assertPushed(RunWorkflowJob::class, 1);
    Exceptions::assertNothingReported();
});
