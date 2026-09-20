<?php

use App\Models\Team;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\Workflow\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A completed and a failed execution on the same day, with explicit timings.
 *
 * @return list<WorkflowExecution>
 */
function completedAndFailedToday(Workflow $workflow): array
{
    return [
        WorkflowExecution::factory()->for($workflow)->completed()->create(['duration_ms' => 412]),
        WorkflowExecution::factory()->for($workflow)->failed()->create(['duration_ms' => 980]),
    ];
}

test('counts active and total workflows of the team', function () {
    [$metrics, $team] = metricsForTeam();

    Workflow::factory()->for($team)->count(3)->active()->create();
    Workflow::factory()->for($team)->count(2)->create();

    $stats = $metrics->stats($team);

    expect($stats->activeWorkflows)->toBe(3)
        ->and($stats->totalWorkflows)->toBe(5);
});

test('counts executions within 24 hours and within 7 days', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->count(2)->create(['created_at' => now()->subHours(2)]);
    WorkflowExecution::factory()->for($workflow)->count(3)->create(['created_at' => now()->subDays(5)]);

    $stats = $metrics->stats($team);

    expect($stats->executions24h)->toBe(2)
        ->and($stats->executions7d)->toBe(5);
});

test('computes the 7-day success rate and the failure count', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->count(8)->completed()->create();
    WorkflowExecution::factory()->for($workflow)->count(2)->failed()->create();

    $stats = $metrics->stats($team);

    expect($stats->successRate7d)->toBe(80.0)
        ->and($stats->failures7d)->toBe(2);
});

test('excludes cancelled executions from the success rate denominator', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->count(2)->completed()->create();
    WorkflowExecution::factory()->for($workflow)->failed()->create();
    WorkflowExecution::factory()->for($workflow)->cancelled()->create();

    $stats = $metrics->stats($team);

    expect($stats->successRate7d)->toBe(66.7);
});

test('returns null success rate and null average duration when nothing finished', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->running()->create();

    $stats = $metrics->stats($team);

    expect($stats->successRate7d)->toBeNull()
        ->and($stats->averageDurationMs7d)->toBeNull();
});

test('averages duration over completed executions only', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->completed()->create(['duration_ms' => 412]);
    WorkflowExecution::factory()->for($workflow)->completed()->create(['duration_ms' => 980]);
    WorkflowExecution::factory()->for($workflow)->failed()->create(['duration_ms' => 9999]);

    $stats = $metrics->stats($team);

    expect($stats->averageDurationMs7d)->toBe(696);
});

test('includes an execution exactly 7 days old and excludes an older one', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->create(['created_at' => now()->subDays(7)]);
    WorkflowExecution::factory()->for($workflow)->create(['created_at' => now()->subDays(7)->subSecond()]);

    $stats = $metrics->stats($team);

    expect($stats->executions7d)->toBe(1);
});

test('includes an execution exactly 24 hours old and excludes an older one', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->create(['created_at' => now()->subDay()]);
    WorkflowExecution::factory()->for($workflow)->create(['created_at' => now()->subDay()->subSecond()]);

    $stats = $metrics->stats($team);

    expect($stats->executions24h)->toBe(1);
});

test('never counts another team workflows or executions', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->active()->create();
    WorkflowExecution::factory()->for($workflow)->completed()->create();

    $foreignTeam = Team::factory()->create();
    $foreignWorkflow = Workflow::factory()->for($foreignTeam)->active()->create();
    WorkflowExecution::factory()->for($foreignWorkflow)->failed()->create();

    $stats = $metrics->stats($team);

    expect($stats->activeWorkflows)->toBe(1)
        ->and($stats->totalWorkflows)->toBe(1)
        ->and($stats->executions7d)->toBe(1)
        ->and($stats->failures7d)->toBe(0)
        ->and($stats->successRate7d)->toBe(100.0);
});

test('builds a contiguous 30-day series with zero-filled gaps', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    completedAndFailedToday($workflow);
    WorkflowExecution::factory()->for($workflow)->completed()->create(['created_at' => now()->subDays(10)]);

    $analytics = $metrics->analytics($team);

    expect($analytics->daily)->toHaveCount(30)
        ->and($analytics->daily[29]['date'])->toBe(now()->toDateString())
        ->and($analytics->daily[29]['total'])->toBe(2)
        ->and($analytics->daily[29]['completed'])->toBe(1)
        ->and($analytics->daily[29]['failed'])->toBe(1)
        ->and($analytics->daily[19]['date'])->toBe(now()->subDays(10)->toDateString())
        ->and($analytics->daily[19]['total'])->toBe(1)
        ->and($analytics->daily[0]['date'])->toBe(now()->subDays(29)->toDateString())
        ->and($analytics->daily[0]['total'])->toBe(0);
});

test('never includes another team executions in the daily series', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    completedAndFailedToday($workflow);

    $foreignWorkflow = Workflow::factory()->create();
    completedAndFailedToday($foreignWorkflow);

    $analytics = $metrics->analytics($team);

    expect($analytics->daily[29]['total'])->toBe(2)
        ->and($analytics->daily[29]['failed'])->toBe(1);
});

test('ranks top workflows by runs, limited to five, deterministic on ties', function () {
    [$metrics, $team] = metricsForTeam();
    $runs = Workflow::factory()->for($team)->count(3)->create()->sortDesc()->values();

    $first = $runs[0];
    $second = $runs[1];
    $third = $runs[2];

    WorkflowExecution::factory()->for($first)->count(3)->create();
    WorkflowExecution::factory()->for($second)->count(3)->create();
    WorkflowExecution::factory()->for($third)->count(1)->create();
    Workflow::factory()->for($team)->count(2)->create(); // never executed

    $analytics = $metrics->analytics($team);

    expect($analytics->topWorkflows)->toHaveCount(3)
        ->and($analytics->topWorkflows[0]['runs'])->toBe(3)
        ->and($analytics->topWorkflows[1]['runs'])->toBe(3)
        ->and($analytics->topWorkflows[0]['name'])->toBeLessThan($analytics->topWorkflows[1]['name'])
        ->and($analytics->topWorkflows[2]['runs'])->toBe(1)
        ->and($analytics->topWorkflows[2]['id'])->toBe($third->id);
});

test('excludes soft-deleted workflows from the top workflows', function () {
    [$metrics, $team] = metricsForTeam();
    $workflow = Workflow::factory()->for($team)->create();

    WorkflowExecution::factory()->for($workflow)->count(2)->create();

    $workflow->delete();

    $analytics = $metrics->analytics($team);

    expect($analytics->topWorkflows)->toBe([]);
});

test('keeps the analytics of another team out of the top workflows', function () {
    [$metrics, $team] = metricsForTeam();
    $foreignWorkflow = Workflow::factory()->create();

    WorkflowExecution::factory()->for($foreignWorkflow)->count(2)->create();

    $analytics = $metrics->analytics($team);

    expect($analytics->topWorkflows)->toBe([]);
});

/**
 * The metrics service under test, bound to a fresh team.
 *
 * @return array{DashboardMetrics, Team}
 */
function metricsForTeam(): array
{
    Carbon::setTestNow('2026-09-20 12:00:00');

    return [app(DashboardMetrics::class), Team::factory()->create()];
}
