<?php

use App\Data\Workflow\NodeContext;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Trigger\ScheduleHandler;
use Illuminate\Support\Carbon;

test('the handler is registered under its type id', function () {
    $handler = new ScheduleHandler;

    expect($handler->type())->toBe('trigger.schedule');
});

test('a missing cron is refused', function () {
    expect((new ScheduleHandler)->validate([]))->not->toBe([]);
});

test('an invalid cron expression is refused', function () {
    foreach (['not a cron', '* * *', '99 9 * * 1', '0 9 * * 1-99'] as $cron) {
        expect((new ScheduleHandler)->validate(['cron' => $cron]))->not->toBe([]);
    }
});

test('a valid cron expression passes validation', function () {
    foreach (['0 9 * * 1', '*/5 * * * *', '30 14 1 * *', '0 9 * * 1-5'] as $cron) {
        expect((new ScheduleHandler)->validate(['cron' => $cron]))->toBe([]);
    }
});

test('execution exposes the schedule context as trigger output', function () {
    Carbon::setTestNow('2026-09-21 09:00:00');

    $context = new NodeContext(
        nodeKey: 's',
        nodeType: 'trigger.schedule',
        nodeName: 'Planifié',
        config: ['cron' => '0 9 * * 1'],
        input: [],
        execution: new ExecutionContext,
    );

    $result = (new ScheduleHandler)->execute($context);

    expect($result->output)->toBe([
        'triggered_at' => '2026-09-21T09:00:00+00:00',
        'cron' => '0 9 * * 1',
    ]);

    Carbon::setTestNow();
});
