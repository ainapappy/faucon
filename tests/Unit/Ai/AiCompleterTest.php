<?php

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Data\Ai\AiUsage;
use App\Services\Ai\AiCompleter;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Exception\AiStructuredOutputException;
use App\Services\Ai\Providers\FakeProvider;
use Illuminate\Support\Facades\Http;

/**
 * A completer whose fake driver replays the given responses in order and
 * records every request it received.
 *
 * @param  list<AiResponse>  $responses
 * @param  list<AiRequest>&  $captured
 */
function completerReplaying(array $responses, array &$captured = []): AiCompleter
{
    $manager = new AiProviderManager(app());

    // A regular closure (not an arrow fn): the arrow fn would capture
    // `$captured`/`$responses` by value and sever the test's by-ref binding.
    $manager->extend('fake', function () use (&$captured, &$responses): AiProvider {
        return new FakeProvider(
            function (AiRequest $request) use (&$captured, &$responses): AiResponse {
                $captured[] = $request;

                $response = array_shift($responses);

                if ($response === null) {
                    throw new RuntimeException('No more faked responses.');
                }

                return $response;
            },
        );
    });

    return new AiCompleter($manager);
}

/**
 * A valid structured response carrying the given raw text.
 */
function structuredAiResponse(string $text): AiResponse
{
    return new AiResponse(
        text: $text,
        structured: null,
        usage: new AiUsage(10, 5),
        provider: 'fake',
        model: 'demo',
    );
}

test('without a schema the response is passed through and the provider is called once', function () {
    $captured = [];
    $completer = completerReplaying([structuredAiResponse('réponse libre')], $captured);

    $request = new AiRequest(
        provider: 'fake',
        model: 'demo',
        systemPrompt: 'Système.',
        userPrompt: 'Utilisateur.',
        temperature: 0.5,
        maxTokens: 512,
        jsonSchema: null,
        timeoutSeconds: 30,
    );

    $response = $completer->complete($request);

    expect($response->text)->toBe('réponse libre')
        ->and($response->structured)->toBeNull()
        ->and($response->usage->promptTokens)->toBe(10)
        ->and(count($captured))->toBe(1)
        ->and($captured[0]->systemPrompt)->toBe('Système.');
});

test('with a schema the json instruction is appended to the system prompt', function () {
    $captured = [];
    $completer = completerReplaying([
        structuredAiResponse('{"label":"lead"}'),
    ], $captured);

    $request = new AiRequest(
        provider: 'fake',
        model: 'demo',
        systemPrompt: 'Tu classes.',
        userPrompt: 'Contenu.',
        temperature: 0.5,
        maxTokens: 512,
        jsonSchema: ['label' => 'enum:lead,spam'],
        timeoutSeconds: 30,
    );

    $response = $completer->complete($request);

    expect($response->structured)->toBe(['label' => 'lead'])
        ->and($captured[0]->systemPrompt)->toStartWith('Tu classes.')
        ->and($captured[0]->systemPrompt)->toContain('JSON')
        ->and($captured[0]->systemPrompt)->toContain('l’une des valeurs : lead, spam');
});

test('a conforming answer wrapped in markdown fences yields the structured payload', function () {
    $captured = [];
    $completer = completerReplaying([
        structuredAiResponse("```json\n{\"label\":\"spam\"}\n```"),
    ], $captured);

    $request = new AiRequest(
        provider: 'fake',
        model: 'demo',
        systemPrompt: 'Tu classes.',
        userPrompt: 'Contenu.',
        temperature: 0.5,
        maxTokens: 512,
        jsonSchema: ['label' => 'enum:lead,spam'],
        timeoutSeconds: 30,
    );

    $response = $completer->complete($request);

    expect($response->structured)->toBe(['label' => 'spam'])
        ->and(count($captured))->toBe(1);
});

test('an invalid first answer is retried once with a format reminder', function () {
    $captured = [];
    $completer = completerReplaying([
        structuredAiResponse('pas du json'),
        structuredAiResponse('{"label":"lead"}'),
    ], $captured);

    $request = new AiRequest(
        provider: 'fake',
        model: 'demo',
        systemPrompt: 'Tu classes.',
        userPrompt: 'Contenu.',
        temperature: 0.5,
        maxTokens: 512,
        jsonSchema: ['label' => 'enum:lead,spam'],
        timeoutSeconds: 30,
    );

    $response = $completer->complete($request);

    expect($response->structured)->toBe(['label' => 'lead'])
        ->and(count($captured))->toBe(2)
        ->and($captured[0]->systemPrompt)->not->toContain('RAPPEL')
        ->and($captured[1]->systemPrompt)->toContain('RAPPEL');
});

test('two invalid answers throw a structured output exception without leaking the payload', function () {
    $captured = [];
    $completer = completerReplaying([
        structuredAiResponse('première réponse non conforme'),
        structuredAiResponse('seconde réponse non conforme'),
    ], $captured);

    $request = new AiRequest(
        provider: 'fake',
        model: 'demo',
        systemPrompt: 'Tu classes.',
        userPrompt: 'Contenu.',
        temperature: 0.5,
        maxTokens: 512,
        jsonSchema: ['label' => 'enum:lead,spam'],
        timeoutSeconds: 30,
    );

    $complete = fn (): mixed => $completer->complete($request);

    expect($complete)->toThrow(function (AiStructuredOutputException $exception) use (&$captured): bool {
        return count($captured) === 2
            && str_contains($exception->technicalDetail(), 'seconde réponse non conforme')
            && ! str_contains($exception->getMessage(), 'seconde réponse non conforme');
    });
});

test('an unknown driver surfaces as provider_not_configured', function () {
    Http::preventStrayRequests();

    $completer = new AiCompleter(new AiProviderManager(app()));

    $request = new AiRequest(
        provider: 'inconnu',
        model: 'demo',
        systemPrompt: 'Système.',
        userPrompt: 'Utilisateur.',
        temperature: 0.5,
        maxTokens: 512,
        jsonSchema: null,
        timeoutSeconds: 30,
    );

    $complete = fn (): mixed => $completer->complete($request);

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_not_configured');
});
