<?php

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Data\Ai\AiUsage;
use App\Services\Ai\Providers\FakeProvider;

/**
 * A minimal request (no schema by default).
 *
 * @param  array<string, string>|null  $jsonSchema
 */
function fakeAiRequest(?array $jsonSchema = null): AiRequest
{
    return new AiRequest(
        provider: 'fake',
        model: 'demo',
        systemPrompt: 'Système.',
        userPrompt: 'Contenu du message',
        temperature: 0.7,
        maxTokens: 2048,
        jsonSchema: $jsonSchema,
        timeoutSeconds: 30,
    );
}

test('the default fake response embeds the user prompt', function () {
    $response = (new FakeProvider)->complete(fakeAiRequest());

    expect($response->text)->toContain('Contenu du message')
        ->and($response->structured)->toBeNull()
        ->and($response->usage->promptTokens)->toBe(0)
        ->and($response->usage->completionTokens)->toBe(0)
        ->and($response->provider)->toBe('fake')
        ->and($response->model)->toBe('demo');
});

test('the default fake response conforms to the requested schema', function () {
    $response = (new FakeProvider)->complete(fakeAiRequest([
        'label' => 'enum:lead,spam,question',
        'montant' => 'number',
    ]));

    expect($response->structured)->toBe(['label' => 'lead', 'montant' => 42]);
});

test('the injected closure fully controls the response', function () {
    $usage = new AiUsage(12, 34);

    $provider = new FakeProvider(fn (AiRequest $request): AiResponse => new AiResponse(
        text: 'réponse contrôlée',
        structured: null,
        usage: $usage,
        provider: 'fake',
        model: $request->model,
    ));

    $response = $provider->complete(fakeAiRequest());

    expect($response->text)->toBe('réponse contrôlée')
        ->and($response->usage)->toBe($usage)
        ->and($response->usage->promptTokens)->toBe(12)
        ->and($response->usage->completionTokens)->toBe(34);
});

test('the usage array shape uses the phase 6 snake_case keys', function () {
    expect((new AiUsage(128, 45))->toArray())->toBe(['prompt_tokens' => 128, 'completion_tokens' => 45]);
});
