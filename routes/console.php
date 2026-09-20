<?php

use App\Actions\Workflows\DispatchScheduledWorkflows;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('model:prune')->daily();

// One single entry dispatches every due scheduled workflow (phase 7) — the
// cron expressions live on the trigger.schedule nodes, not in this file.
Schedule::call(fn () => app(DispatchScheduledWorkflows::class)->handle())
    ->everyMinute()
    ->name('workflow-schedule-triggers')
    ->withoutOverlapping()
    ->description('Dispatch due scheduled workflow runs');
