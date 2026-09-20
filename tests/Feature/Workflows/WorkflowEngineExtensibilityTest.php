<?php

use App\Actions\Workflows\SaveWorkflowGraph;
use App\Actions\Workflows\TestRunWorkflow;
use App\Enums\WorkflowStatus;
use App\Mail\WorkflowActionEmail;
use App\Models\Workflow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('a webhook to http to email graph runs end to end with real handlers and no engine change', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create(['status' => WorkflowStatus::Active]);

    Http::preventStrayRequests();
    Http::fake(['https://203.0.113.10/*' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json'])]);
    Mail::fake();

    app(SaveWorkflowGraph::class)->handle($workflow, [
        ['key' => 'n1', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 0, 'positionY' => 0],
        ['key' => 'n2', 'type' => 'action.http', 'name' => 'Requête HTTP', 'config' => [
            'method' => 'POST',
            'url' => 'https://203.0.113.10/relay',
            'body' => '{{ trigger.event }}',
        ], 'positionX' => 0, 'positionY' => 0],
        ['key' => 'n3', 'type' => 'action.email', 'name' => 'Email', 'config' => [
            'to' => '{{ trigger.email }}',
            'subject' => 'Événement {{ trigger.event }}',
            'body' => 'Reçu : {{ trigger.event }} pour {{ trigger.email }}.',
        ], 'positionX' => 0, 'positionY' => 0],
    ], [
        ['sourceNodeKey' => 'n1', 'targetNodeKey' => 'n2', 'sourceHandle' => 'out'],
        ['sourceNodeKey' => 'n2', 'targetNodeKey' => 'n3', 'sourceHandle' => 'out'],
    ]);

    $result = app(TestRunWorkflow::class)->handle($workflow->fresh(), [
        'event' => 'order.created',
        'email' => 'client@example.com',
    ]);

    $nodes = collect($result->nodes);

    expect($result->status)->toBe('completed')
        ->and($result->errors)->toBe([])
        ->and($nodes->where('status', 'ok'))->toHaveCount(3)
        ->and($nodes->firstWhere('nodeKey', 'n2')?->output)->toBe(['status' => 200, 'body' => ['ok' => true]])
        ->and($nodes->firstWhere('nodeKey', 'n3')?->output)->toBe(['sent' => true, 'to' => 'client@example.com']);

    Http::assertSentCount(1);
    Mail::assertSent(WorkflowActionEmail::class, function (WorkflowActionEmail $mail): bool {
        return $mail->hasTo('client@example.com') && $mail->hasSubject('Événement order.created');
    });
});
