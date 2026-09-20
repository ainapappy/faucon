<?php

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Notifications\ExecutionFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Send a real failure notification to the user (database channel) and return
 * the stored notification.
 */
function failedNotificationFor(User $user): DatabaseNotification
{
    $workflow = Workflow::factory()->create();
    $execution = WorkflowExecution::factory()->for($workflow)->failed()->create();

    $user->notify(new ExecutionFailedNotification($execution->refresh()));

    return $user->notifications()->firstOrFail();
}

/**
 * Store the notification back in time (ordering fixture).
 */
function aged(DatabaseNotification $notification, int $minutesAgo): DatabaseNotification
{
    $notification->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

    return $notification->refresh();
}

test('guests are redirected to the login page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->patch(route('notifications.read', ['notification' => '00000000-0000-0000-0000-000000000000']))->assertRedirect(route('login'));
    $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
});

test('the history is paginated by fifteen per page', function () {
    $user = User::factory()->create();

    foreach (range(1, 20) as $index) {
        aged(failedNotificationFor($user), $index);
    }

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 15)
            ->where('notifications.total', 20));
});

test('the history projects items with a short type and an exact read flag', function () {
    $user = User::factory()->create();

    $unread = aged(failedNotificationFor($user), 2);
    $read = aged(failedNotificationFor($user), 1);
    $read->markAsRead();

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.id', $read->id)
            ->where('notifications.data.0.type', 'execution_failed')
            ->where('notifications.data.0.read', true)
            ->where('notifications.data.1.id', $unread->id)
            ->where('notifications.data.1.type', 'execution_failed')
            ->where('notifications.data.1.read', false)
            ->has('notifications.data.0.data.workflowName')
            ->has('notifications.data.0.data.executionId')
            ->has('notifications.data.0.data.teamSlug')
            ->missing('notifications.data.0.data.input')
            ->missing('notifications.data.0.data.result'));
});

test('the history never exposes another user notifications', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    failedNotificationFor($alice);
    failedNotificationFor($bob);
    failedNotificationFor($bob);

    $this->actingAs($alice)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.total', 1));

    $this->actingAs($bob)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 2)
            ->where('notifications.total', 2));
});

test('marking a notification read sets read_at and stays idempotent', function () {
    $user = User::factory()->create();

    $notification = failedNotificationFor($user);

    $route = route('notifications.read', ['notification' => $notification->id]);

    $this->actingAs($user)->patch($route)->assertRedirect();

    $notification->refresh();

    expect($notification->read_at)->not->toBeNull();

    $firstReadAt = $notification->read_at;

    $this->actingAs($user)->patch($route)->assertRedirect();

    $notification->refresh();

    expect($notification->read_at->equalTo($firstReadAt))->toBeTrue()
        ->and($user->unreadNotifications()->count())->toBe(0);
});

test('marking another user notification read returns 404', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $bobs = failedNotificationFor($bob);

    $this->actingAs($alice)
        ->patch(route('notifications.read', ['notification' => $bobs->id]))
        ->assertNotFound();

    expect($bobs->refresh()->read_at)->toBeNull();

    $this->actingAs($alice)
        ->patch(route('notifications.read', ['notification' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

test('read all marks every unread notification and leaves the read ones untouched', function () {
    $user = User::factory()->create();

    $oldRead = aged(failedNotificationFor($user), 60);
    $oldRead->markAsRead();
    $oldRead->refresh();
    $firstReadAt = $oldRead->read_at;

    $recentRead = failedNotificationFor($user);
    $recentRead->markAsRead();
    $recentRead->refresh();
    $secondReadAt = $recentRead->read_at;

    aged(failedNotificationFor($user), 3);
    aged(failedNotificationFor($user), 2);
    aged(failedNotificationFor($user), 1);

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($user->notifications()->count())->toBe(5)
        ->and($oldRead->refresh()->read_at->equalTo($firstReadAt))->toBeTrue()
        ->and($recentRead->refresh()->read_at->equalTo($secondReadAt))->toBeTrue();
});

test('the shared root prop exposes the exact unread count and the five most recent unread', function () {
    $user = User::factory()->create();

    aged(failedNotificationFor($user), 9);
    $old = aged(failedNotificationFor($user), 8);
    $old->markAsRead(); // read → never in the bell
    foreach (range(1, 7) as $index) {
        aged(failedNotificationFor($user), $index);
    }

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            // The page prop (paginator) overrides the shared root prop there.
            ->has('notifications.data')
            ->missing('notifications.unreadCount'));

    $this->actingAs($user)->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 8)
            ->has('notifications.recent', 5)
            ->missing('notifications.recent.0.read')
            ->has('notifications.recent.0.id')
            ->has('notifications.recent.0.type')
            ->has('notifications.recent.0.data')
            ->has('notifications.recent.0.createdAt'));
});

test('the shared prop recent list is ordered most recent first and read only', function () {
    $user = User::factory()->create();

    $oldest = aged(failedNotificationFor($user), 30);
    $middle = aged(failedNotificationFor($user), 20);
    $newest = aged(failedNotificationFor($user), 10);

    $this->actingAs($user)->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 3)
            ->where('notifications.recent.0.id', $newest->id)
            ->where('notifications.recent.1.id', $middle->id)
            ->where('notifications.recent.2.id', $oldest->id));
});
