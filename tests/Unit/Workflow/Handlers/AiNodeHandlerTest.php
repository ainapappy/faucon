<?php

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Data\Ai\AiUsage;
use App\Data\Workflow\NodeContext;
use App\Services\Ai\AiCompleter;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Providers\FakeProvider;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Ai\AiMode;
use App\Services\Workflow\Handlers\Ai\AiNodeHandler;

/**
 * A handler whose fake provider replays the given responses in order and
 * records every request it received. Empty responses list = the default
 * deterministic FakeProvider behaviour.
 *
 * @param  list<AiResponse>|null  $responses
 * @param  list<AiRequest>&  $captured
 */
function aiHandler(AiMode $mode, ?array $responses = null, array &$captured = []): AiNodeHandler
{
    $responses ??= [];

    $manager = new AiProviderManager(app());

    // A regular closure (not an arrow fn): the arrow fn would capture the
    // by-ref bindings by value and sever the test's references.
    $manager->extend('fake', function () use (&$captured, &$responses): AiProvider {
        return new FakeProvider(
            function (AiRequest $request) use (&$captured, &$responses): AiResponse {
                $captured[] = $request;

                $response = array_shift($responses);

                if ($response === null) {
                    return (new FakeProvider)->complete($request);
                }

                if ($response instanceof Throwable) {
                    throw $response;
                }

                return $response;
            },
        );
    });

    return new AiNodeHandler($mode, new AiCompleter($manager));
}

/**
 * A node context for the given mode (config not pre-interpolated).
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input
 */
function aiNodeContext(AiMode $mode, array $config = [], array $input = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n3',
        nodeType: $mode->typeId(),
        nodeName: 'Node IA',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

/**
 * A provider response carrying the given raw text.
 */
function aiTextResponse(string $text, int $promptTokens = 100, int $completionTokens = 20): AiResponse
{
    return new AiResponse(
        text: $text,
        structured: null,
        usage: new AiUsage($promptTokens, $completionTokens),
        provider: 'fake',
        model: 'demo',
    );
}

test('each mode instance declares its own ai type id', function () {
    foreach (AiMode::cases() as $mode) {
        expect((new AiNodeHandler($mode, new AiCompleter(new AiProviderManager(app()))))->type())
            ->toBe('ai.'.$mode->value);
    }
});

test('forTypeId resolves the five type ids and refuses foreign ones', function () {
    foreach (AiMode::cases() as $mode) {
        expect(AiMode::forTypeId($mode->typeId()))->toBe($mode);
    }

    expect(AiMode::forTypeId('ai.summary'))->toBeNull()
        ->and(AiMode::forTypeId('action.http'))->toBeNull();
});

test('prompt mode returns the provider text with the usage block', function () {
    $captured = [];
    $handler = aiHandler(AiMode::Prompt, [aiTextResponse('réponse du modèle', 128, 45)], $captured);

    $result = $handler->execute(aiNodeContext(AiMode::Prompt, [
        'model' => 'fake/demo',
        'prompt' => 'Dis bonjour',
    ]));

    expect($result->output)->toBe([
        'text' => 'réponse du modèle',
        'usage' => ['prompt_tokens' => 128, 'completion_tokens' => 45],
    ])
        ->and($result->branch)->toBeNull()
        ->and($result->isTerminal)->toBeFalse()
        ->and($result->exposeAs)->toBeNull();
});

test('the prompt template is interpolated over the run context', function () {
    $captured = [];
    $handler = aiHandler(AiMode::Prompt, null, $captured);

    $context = aiNodeContext(AiMode::Prompt, [
        'model' => 'fake/demo',
        'prompt' => 'Résume : {{ trigger.message }}',
    ]);
    $context->execution->setVariable('trigger', ['message' => 'contenu source']);

    $handler->execute($context);

    expect($captured[0]->userPrompt)->toBe('Résume : contenu source');
});

test('a missing placeholder path fails the node with path_not_found', function () {
    Http::preventStrayRequests();

    $handler = aiHandler(AiMode::Prompt);

    $context = aiNodeContext(AiMode::Prompt, [
        'model' => 'fake/demo',
        'prompt' => 'Résume : {{ trigger.missing }}',
    ]);
    $context->execution->setVariable('trigger', []);

    $execute = fn (): mixed => $handler->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});

test('classification builds the enum schema and appends the labels to the system prompt', function () {
    $captured = [];
    $handler = aiHandler(AiMode::Classification, [aiTextResponse('{"label":"lead"}')], $captured);

    $context = aiNodeContext(AiMode::Classification, [
        'model' => 'fake/demo',
        'prompt' => 'Classe : {{ trigger.message }}',
        'labels' => 'lead, spam, question',
    ]);
    $context->execution->setVariable('trigger', ['message' => 'contenu à classer']);

    $result = $handler->execute($context);

    expect($result->output)->toBe([
        'label' => 'lead',
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20],
    ])
        ->and($captured[0]->jsonSchema)->toBe(['label' => 'enum:lead,spam,question'])
        ->and($captured[0]->systemPrompt)->toContain('Étiquettes possibles : lead, spam, question.');
});

test('a classification label outside the list retries once then fails the node', function () {
    $captured = [];
    $handler = aiHandler(AiMode::Classification, [
        aiTextResponse('{"label":"hors-liste"}'),
        aiTextResponse('{"label":"encore-hors-liste"}'),
    ], $captured);

    $execute = fn (): mixed => $handler->execute(aiNodeContext(AiMode::Classification, [
        'model' => 'fake/demo',
        'prompt' => 'Classe',
        'labels' => 'lead, spam',
    ]));

    expect($execute)->toThrow(function (NodeExecutionException $exception) use (&$captured): bool {
        return count($captured) === 2
            && $exception->reason === 'structured_output_invalid'
            && $exception->getMessage() === __('La réponse du fournisseur IA n’a pas pu être mise au format attendu.')
            && str_contains($exception->technicalDetail(), 'hors-liste');
    });
});

test('extraction builds the parsed field schema and returns the structured output', function () {
    $captured = [];
    $handler = aiHandler(AiMode::Extraction, [
        new AiResponse('{"nom":"Client","montant":150,"actif":true}', null, new AiUsage(50, 10), 'fake', 'demo'),
    ], $captured);

    $result = $handler->execute(aiNodeContext(AiMode::Extraction, [
        'model' => 'fake/demo',
        'prompt' => 'Analyse',
        'fields' => "nom: text\nmontant: number\nactif: boolean",
    ]));

    expect($result->output['structured'])->toBe(['nom' => 'Client', 'montant' => 150, 'actif' => true])
        ->and($result->output['usage'])->toBe(['prompt_tokens' => 50, 'completion_tokens' => 10])
        ->and($captured[0]->jsonSchema)->toBe([
            'nom' => 'text',
            'montant' => 'number',
            'actif' => 'boolean',
        ]);
});

test('summarization and generation send no schema and return the text output', function () {
    foreach ([AiMode::Summarization, AiMode::Generation] as $mode) {
        $captured = [];
        $handler = aiHandler($mode, [aiTextResponse('texte', 12, 34)], $captured);

        $result = $handler->execute(aiNodeContext($mode, [
            'model' => 'fake/demo',
            'prompt' => 'Fais quelque chose',
        ]));

        expect($result->output)->toBe([
            'text' => 'texte',
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 34],
        ])
            ->and($captured[0]->jsonSchema)->toBeNull();
    }
});

test('provider failures are translated with the reason preserved and the detail kept for logs', function () {
    $details = [
        'provider_timeout' => fn (): AiProviderException => AiProviderException::timeout('détail interne'),
        'provider_unreachable' => fn (): AiProviderException => AiProviderException::unreachable('détail interne'),
        'provider_rate_limited' => fn (): AiProviderException => AiProviderException::rateLimited('détail interne'),
        'provider_auth_failed' => fn (): AiProviderException => AiProviderException::authFailed('détail interne'),
        'provider_invalid_request' => fn (): AiProviderException => AiProviderException::invalidRequest('détail interne'),
        'provider_error' => fn (): AiProviderException => AiProviderException::providerError('détail interne'),
    ];

    foreach ($details as $reason => $make) {
        $handler = aiHandler(AiMode::Prompt, [$make()]);

        $execute = fn (): mixed => $handler->execute(aiNodeContext(AiMode::Prompt, [
            'model' => 'fake/demo',
            'prompt' => 'Test',
        ]));

        expect($execute)->toThrow(function (NodeExecutionException $exception) use ($reason): bool {
            return $exception->reason === $reason
                && $exception->getMessage() !== ''
                && str_contains($exception->technicalDetail(), 'détail interne')
                && ! str_contains($exception->getMessage(), 'détail interne');
        });
    }
});

test('an empty model falls back to the configured default provider and first model', function () {
    config(['ai.default_provider' => 'fake', 'ai.providers.fake.models' => ['demo', 'demo-2']]);
    $captured = [];
    $handler = aiHandler(AiMode::Prompt, null, $captured);

    $handler->execute(aiNodeContext(AiMode::Prompt, ['prompt' => 'Test']));

    expect($captured[0]->provider)->toBe('fake')
        ->and($captured[0]->model)->toBe('demo');
});

test('an unknown or disabled provider or model fails with ai_model_unavailable', function () {
    config([
        'ai.default_provider' => 'fake',
        'ai.providers.openai.enabled' => true,
        'ai.providers.openai.models' => ['gpt-4o-mini'],
    ]);

    $handler = aiHandler(AiMode::Prompt);

    foreach (['openai/inconnu', 'bidule/gpt-4o', 'bidon/model', 'pas-un-modele'] as $model) {
        $execute = fn (): mixed => $handler->execute(aiNodeContext(AiMode::Prompt, [
            'model' => $model,
            'prompt' => 'Test',
        ]));

        expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'ai_model_unavailable');
    }
});

test('temperature and max tokens defaults come from the config', function () {
    config(['ai.temperature' => 0.25, 'ai.max_tokens' => 777, 'ai.timeout' => 15]);
    $captured = [];
    $handler = aiHandler(AiMode::Prompt, null, $captured);

    $handler->execute(aiNodeContext(AiMode::Prompt, ['prompt' => 'Test']));

    expect($captured[0]->temperature)->toBe(0.25)
        ->and($captured[0]->maxTokens)->toBe(777)
        ->and($captured[0]->timeoutSeconds)->toBe(15);
});

test('node overrides for temperature and max tokens win over the config defaults', function () {
    config(['ai.temperature' => 0.7, 'ai.max_tokens' => 2048]);
    $captured = [];
    $handler = aiHandler(AiMode::Prompt, null, $captured);

    $handler->execute(aiNodeContext(AiMode::Prompt, [
        'prompt' => 'Test',
        'temperature' => '0.1',
        'max_tokens' => '128',
    ]));

    expect($captured[0]->temperature)->toBe(0.1)
        ->and($captured[0]->maxTokens)->toBe(128);
});

test('validate accepts an empty configuration except classification labels', function () {
    expect(aiHandler(AiMode::Prompt)->validate([]))->toBe([])
        ->and(aiHandler(AiMode::Summarization)->validate([]))->toBe([])
        ->and(aiHandler(AiMode::Classification)->validate([]))->toBe(['Au moins une étiquette est requise.'])
        ->and(aiHandler(AiMode::Extraction)->validate([]))->toBe([]);
});

test('validate accepts a fully populated prompt configuration', function () {
    expect(aiHandler(AiMode::Prompt)->validate([
        'model' => 'fake/demo',
        'prompt' => 'Prompts vides tolérés aussi',
        'temperature' => '0.5',
        'max_tokens' => '1024',
    ]))->toBe([]);
});

test('validate rejects an unknown or malformed model reference', function () {
    config([
        'ai.providers.fake.models' => ['demo'],
        'ai.providers.openai.enabled' => true,
        'ai.providers.openai.models' => ['gpt-4o-mini'],
    ]);

    expect(aiHandler(AiMode::Prompt)->validate(['model' => 'openai/inconnu']))
        ->toBe(['Le modèle référencé n’existe pas pour ce fournisseur.'])
        ->and(aiHandler(AiMode::Prompt)->validate(['model' => 'fake/demo-x']))
        ->toBe(['Le modèle référencé n’existe pas pour ce fournisseur.'])
        ->and(aiHandler(AiMode::Prompt)->validate(['model' => 'bidule/gpt-4o']))
        ->toBe(['Le modèle référencé n’existe pas pour ce fournisseur.'])
        ->and(aiHandler(AiMode::Prompt)->validate(['model' => 'malformed']))
        ->toBe(['Le modèle référencé n’existe pas pour ce fournisseur.']);
});

test('validate rejects temperatures outside the 0-1 range or non numeric', function () {
    $handler = aiHandler(AiMode::Prompt);

    expect($handler->validate(['temperature' => '1.5']))->toBe(['La température doit être un nombre entre 0 et 1.'])
        ->and($handler->validate(['temperature' => '-0.1']))->toBe(['La température doit être un nombre entre 0 et 1.'])
        ->and($handler->validate(['temperature' => 'abc']))->toBe(['La température doit être un nombre entre 0 et 1.'])
        ->and($handler->validate(['temperature' => '0']))->toBe([])
        ->and($handler->validate(['temperature' => '1']))->toBe([]);
});

test('validate rejects max tokens outside the 1-8192 range or non integer', function () {
    $handler = aiHandler(AiMode::Prompt);

    expect($handler->validate(['max_tokens' => '0']))->toBe(['Le maximum de tokens doit être un nombre entre 1 et 8192.'])
        ->and($handler->validate(['max_tokens' => '99999']))->toBe(['Le maximum de tokens doit être un nombre entre 1 et 8192.'])
        ->and($handler->validate(['max_tokens' => '12.5']))->toBe(['Le maximum de tokens doit être un nombre entre 1 et 8192.'])
        ->and($handler->validate(['max_tokens' => 'abc']))->toBe(['Le maximum de tokens doit être un nombre entre 1 et 8192.']);
});

test('validate enforces the classification label rules', function () {
    $handler = aiHandler(AiMode::Classification);

    $tooLong = str_repeat('a', 65);
    $twentyOne = implode(',', array_map(fn (int $i): string => 'e'.$i, range(1, 21)));

    expect($handler->validate(['labels' => '   ']))->toBe(['Au moins une étiquette est requise.'])
        ->and($handler->validate(['labels' => $twentyOne]))->toBe(['Au maximum 20 étiquettes peuvent être définies.'])
        ->and($handler->validate(['labels' => 'lead,'.$tooLong]))->toBe(['Une étiquette ne doit pas dépasser 64 caractères.'])
        ->and($handler->validate(['labels' => 'lead, spam']))->toBe([]);
});

test('validate enforces the extraction field line rules', function () {
    $handler = aiHandler(AiMode::Extraction);

    $formatError = 'Chaque ligne doit suivre le format « clé: type » (types : text, number, boolean).';
    $twentyOne = implode("\n", array_map(fn (int $i): string => 'champ_'.$i.': text', range(1, 21)));
    $tooLongKey = str_repeat('a', 65).': text';

    expect($handler->validate(['fields' => 'pas de séparateur']))->toBe([$formatError])
        ->and($handler->validate(['fields' => 'nom: date']))->toBe([$formatError])
        ->and($handler->validate(['fields' => 'a.b: text']))->toBe(['La clé de champ « a.b » est invalide (alphanumérique, 64 caractères max).'])
        ->and($handler->validate(['fields' => $tooLongKey]))->toBe(['La clé de champ « '.str_repeat('a', 65).' » est invalide (alphanumérique, 64 caractères max).'])
        ->and($handler->validate(['fields' => $twentyOne]))->toBe(['Au maximum 20 champs peuvent être définis.'])
        ->and($handler->validate(['fields' => "nom: text\nmontant: number\nactif: boolean"]))->toBe([]);
});
