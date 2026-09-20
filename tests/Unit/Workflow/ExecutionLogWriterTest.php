<?php

use App\Data\Workflow\ExecutionError;
use App\Data\Workflow\NodeRunResult;
use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
use App\Models\WorkflowExecution;
use App\Services\Workflow\Log\ExecutionLogWriter;
use App\Services\Workflow\Log\SecretRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const WRITER_ATTEMPT_START = '2026-09-21 09:00:00';

function logWriter(): ExecutionLogWriter
{
    return new ExecutionLogWriter(new SecretRedactor);
}

/**
 * A two-node graph shape, as the job receives it from the mapper.
 *
 * @return list<array{key: string, type: string, name: string, config: array<string, mixed>}>
 */
function writerNodes(): array
{
    return [
        ['key' => 't', 'type' => 'trigger.manual', 'name' => 'Déclencheur manuel', 'config' => []],
        ['key' => 'o', 'type' => 'data.output', 'name' => 'Sortie', 'config' => []],
    ];
}

test('openAttempt pre-inserts one queued row per node', function () {
    $execution = WorkflowExecution::factory()->create();

    logWriter()->openAttempt($execution, 1, writerNodes(), now());

    $rows = $execution->logs()->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->kind)->toBe(ExecutionLogKind::Node)
        ->and($rows[0]->node_key)->toBe('t')
        ->and($rows[0]->node_type)->toBe('trigger.manual')
        ->and($rows[0]->node_name)->toBe('Déclencheur manuel')
        ->and($rows[0]->attempt)->toBe(1)
        ->and($rows[0]->status)->toBe('queued')
        ->and($rows[0]->offset_ms)->toBe(0)
        ->and($rows[0]->level)->toBe(ExecutionLogLevel::Info)
        ->and($rows[0]->message)->toBeNull()
        ->and($rows[0]->input)->toBeNull()
        ->and($rows[1]->node_key)->toBe('o');
});

test('recordNode updates the pre-inserted row after redaction', function () {
    config([
        'workflows.logs.redacted_keys' => ['api_key'],
        'workflows.logs.redacted_key_suffixes' => [],
    ]);

    Carbon::setTestNow(WRITER_ATTEMPT_START);
    $startedAt = now();

    $execution = WorkflowExecution::factory()->create();
    $writer = logWriter();
    $writer->openAttempt($execution, 1, writerNodes(), $startedAt);

    Carbon::setTestNow('2026-09-21 09:00:01.500');

    $writer->recordNode($execution, new NodeRunResult(
        nodeKey: 't',
        type: 'trigger.manual',
        name: 'Déclencheur manuel',
        status: 'ok',
        durationMs: 860,
        output: ['api_key' => 'sk_live_1', 'user' => 'ana'],
        input: ['api_key' => 'sk_live_1'],
    ), 1, $startedAt);

    $row = $execution->logs()->where('node_key', 't')->sole();

    expect($row->status)->toBe('ok')
        ->and($row->duration_ms)->toBe(860)
        ->and($row->level)->toBe(ExecutionLogLevel::Ok)
        ->and($row->message)->toBe('Node « Déclencheur manuel » terminé en 860 ms.')
        ->and($row->input)->toBe(['api_key' => '[masqué]'])
        ->and($row->output)->toBe(['api_key' => '[masqué]', 'user' => 'ana'])
        ->and($row->error)->toBeNull()
        ->and($row->offset_ms)->toBe(1500);

    Carbon::setTestNow();
});

test('a failing node records the error payload and the error level', function () {
    config([
        'workflows.logs.redacted_keys' => [],
        'workflows.logs.redacted_key_suffixes' => [],
    ]);

    Carbon::setTestNow(WRITER_ATTEMPT_START);
    $startedAt = now();

    $execution = WorkflowExecution::factory()->create();
    $writer = logWriter();
    $writer->openAttempt($execution, 1, writerNodes(), $startedAt);

    $writer->recordNode($execution, new NodeRunResult(
        nodeKey: 'o',
        type: 'data.output',
        name: 'Sortie',
        status: 'error',
        durationMs: 45,
        output: [],
        error: new ExecutionError('o', 'data.output', 'network_error', 'Le service distant n’a pas répondu.'),
        input: ['v' => 1],
    ), 1, $startedAt);

    $row = $execution->logs()->where('node_key', 'o')->sole();

    expect($row->status)->toBe('error')
        ->and($row->level)->toBe(ExecutionLogLevel::Error)
        ->and($row->message)->toBe('Le node a échoué : Le service distant n’a pas répondu.')
        ->and($row->error)->toBe([
            'nodeKey' => 'o',
            'type' => 'data.output',
            'reason' => 'network_error',
            'message' => 'Le service distant n’a pas répondu.',
        ]);

    Carbon::setTestNow();
});

test('a double recordNode keeps exactly one row per node per attempt', function () {
    config([
        'workflows.logs.redacted_keys' => [],
        'workflows.logs.redacted_key_suffixes' => [],
    ]);

    $execution = WorkflowExecution::factory()->create();
    $writer = logWriter();
    $startedAt = now();

    $writer->openAttempt($execution, 1, writerNodes(), $startedAt);

    $nodeRun = new NodeRunResult(
        nodeKey: 't',
        type: 'trigger.manual',
        name: 'Déclencheur manuel',
        status: 'ok',
        durationMs: 12,
        output: [],
        input: [],
    );

    $writer->recordNode($execution, $nodeRun, 1, $startedAt);
    $writer->recordNode($execution, $nodeRun, 1, $startedAt);

    expect($execution->logs()->where('node_key', 't')->count())->toBe(1)
        ->and($execution->logs()->count())->toBe(2);
});

test('closeAttempt turns queued rows into skipped and leaves the others', function () {
    $execution = WorkflowExecution::factory()->create();
    $writer = logWriter();
    $startedAt = now();

    $writer->openAttempt($execution, 1, writerNodes(), $startedAt);

    $writer->recordNode($execution, new NodeRunResult(
        nodeKey: 't',
        type: 'trigger.manual',
        name: 'Déclencheur manuel',
        status: 'ok',
        durationMs: 12,
        output: [],
        input: [],
    ), 1, $startedAt);

    $writer->closeAttempt($execution, 1);

    $rows = $execution->logs()->get()->keyBy('node_key');

    expect($rows['t']->status)->toBe('ok')
        ->and($rows['o']->status)->toBe('skipped');
});

test('recordEvent writes an event row with the offset from the attempt start', function () {
    Carbon::setTestNow(WRITER_ATTEMPT_START);
    $startedAt = now();

    $execution = WorkflowExecution::factory()->create();

    Carbon::setTestNow('2026-09-21 09:00:02.500');

    logWriter()->recordEvent(
        $execution,
        'Exécution démarrée (déclencheur : Manuel).',
        ExecutionLogLevel::Info,
        1,
        $startedAt,
    );

    $row = $execution->logs()->sole();

    expect($row->kind)->toBe(ExecutionLogKind::Event)
        ->and($row->node_key)->toBeNull()
        ->and($row->status)->toBeNull()
        ->and($row->message)->toBe('Exécution démarrée (déclencheur : Manuel).')
        ->and($row->level)->toBe(ExecutionLogLevel::Info)
        ->and($row->offset_ms)->toBe(2500);

    Carbon::setTestNow();
});

test('a JSON column beyond the size cap becomes an omission marker', function () {
    config(['workflows.logs.max_json_bytes' => 64]);

    $execution = WorkflowExecution::factory()->create();
    $writer = logWriter();
    $startedAt = now();

    $writer->openAttempt($execution, 1, [writerNodes()[0]], $startedAt);

    $bigOutput = ['blob' => str_repeat('a', 200)];

    $writer->recordNode($execution, new NodeRunResult(
        nodeKey: 't',
        type: 'trigger.manual',
        name: 'Déclencheur manuel',
        status: 'ok',
        durationMs: 12,
        output: $bigOutput,
        input: [],
    ), 1, $startedAt);

    $row = $execution->logs()->where('node_key', 't')->sole();

    expect($row->output)->toBe([
        '_payload_bytes' => strlen((string) json_encode($bigOutput)),
        '_note' => 'Contenu omis : dépasse la taille maximale de journalisation.',
    ]);
});
