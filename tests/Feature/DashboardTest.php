<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Services\Workflow\ExecutionPresenter;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.id', $invitation->id)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard exposes the exact team KPIs', function () {
    [$user, $team] = teamWithMember();

    Workflow::factory()->for($team)->active()->count(2)->create();
    Workflow::factory()->for($team)->count(2)->create();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->completed()->create(['duration_ms' => 412, 'created_at' => now()->subHours(2)]);
    WorkflowExecution::factory()->for($workflow)->completed()->create(['duration_ms' => 1000, 'created_at' => now()->subHours(3)]);
    WorkflowExecution::factory()->for($workflow)->failed()->create(['created_at' => now()->subHours(4)]);
    WorkflowExecution::factory()->for($workflow)->cancelled()->create(['created_at' => now()->subDays(5)]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('stats', 7)
        ->where('stats.activeWorkflows', 2)
        ->where('stats.totalWorkflows', 5)
        ->where('stats.executions24h', 3)
        ->where('stats.executions7d', 4)
        ->where('stats.successRate7d', 66.7)
        ->where('stats.failures7d', 1)
        ->where('stats.averageDurationMs7d', 706));
});

test('dashboard keeps another team out of the recent lists', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->create();
    WorkflowExecution::factory()->for($workflow)->create();

    $foreignWorkflow = Workflow::factory()->create();
    WorkflowExecution::factory()->for($foreignWorkflow)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recentExecutions', 1)
        ->where('recentExecutions.0.workflow.id', $workflow->id)
        ->has('recentWorkflows', 1)
        ->where('recentWorkflows.0.id', $workflow->id));
});

test('dashboard lists the eight most recent executions with the history projection', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->create();

    $executions = WorkflowExecution::factory()->for($workflow)->count(9)->sequence(
        fn ($sequence) => ['created_at' => now()->subMinutes($sequence->index + 1)],
    )->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recentExecutions', 8)
        ->where('recentExecutions.0', ExecutionPresenter::listItem($executions[0]->fresh()))
        ->where('recentExecutions.7', ExecutionPresenter::listItem($executions[7]->fresh()))
        ->missing('recentExecutions.8'));
});

test('dashboard lists the six most recently updated workflows with their last run', function () {
    [$user, $team] = teamWithMember();

    $old = Workflow::factory()->for($team)->create(['updated_at' => now()->subDays(2)]);
    $recent = Workflow::factory()->for($team)->active()->create(['updated_at' => now()->subHours(3)]);
    WorkflowNode::factory()->for($recent)->count(3)->create();

    $execution = WorkflowExecution::factory()->for($recent)->failed()->create(['created_at' => now()->subHours(5)]);

    Workflow::factory()->for($team)->count(6)->create(['updated_at' => now()->subDays(3)]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recentWorkflows', 6)
        ->where('recentWorkflows.0.id', $recent->id)
        ->where('recentWorkflows.0.status', 'active')
        ->where('recentWorkflows.0.nodesCount', 3)
        ->has('recentWorkflows.0.lastExecution')
        ->where('recentWorkflows.0.lastExecution.status', 'failed')
        ->where('recentWorkflows.0.lastExecution.createdAt', $execution->created_at->toIso8601String())
        ->where('recentWorkflows.1.id', $old->id)
        ->where('recentWorkflows.1.lastExecution', null)
        ->has('recentWorkflows.5')
        ->missing('recentWorkflows.6'));
});

test('dashboard announces analytics as deferred and resolves it on the partial reload', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->create();
    WorkflowExecution::factory()->for($workflow)->completed()->create(['created_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->missing('analytics')
        ->has('stats')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('analytics.daily', 30)
            ->where('analytics.daily.29.date', now()->toDateString())
            ->where('analytics.daily.29.total', 1)
            ->where('analytics.daily.29.completed', 1)
            ->where('analytics.daily.29.failed', 0)
            ->has('analytics.topWorkflows', 1)
            ->where('analytics.topWorkflows.0.id', $workflow->id)
            ->where('analytics.topWorkflows.0.runs', 1)
            ->missing('stats')));
});

test('dashboard stays within a bounded number of business queries', function () {
    [$user, $team] = teamWithMember();

    $workflow = Workflow::factory()->for($team)->create();
    Workflow::factory()->for($team)->count(3)->create();
    WorkflowExecution::factory()->for($workflow)->count(5)->create();

    DB::enableQueryLog();

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk();

    $business = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'workflow'));

    // Expected 5: stats (workflows + executions aggregates), recentExecutions
    // (list + eager workflow names) and recentWorkflows (scalar subselects).
    expect($business)->toHaveCount(5);
});

test('dashboard exposes the team permissions for the shortcuts', function () {
    [$user, $team] = teamWithMember();

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permissions')
            ->where('permissions.canCreateWorkflow', true));
});
