<?php

use App\Data\Ai\AiRequest;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Providers\ZAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Enable the z.ai driver config for the test (fake key, real endpoint shape).
 */
function zaiConfig(): void
{
    config([
        'ai.providers.zai.key' => 'zai-test-key',
        'ai.providers.zai.base_url' => 'https://api.z.ai/api/paas/v4',
        'ai.retries' => 2,
    ]);
}

/**
 * A full request without schema.
 *
 * @param  array<string, string>|null  $jsonSchema
 */
function zaiRequest(?array $jsonSchema = null): AiRequest
{
    return new AiRequest(
        provider: 'zai',
        model: 'glm-4.6',
        systemPrompt: 'Système.',
        userPrompt: 'Utilisateur.',
        temperature: 0.4,
        maxTokens: 1024,
        jsonSchema: $jsonSchema,
        timeoutSeconds: 30,
    );
}

/**
 * A chat/completions answer payload.
 */
function zaiPayload(string $content, int $promptTokens = 96, int $completionTokens = 33): string
{
    return (string) json_encode([
        'choices' => [
            ['message' => ['role' => 'assistant', 'content' => $content]],
        ],
        'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens],
    ]);
}

test('the request hits the z.ai endpoint with bearer auth and the classic max_tokens field', function () {
    Sleep::fake();
    zaiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.z.ai/api/paas/v4/*' => Http::response(zaiPayload('réponse'), 200),
    ]);

    (new ZAiProvider)->complete(zaiRequest());

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.z.ai/api/paas/v4/chat/completions'
            && $request->header('Authorization') === ['Bearer zai-test-key']
            && $body['model'] === 'glm-4.6'
            && $body['messages'] === [
                ['role' => 'system', 'content' => 'Système.'],
                ['role' => 'user', 'content' => 'Utilisateur.'],
            ]
            && $body['temperature'] === 0.4
            && $body['max_tokens'] === 1024
            && ! array_key_exists('max_completion_tokens', $body)
            && ! array_key_exists('response_format', $body);
    });
});

test('response_format json_object is sent only when a schema is requested', function () {
    Sleep::fake();
    zaiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.z.ai/api/paas/v4/*' => Http::response(zaiPayload('{"label":"lead"}'), 200),
    ]);

    (new ZAiProvider)->complete(zaiRequest(['label' => 'enum:lead,spam']));

    Http::assertSent(fn ($request): bool => $request->data()['response_format'] === ['type' => 'json_object']);
});

test('the answer text and token usage are parsed with the zai provider id', function () {
    Sleep::fake();
    zaiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.z.ai/api/paas/v4/*' => Http::response(zaiPayload('bonjour', 96, 33), 200),
    ]);

    $response = (new ZAiProvider)->complete(zaiRequest());

    expect($response->text)->toBe('bonjour')
        ->and($response->structured)->toBeNull()
        ->and($response->usage->promptTokens)->toBe(96)
        ->and($response->usage->completionTokens)->toBe(33)
        ->and($response->provider)->toBe('zai')
        ->and($response->model)->toBe('glm-4.6');
});

test('a transient 429 is retried until success with the configured attempts', function () {
    Sleep::fake();
    zaiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.z.ai/api/paas/v4/*' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->push(zaiPayload('succès après retries'), 200),
    ]);

    $response = (new ZAiProvider)->complete(zaiRequest());

    expect($response->text)->toBe('succès après retries');
    Http::assertSentCount(3);
});

test('a 401 ends as provider_auth_failed without any retry and never leaks the key', function () {
    Sleep::fake();
    zaiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.z.ai/api/paas/v4/*' => Http::sequence()->pushStatus(401),
    ]);

    $complete = fn (): mixed => (new ZAiProvider)->complete(zaiRequest());

    expect($complete)->toThrow(function (AiProviderException $exception): bool {
        return $exception->reason === 'provider_auth_failed'
            && ! str_contains($exception->technicalDetail(), 'zai-test-key')
            && ! str_contains($exception->getMessage(), 'zai-test-key');
    });
    Http::assertSentCount(1);
});

test('a missing key fails as provider_not_configured before any request', function () {
    Sleep::fake();
    config(['ai.providers.zai.key' => null]);
    Http::preventStrayRequests();

    $complete = fn (): mixed => (new ZAiProvider)->complete(zaiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_not_configured');
    Http::assertNothingSent();
});
