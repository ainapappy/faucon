<?php

use App\Data\Workflow\ExecutionError;
use App\Enums\TemplateOrigin;
use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\Exception\TemplateNotPublishableException;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowTemplater;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function templater(): WorkflowTemplater
{
    return app(WorkflowTemplater::class);
}

/**
 * A webhook → condition → email workflow (the T1 shape) with faithful
 * nodes/edges, ready to be snapshotted or duplicated.
 */
function templateSourceWorkflow(Team $team, string $name = 'Source'): Workflow
{
    $workflow = Workflow::factory()->for($team)->create([
        'name' => $name,
        'description' => 'Une description',
    ]);

    $webhook = WorkflowNode::factory()->for($workflow)->ofType('trigger.webhook')->keyed('webhook')->at(100, 200)->withConfig([])->create();
    $condition = WorkflowNode::factory()->for($workflow)->ofType('logic.condition')->keyed('condition')->at(420, 200)->withConfig(['expression' => '{{ trigger.type }}', 'operator' => '==', 'value' => 'urgent'])->create();
    $email = WorkflowNode::factory()->for($workflow)->ofType('action.email')->keyed('email')->at(740, 200)->withConfig(['to' => '{{ trigger.email }}', 'subject' => 'Demande urgente', 'body' => '{{ trigger.message }}'])->create();

    WorkflowEdge::factory()->for($workflow)->between($webhook, $condition, 'out')->create();
    WorkflowEdge::factory()->for($workflow)->between($condition, $email, 'true')->create();

    return $workflow;
}

/**
 * Overwrite the graph of a template with a webhook → email snapshot.
 */
function seedWebhookTemplateGraph(WorkflowTemplate $template): void
{
    $template->forceFill(['graph' => [
        'nodes' => [
            ['key' => 'webhook', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 100, 'positionY' => 200],
            ['key' => 'email', 'type' => 'action.email', 'name' => 'Email', 'config' => ['to' => '{{ trigger.email }}'], 'positionX' => 420, 'positionY' => 200],
        ],
        'edges' => [
            ['sourceNodeKey' => 'webhook', 'targetNodeKey' => 'email', 'sourceHandle' => 'out'],
        ],
    ]])->save();
}

test('instantiate creates a faithful draft named after the template', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->team()->create([
        'team_id' => $team->id,
        'name' => 'Support client prioritaire',
        'description' => 'Une description',
    ]);

    $workflow = templater()->instantiate($template, $team, $user);

    expect($workflow->status)->toBe(WorkflowStatus::Draft)
        ->and($workflow->team_id)->toBe($team->id)
        ->and($workflow->created_by)->toBe($user->id)
        ->and($workflow->name)->toBe('Support client prioritaire')
        ->and($workflow->description)->toBe('Une description');

    $graph = $template->graph;

    $nodes = $workflow->nodes()->get()->keyBy('key');
    expect($nodes->keys()->sort()->values()->all())->toBe(collect($graph['nodes'])->pluck('key')->sort()->values()->all());

    foreach ($graph['nodes'] as $node) {
        $created = $nodes[$node['key']];
        expect($created->type)->toBe($node['type'])
            ->and($created->name)->toBe($node['name'])
            ->and($created->config)->toBe($node['config'])
            ->and($created->position_x)->toBe($node['positionX'])
            ->and($created->position_y)->toBe($node['positionY']);
    }

    $edges = $workflow->edges()->get()->map(fn (WorkflowEdge $edge): array => [$edge->source_node_key, $edge->target_node_key, $edge->source_handle])->all();
    expect($edges)->toBe(array_map(fn (array $edge): array => [$edge['sourceNodeKey'], $edge['targetNodeKey'], $edge['sourceHandle']], $graph['edges']));
});

test('instantiate from a system template works the same way', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->system()->create(['name' => 'Veille du matin']);

    $workflow = templater()->instantiate($template, $team, $user);

    expect($workflow->team_id)->toBe($team->id)
        ->and($workflow->status)->toBe(WorkflowStatus::Draft)
        ->and($workflow->nodes()->count())->toBe(2)
        ->and($workflow->edges()->count())->toBe(1);
});

test('a second instantiation of the same template creates a sibling workflow', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id, 'name' => 'Homonyme']);

    $first = templater()->instantiate($template, $team, $user);
    $second = templater()->instantiate($template, $team, $user);

    expect($first->id)->not->toBe($second->id)
        ->and($second->name)->toBe('Homonyme')
        ->and(Workflow::query()->where('team_id', $team->id)->count())->toBe(2);
});

test('instantiate attaches a webhook endpoint when the template has a webhook trigger', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);
    seedWebhookTemplateGraph($template);

    $workflow = templater()->instantiate($template, $team, $user);

    expect($workflow->webhookEndpoint)->toBeInstanceOf(WebhookEndpoint::class);
});

test('instantiate creates no endpoint without a webhook node', function () {
    [$user, $team] = teamWithMember();
    $template = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);

    $workflow = templater()->instantiate($template, $team, $user);

    expect($workflow->webhookEndpoint)->toBeNull()
        ->and(WebhookEndpoint::query()->count())->toBe(0);
});

test('duplicate copies the workflow as a draft without mutating the original', function () {
    [$user, $team] = teamWithMember();
    $original = templateSourceWorkflow($team, 'Original');

    $copy = templater()->duplicate($original, $user);

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->name)->toBe('Original (copie)')
        ->and($copy->description)->toBe('Une description')
        ->and($copy->status)->toBe(WorkflowStatus::Draft)
        ->and($copy->team_id)->toBe($team->id)
        ->and($copy->created_by)->toBe($user->id);

    $originalNodes = $original->nodes()->get()->keyBy('key');
    $copyNodes = $copy->nodes()->get()->keyBy('key');
    expect($copyNodes->keys()->all())->toBe($originalNodes->keys()->all());

    foreach ($originalNodes as $key => $node) {
        expect($copyNodes[$key]->type)->toBe($node->type)
            ->and($copyNodes[$key]->config)->toBe($node->config)
            ->and($copyNodes[$key]->position_x)->toBe($node->position_x)
            ->and($copyNodes[$key]->position_y)->toBe($node->position_y);
    }

    $originalEdges = $original->edges()->get()->map(fn (WorkflowEdge $edge): array => [$edge->source_node_key, $edge->target_node_key, $edge->source_handle])->all();
    $copyEdges = $copy->edges()->get()->map(fn (WorkflowEdge $edge): array => [$edge->source_node_key, $edge->target_node_key, $edge->source_handle])->all();
    expect($copyEdges)->toBe($originalEdges)
        ->and($original->nodes()->count())->toBe(3)
        ->and($original->edges()->count())->toBe(2);
});

test('duplicate also ensures the webhook endpoint', function () {
    [$user, $team] = teamWithMember();
    $original = templateSourceWorkflow($team);

    $copy = templater()->duplicate($original, $user);

    expect($copy->webhookEndpoint)->toBeInstanceOf(WebhookEndpoint::class)
        ->and(WebhookEndpoint::query()->where('workflow_id', $copy->id)->count())->toBe(1)
        ->and(WebhookEndpoint::query()->where('workflow_id', $original->id)->count())->toBe(0);
});

test('publish snapshots the validated workflow into a team template', function () {
    [$user, $team] = teamWithMember();
    $workflow = templateSourceWorkflow($team);

    $template = templater()->publish($workflow, $user, 'Mon template', 'Ma description', 'Ventes');

    expect($template->origin)->toBe(TemplateOrigin::Team)
        ->and($template->team_id)->toBe($team->id)
        ->and($template->created_by)->toBe($user->id)
        ->and($template->name)->toBe('Mon template')
        ->and($template->description)->toBe('Ma description')
        ->and($template->category)->toBe('Ventes')
        ->and($template->graph)->toBe(app(WorkflowGraphMapper::class)->snapshot($workflow->refresh()))
        ->and($workflow->nodes()->count())->toBe(3)
        ->and($workflow->edges()->count())->toBe(2);
});

test('a republish creates a second template, never an upsert', function () {
    [$user, $team] = teamWithMember();
    $workflow = templateSourceWorkflow($team);

    templater()->publish($workflow, $user, 'Mon template', null, 'Ventes');
    templater()->publish($workflow, $user, 'Mon template', null, 'Ventes');

    expect(WorkflowTemplate::query()->where('team_id', $team->id)->count())->toBe(2);
});

test('publish refuses a non executable workflow and creates nothing', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('data.input')->keyed('entree')->create();

    $exception = null;

    try {
        templater()->publish($workflow, $user, 'Mon template', null, 'Ventes');
    } catch (TemplateNotPublishableException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(TemplateNotPublishableException::class)
        ->and($exception->errors)->not->toBeEmpty()
        ->and($exception->errors[0])->toBeInstanceOf(ExecutionError::class)
        ->and($exception->firstMessage())->toBe($exception->errors[0]->message)
        ->and(WorkflowTemplate::query()->count())->toBe(0);
});

test('publish refuses an invalid cron config and creates nothing', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();
    WorkflowNode::factory()->for($workflow)->ofType('trigger.schedule')->keyed('cron')->withConfig(['cron' => 'invalid cron'])->create();

    expect(fn (): WorkflowTemplate => templater()->publish($workflow, $user, 'T', null, 'Données'))
        ->toThrow(TemplateNotPublishableException::class);

    expect(WorkflowTemplate::query()->count())->toBe(0);
});

test('snapshot and fromSnapshot round-trip the graph faithfully', function () {
    [$user, $team] = teamWithMember();
    $workflow = templateSourceWorkflow($team);

    $mapper = app(WorkflowGraphMapper::class);
    $snapshot = $mapper->snapshot($workflow);

    expect(array_keys($snapshot))->toBe(['nodes', 'edges']);

    foreach ($snapshot['nodes'] as $node) {
        expect(array_keys($node))->toBe(['key', 'type', 'name', 'config', 'positionX', 'positionY'])
            ->and($node['positionX'])->toBeInt()
            ->and($node['positionY'])->toBeInt();
    }

    foreach ($snapshot['edges'] as $edge) {
        expect(array_keys($edge))->toBe(['sourceNodeKey', 'targetNodeKey', 'sourceHandle']);
    }

    [$nodes, $edges] = WorkflowGraphMapper::fromSnapshot($snapshot);

    $mapped = $mapper->map($workflow->refresh());

    expect($nodes)->toBe($mapped[0])
        ->and($edges)->toBe($mapped[1]);
});
