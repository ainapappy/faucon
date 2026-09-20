<?php

use App\Data\Ai\AiRequest;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Enable the OpenAI driver config for the test (fake key, real endpoint shape).
 */
function openAiConfig(): void
{
    config([
        'ai.providers.openai.key' => 'sk-test-openai-key',
        'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
        'ai.retries' => 2,
    ]);
}

/**
 * A full request without schema.
 *
 * @param  array<string, string>|null  $jsonSchema
 */
function openAiRequest(?array $jsonSchema = null): AiRequest
{
    return new AiRequest(
        provider: 'openai',
        model: 'gpt-4o-mini',
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
function openAiPayload(string $content, int $promptTokens = 128, int $completionTokens = 45): string
{
    return (string) json_encode([
        'choices' => [
            ['message' => ['role' => 'assistant', 'content' => $content]],
        ],
        'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens],
    ]);
}

test('the request hits chat/completions with bearer auth and the modern body shape', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response(openAiPayload('réponse'), 200),
    ]);

    (new OpenAiProvider)->complete(openAiRequest());

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->header('Authorization') === ['Bearer sk-test-openai-key']
            && $body['model'] === 'gpt-4o-mini'
            && $body['messages'] === [
                ['role' => 'system', 'content' => 'Système.'],
                ['role' => 'user', 'content' => 'Utilisateur.'],
            ]
            && $body['temperature'] === 0.4
            && $body['max_completion_tokens'] === 1024
            && ! array_key_exists('max_tokens', $body)
            && ! array_key_exists('response_format', $body);
    });
});

test('response_format json_object is sent only when a schema is requested', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response(openAiPayload('{"label":"lead"}'), 200),
    ]);

    (new OpenAiProvider)->complete(openAiRequest(['label' => 'enum:lead,spam']));

    Http::assertSent(fn ($request): bool => $request->data()['response_format'] === ['type' => 'json_object']);
});

test('the answer text and token usage are parsed into the response', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response(openAiPayload('bonjour', 128, 45), 200),
    ]);

    $response = (new OpenAiProvider)->complete(openAiRequest());

    expect($response->text)->toBe('bonjour')
        ->and($response->structured)->toBeNull()
        ->and($response->usage->promptTokens)->toBe(128)
        ->and($response->usage->completionTokens)->toBe(45)
        ->and($response->provider)->toBe('openai')
        ->and($response->model)->toBe('gpt-4o-mini');
});

test('a missing usage block defaults both counters to zero', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response((string) json_encode(['choices' => [['message' => ['content' => 'a']]]]), 200),
    ]);

    $response = (new OpenAiProvider)->complete(openAiRequest());

    expect($response->usage->promptTokens)->toBe(0)
        ->and($response->usage->completionTokens)->toBe(0);
});

test('a transient 429 is retried until success with the configured attempts', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->push(openAiPayload('succès après retries'), 200),
    ]);

    $response = (new OpenAiProvider)->complete(openAiRequest());

    expect($response->text)->toBe('succès après retries');
    Http::assertSentCount(3);
});

test('a persistent 429 ends as provider_rate_limited', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->pushStatus(429),
    ]);

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_rate_limited');
    Http::assertSentCount(3);
});

test('a connection timeout ends as provider_timeout after the transport retries', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::failedConnection('cURL error 28: Operation timed out'),
    ]);

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_timeout');
    Http::assertSentCount(3);
});

test('a connection refusal ends as provider_unreachable', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::failedConnection('cURL error 7: Failed to connect'),
    ]);

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_unreachable');
});

test('a 401 ends as provider_auth_failed without any retry', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::sequence()->pushStatus(401),
    ]);

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_auth_failed');
    Http::assertSentCount(1);
});

test('a 400 ends as provider_invalid_request with the provider message logged, never the key', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::response(
            (string) json_encode(['error' => ['message' => 'max_completion_tokens is too large']], 400),
            400,
        ),
    ]);

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(function (AiProviderException $exception): bool {
        return $exception->reason === 'provider_invalid_request'
            && str_contains($exception->technicalDetail(), 'max_completion_tokens is too large')
            && ! str_contains($exception->technicalDetail(), 'sk-test-openai-key')
            && ! str_contains($exception->getMessage(), 'sk-test-openai-key');
    });
});

test('a 500 ends as provider_error after the transport retries', function () {
    Sleep::fake();
    openAiConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/*' => Http::sequence()->pushStatus(500)->pushStatus(500)->pushStatus(500),
    ]);

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_error');
    Http::assertSentCount(3);
});

test('a missing key fails as provider_not_configured before any request', function () {
    Sleep::fake();
    config(['ai.providers.openai.key' => null]);
    Http::preventStrayRequests();

    $complete = fn (): mixed => (new OpenAiProvider)->complete(openAiRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_not_configured');
    Http::assertNothingSent();
});
