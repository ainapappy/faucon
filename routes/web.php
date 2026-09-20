<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Integrations\TestIntegrationConnectionController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Workflows\RegenerateWebhookTokenController;
use App\Http\Controllers\Workflows\RunWorkflowController;
use App\Http\Controllers\Workflows\SaveWorkflowGraphController;
use App\Http\Controllers\Workflows\TestRunWorkflowController;
use App\Http\Controllers\Workflows\WebhookController;
use App\Http\Controllers\Workflows\WebhookUrlController;
use App\Http\Controllers\Workflows\WorkflowController;
use App\Http\Controllers\Workflows\WorkflowExecution\CancelWorkflowExecutionController;
use App\Http\Controllers\Workflows\WorkflowExecution\WorkflowExecutionController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('workflows', [WorkflowController::class, 'index'])->name('workflows.index');
        Route::post('workflows', [WorkflowController::class, 'store'])->name('workflows.store');
        Route::get('workflows/{workflow}/edit', [WorkflowController::class, 'edit'])->name('workflows.edit');
        Route::patch('workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
        Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate'])->name('workflows.duplicate');
        Route::put('workflows/{workflow}/graph', SaveWorkflowGraphController::class)->name('workflows.graph.update');
        Route::post('workflows/{workflow}/test-run', TestRunWorkflowController::class)->name('workflows.test-run');
        Route::post('workflows/{workflow}/run', RunWorkflowController::class)->name('workflows.run');

        Route::get('workflow-executions', [WorkflowExecutionController::class, 'index'])->name('workflow-executions.index');
        Route::post('workflow-executions/{execution}/cancel', CancelWorkflowExecutionController::class)->name('workflow-executions.cancel');

        Route::get('workflows/{workflow}/webhook-url', WebhookUrlController::class)->name('workflows.webhook.url');
        Route::post('workflows/{workflow}/webhook-regenerate', RegenerateWebhookTokenController::class)->name('workflows.webhook.regenerate');

        Route::get('settings/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::post('settings/integrations', [IntegrationController::class, 'store'])->name('integrations.store');
        Route::patch('settings/integrations/{integration}', [IntegrationController::class, 'update'])->name('integrations.update');
        Route::delete('settings/integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
        Route::post('settings/integrations/{integration}/test', TestIntegrationConnectionController::class)->name('integrations.test');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::post('webhooks/{token}', WebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.handle');

require __DIR__.'/settings.php';
