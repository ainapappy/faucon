<?php

use App\Models\Workflow;
use App\Services\Workflow\NodeCatalog;
use App\Services\Workflow\WorkflowRunner;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Assert as PHPUnitAssert;

test('a saved classification workflow runs end to end through the true branch', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [
                ['key' => 'n1', 'type' => 'trigger.manual'],
                ['key' => 'n2', 'type' => 'ai.classification', 'config' => [
                    'model' => 'fake/demo',
                    'prompt' => 'Classe : {{ trigger.message }}',
                    'labels' => 'lead, spam',
                ]],
                ['key' => 'n3', 'type' => 'logic.condition', 'config' => [
                    'expression' => '{{ n2.label }}',
                    'operator' => '==',
                    'value' => 'lead',
                ]],
                ['key' => 'n4', 'type' => 'data.output'],
            ],
            edges: [
                ['source' => 'n1', 'target' => 'n2', 'handle' => 'out'],
                ['source' => 'n2', 'target' => 'n3', 'handle' => 'out'],
                ['source' => 'n3', 'target' => 'n4', 'handle' => 'true'],
            ],
        ))->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{"message":"message client"}',
        ]);

    $response->assertOk();

    $payload = $response->json();
    $nodes = collect($payload['nodes'])->keyBy(fn (array $node) => $node['nodeKey']);

    expect($payload['status'])->toBe('completed')
        ->and($payload['errors'])->toBe([])
        ->and($nodes['n1']['status'])->toBe('ok')
        ->and($nodes['n2']['status'])->toBe('ok')
        ->and($nodes['n3']['status'])->toBe('ok')
        ->and($nodes['n4']['status'])->toBe('ok')
        ->and($nodes['n2']['output']['label'])->toBe('lead')
        ->and($nodes['n2']['output']['usage'])->toBe(['prompt_tokens' => 0, 'completion_tokens' => 0])
        ->and($nodes['n3']['output'])->toBe(['branch' => 'true', 'value' => true])
        ->and($nodes['n4']['output'])->toBe(['value' => ['branch' => 'true', 'value' => true]]);
});

test('each of the five ai modes completes a run with the fake provider', function (string $type, array $config) {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), graphPayload(
            nodes: [
                ['key' => 'n1', 'type' => 'trigger.manual'],
                ['key' => 'n2', 'type' => $type, 'config' => $config],
                ['key' => 'n3', 'type' => 'data.output'],
            ],
            edges: [
                ['source' => 'n1', 'target' => 'n2', 'handle' => 'out'],
                ['source' => 'n2', 'target' => 'n3', 'handle' => 'out'],
            ],
        ))->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson(route('workflows.test-run', ['current_team' => $team->slug, 'workflow' => $workflow->id]), [
            'input' => '{}',
        ]);

    $response->assertOk();

    $payload = $response->json();
    $nodes = collect($payload['nodes'])->keyBy(fn (array $node) => $node['nodeKey']);

    expect($payload['status'])->toBe('completed')
        ->and($nodes['n2']['status'])->toBe('ok')
        ->and($nodes['n2']['output']['usage'])->toBe(['prompt_tokens' => 0, 'completion_tokens' => 0]);
})->with([
    'prompt' => ['ai.prompt', ['model' => 'fake/demo', 'prompt' => 'Réponds au message']],
    'classification' => ['ai.classification', ['model' => 'fake/demo', 'prompt' => 'Classe', 'labels' => 'lead, spam']],
    'extraction' => ['ai.extraction', ['model' => 'fake/demo', 'prompt' => 'Analyse', 'fields' => 'nom: text']],
    'summarization' => ['ai.summarization', ['model' => 'fake/demo', 'prompt' => 'Résume le contenu']],
    'generation' => ['ai.generation', ['model' => 'fake/demo', 'prompt' => 'Génère un texte']],
]);

test('a legacy ai.summary graph is refused as an unknown type by the runner', function () {
    Http::preventStrayRequests();

    $graph = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'ai.summary'],
        ],
        edges: [['source' => 'n1', 'target' => 'n2', 'handle' => 'out']],
    );

    $result = app(WorkflowRunner::class)->run($graph['nodes'], $graph['edges'], []);

    expect($result->status)->toBe('failed')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->reason)->toBe('unknown_type')
        ->and($result->errors[0]->message)->toContain('ai.summary');
});

test('the edit page exposes the ai node types without ever leaking an api key', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    config([
        'ai.providers.openai.key' => 'sk-test-sentinel-0123456789',
        'ai.providers.openai.enabled' => true,
        'ai.providers.openai.models' => ['gpt-4o-mini'],
    ]);
    NodeCatalog::flush();

    try {
        $response = $this->actingAs($user)
            ->get(route('workflows.edit', ['current_team' => $team->slug, 'workflow' => $workflow->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('workflows/Edit')
            ->has('nodeTypes', 17));

        $content = $response->getContent();

        PHPUnitAssert::assertStringContainsString('ai.classification', $content);
        PHPUnitAssert::assertStringContainsString('fake\/demo', $content);
        PHPUnitAssert::assertStringContainsString('openai\/gpt-4o-mini', $content);
        PHPUnitAssert::assertStringNotContainsString('sk-test-sentinel', $content);
    } finally {
        config([
            'ai.providers.openai.key' => null,
            'ai.providers.openai.enabled' => false,
            'ai.providers.openai.models' => ['gpt-4o-mini', 'gpt-4o'],
        ]);
        NodeCatalog::flush();
    }
});
