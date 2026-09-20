<?php

use App\Enums\TeamRole;
use App\Enums\WorkflowStatus;
use App\Models\Integration;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\Workflow\NodeCatalog;
use App\Services\Workflow\NodeHandlerRegistry;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DemoWorkflowSeeder;
use Illuminate\Support\Facades\Hash;

test('seeding creates the demo user with its team and membership', function () {
    $this->seed();

    $user = User::query()->where('email', DemoUserSeeder::DemoEmail)->first();
    $team = Team::query()->where('slug', DemoUserSeeder::TeamSlug)->first();

    expect($user)->not->toBeNull()
        ->and($team)->not->toBeNull()
        ->and($team->name)->toBe(DemoUserSeeder::TeamName)
        ->and($team->is_personal)->toBeTrue()
        ->and($user->current_team_id)->toBe($team->id)
        ->and($user->teamRole($team))->toBe(TeamRole::Owner)
        ->and(Hash::check(DemoUserSeeder::DemoPassword, $user->password))->toBeTrue();
});

test('seeding creates the three demo workflows with valid graphs', function () {
    $this->seed();

    $team = Team::query()->where('slug', DemoUserSeeder::TeamSlug)->firstOrFail();
    $registry = app(NodeHandlerRegistry::class);

    $expected = [
        DemoWorkflowSeeder::TriageWorkflow => ['nodes' => 6, 'edges' => 5, 'status' => WorkflowStatus::Active],
        DemoWorkflowSeeder::SummaryWorkflow => ['nodes' => 3, 'edges' => 2, 'status' => WorkflowStatus::Draft],
        DemoWorkflowSeeder::WebhookExtractionWorkflow => ['nodes' => 4, 'edges' => 3, 'status' => WorkflowStatus::Active],
    ];

    expect($team->workflows()->count())->toBe(3);

    foreach ($expected as $name => $expectations) {
        $workflow = Workflow::query()
            ->where('team_id', $team->id)
            ->where('name', $name)
            ->first();

        expect($workflow)->not->toBeNull()
            ->and($workflow->status)->toBe($expectations['status'])
            ->and($workflow->nodes)->toHaveCount($expectations['nodes'])
            ->and($workflow->edges)->toHaveCount($expectations['edges']);

        foreach ($workflow->nodes as $node) {
            $handler = $registry->forType($node->type);

            expect(NodeCatalog::has($node->type))->toBeTrue()
                ->and($handler)->not->toBeNull()
                ->and($handler->validate($node->config ?? []))->toBe([]);
        }
    }
});

test('the condition branches of the triage workflow are wired on the right handles', function () {
    $this->seed();

    $team = Team::query()->where('slug', DemoUserSeeder::TeamSlug)->firstOrFail();
    $workflow = $team->workflows()->where('name', DemoWorkflowSeeder::TriageWorkflow)->firstOrFail();

    $handles = $workflow->edges
        ->mapWithKeys(fn (WorkflowEdge $edge): array => [$edge->target_node_key => $edge->source_handle]);

    expect($handles['reponse'])->toBe('true')
        ->and($handles['sortie-autre'])->toBe('false')
        ->and($handles['sortie-lead'])->toBe('out');
});

test('seeding creates the demo webhook endpoint with the fixed token', function () {
    $this->seed();

    $endpoint = WebhookEndpoint::query()
        ->where('token_hash', WebhookEndpoint::hashToken(DemoWorkflowSeeder::WebhookToken))
        ->first();

    $team = Team::query()->where('slug', DemoUserSeeder::TeamSlug)->firstOrFail();
    $workflow = $team->workflows()->where('name', DemoWorkflowSeeder::WebhookExtractionWorkflow)->firstOrFail();

    expect($endpoint)->not->toBeNull()
        ->and($endpoint->workflow_id)->toBe($workflow->id)
        ->and($endpoint->token)->toBe(DemoWorkflowSeeder::WebhookToken)
        ->and(mb_strlen(DemoWorkflowSeeder::WebhookToken))->toBe(48);
});

test('seeding creates the two demo integrations', function () {
    $this->seed();

    $team = Team::query()->where('slug', DemoUserSeeder::TeamSlug)->firstOrFail();

    $http = Integration::query()
        ->where('team_id', $team->id)
        ->where('type', 'generic_http')
        ->where('name', 'API Exemple')
        ->first();

    $smtp = Integration::query()
        ->where('team_id', $team->id)
        ->where('type', 'smtp')
        ->where('name', 'SMTP Local (Mailpit)')
        ->first();

    expect($http)->not->toBeNull()
        ->and($http->credentials)->toBe(['baseUrl' => 'https://api.exemple.com', 'auth' => 'none'])
        ->and($smtp)->not->toBeNull()
        ->and($smtp->credentials)->toBe(['host' => '127.0.0.1', 'port' => 1025, 'encryption' => null]);
});

test('a second seeding run duplicates nothing', function () {
    $this->seed();

    $counts = [
        'users' => User::query()->count(),
        'teams' => Team::query()->count(),
        'memberships' => Membership::query()->count(),
        'workflows' => Workflow::query()->count(),
        'nodes' => WorkflowNode::query()->count(),
        'edges' => WorkflowEdge::query()->count(),
        'integrations' => Integration::query()->count(),
        'endpoints' => WebhookEndpoint::query()->count(),
    ];

    $this->seed();

    expect(User::query()->count())->toBe($counts['users'])
        ->and(Team::query()->count())->toBe($counts['teams'])
        ->and(Membership::query()->count())->toBe($counts['memberships'])
        ->and(Workflow::query()->count())->toBe($counts['workflows'])
        ->and(WorkflowNode::query()->count())->toBe($counts['nodes'])
        ->and(WorkflowEdge::query()->count())->toBe($counts['edges'])
        ->and(Integration::query()->count())->toBe($counts['integrations'])
        ->and(WebhookEndpoint::query()->count())->toBe($counts['endpoints'])
        ->and($counts['users'])->toBe(1)
        ->and($counts['teams'])->toBe(1)
        ->and($counts['workflows'])->toBe(3)
        ->and($counts['nodes'])->toBe(13)
        ->and($counts['edges'])->toBe(10)
        ->and($counts['integrations'])->toBe(2)
        ->and($counts['endpoints'])->toBe(1);
});
