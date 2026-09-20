<?php

use App\Services\Workflow\Log\ExecutionLogMessages;

test('the started line names the trigger', function () {
    expect(ExecutionLogMessages::started('Webhook'))->toBe('Exécution démarrée (déclencheur : Webhook).')
        ->and(ExecutionLogMessages::started('Planifié — cron 0 9 * * *'))
        ->toBe('Exécution démarrée (déclencheur : Planifié — cron 0 9 * * *).');
});

test('the completed node line formats durations below one second in ms', function () {
    expect(ExecutionLogMessages::nodeCompleted('Requête HTTP', 860))->toBe('Node « Requête HTTP » terminé en 860 ms.')
        ->and(ExecutionLogMessages::nodeCompleted('Sortie', 0))->toBe('Node « Sortie » terminé en 0 ms.');
});

test('the completed node line formats seconds with a French comma', function () {
    expect(ExecutionLogMessages::nodeCompleted('Requête HTTP', 4100))->toBe('Node « Requête HTTP » terminé en 4,1 s.');
});

test('the completed node line formats minutes with zero-padded seconds', function () {
    expect(ExecutionLogMessages::nodeCompleted('Requête HTTP', 125000))->toBe('Node « Requête HTTP » terminé en 2 min 05 s.');
});

test('the failed node line embeds the error message', function () {
    expect(ExecutionLogMessages::nodeFailed('Le service distant n’a pas répondu.'))
        ->toBe('Le node a échoué : Le service distant n’a pas répondu.');
});

test('the retry line announces the next attempt', function () {
    expect(ExecutionLogMessages::retryScheduled(2, 2, 30))->toBe('Retry programmé (tentative 2/2 dans 30 s).');
});

test('the lifecycle lines cover completion, failure and cancellation', function () {
    expect(ExecutionLogMessages::completed())->toBe('Exécution terminée avec succès.')
        ->and(ExecutionLogMessages::failed('Node « Requête HTTP » en échec : net down'))
        ->toBe('Exécution échouée : Node « Requête HTTP » en échec : net down')
        ->and(ExecutionLogMessages::failed(null))->toBe('Exécution échouée.')
        ->and(ExecutionLogMessages::cancelled())->toBe('Exécution annulée.');
});
