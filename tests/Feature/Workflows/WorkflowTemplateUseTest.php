<?php

use App\Enums\WorkflowStatus;
use App\Jobs\RunWorkflowJob;
use App\Mail\WorkflowActionEmail;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\Log\ExecutionLogWriter;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;
use App\Services\Workflow\WorkflowTemplater;
use App\Services\Workflow\WorkflowValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * A system template on the T1 shape: webhook > condition > email, on the
 * declared handles, with real output keys in the interpolations.
 */
function urgentSupportTemplate(): WorkflowTemplate
{
    return WorkflowTemplate::factory()->system()->create([
        'name' => 'Support client prioritaire',
        'category' => 'Support',
        'graph' => [
            'nodes' => [
                ['key' => 'webhook', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 100, 'positionY' => 200],
                ['key' => 'condition', 'type' => 'logic.condition', 'name' => 'Condition', 'config' => ['expression' => '{{ trigger.type }}', 'operator' => '==', 'value' => 'urgent'], 'positionX' => 420, 'positionY' => 200],
                ['key' => 'email', 'type' => 'action.email', 'name' => 'Email', 'config' => ['to' => '{{ trigger.email }}', 'subject' => 'Demande urgente', 'body' => 'Message : {{ trigger.message }}'], 'positionX' => 740, 'positionY' => 200],
            ],
            'edges' => [
                ['sourceNodeKey' => 'webhook', 'targetNodeKey' => 'condition', 'sourceHandle' => 'out'],
                ['sourceNodeKey' => 'condition', 'targetNodeKey' => 'email', 'sourceHandle' => 'true'],
            ],
        ],
    ]);
}

test('using a system template creates a draft in the current team and redirects to the builder', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->system()->create([
        'name' => 'Support client prioritaire',
        'description' => 'Une description',
    ]);

    $response = $this->actingAs($user)
        ->post(route('templates.use', ['current_team' => $team->slug, 'template' => $template->id]));

    $workflow = Workflow::query()->where('team_id', $team->id)->where('name', 'Support client prioritaire')->firstOrFail();

    $response->assertRedirect(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Workflow created from template.')]);

    $nodes = $workflow->nodes()->get()->keyBy('key');
    $graph = $template->graph;

    expect($workflow->status)->toBe(WorkflowStatus::Draft)
        ->and($workflow->created_by)->toBe($user->id)
        ->and($nodes->keys()->sort()->values()->all())
        ->toBe(collect($graph['nodes'])->pluck('key')->sort()->values()->all());

    foreach ($graph['nodes'] as $node) {
        expect($nodes[$node['key']]->type)->toBe($node['type'])
            ->and($nodes[$node['key']]->config)->toBe($node['config'])
            ->and($nodes[$node['key']]->position_x)->toBe($node['positionX']);
    }

    expect($workflow->edges()->count())->toBe(count($graph['edges']));
});

test('a second use of the same template creates a second workflow', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->system()->create(['name' => 'Homonyme']);

    $this->actingAs($user)->post(route('templates.use', ['current_team' => $team->slug, 'template' => $template->id]));
    $this->actingAs($user)->post(route('templates.use', ['current_team' => $team->slug, 'template' => $template->id]));

    expect(Workflow::query()->where('team_id', $team->id)->where('name', 'Homonyme')->count())->toBe(2);
});

test('a member of another team gets a 404 on a foreign team template (visibleFor scoping)', function () {
    [$user, $team] = teamWithMember();
    $foreign = WorkflowTemplate::factory()->team()->create();

    $response = $this->actingAs($user)
        ->post(route('templates.use', ['current_team' => $team->slug, 'template' => $foreign->id]));

    $response->assertNotFound();
    expect(Workflow::query()->where('team_id', $team->id)->count())->toBe(0);
});

test('a guest is redirected to the login', function () {
    $template = WorkflowTemplate::factory()->system()->create();
    $team = Team::factory()->create();

    $this->post(route('templates.use', ['current_team' => $team->slug, 'template' => $template->id]))
        ->assertRedirect(route('login'));
});

test('an unknown template id is a 404', function () {
    [$user, $team] = teamWithMember();

    $this->actingAs($user)
        ->post(route('templates.use', ['current_team' => $team->slug, 'template' => 999999]))
        ->assertNotFound();
});

test('the workflow instantiated from the webhook template runs to completion', function () {
    Mail::fake();

    [$user, $team] = teamWithMember();
    $template = urgentSupportTemplate();

    $workflow = app(WorkflowTemplater::class)->instantiate($template, $team, $user);

    expect($workflow->webhookEndpoint)->toBeInstanceOf(WebhookEndpoint::class);

    $validator = app(WorkflowValidator::class);
    [$nodes, $edges] = app(WorkflowGraphMapper::class)->map($workflow->refresh());
    expect($validator->validate($nodes, $edges))->toBe([]);

    $workflow->forceFill(['status' => 'active'])->save();

    $execution = WorkflowExecution::factory()->for($workflow)->create([
        'input' => ['type' => 'urgent', 'email' => 'x@example.com', 'message' => 'help'],
    ]);

    (new RunWorkflowJob($execution->id))->handle(
        app(WorkflowRunner::class),
        app(WorkflowGraphMapper::class),
        app(ExecutionLogWriter::class),
    );

    $execution->refresh();

    expect($execution->status->value)->toBe('completed')
        ->and(Mail::assertSent(WorkflowActionEmail::class, 1));
});
