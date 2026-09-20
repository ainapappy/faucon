<?php

use App\Data\Ai\AiRequest;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Providers\AnthropicProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Enable the Anthropic driver config for the test (fake key, real endpoint shape).
 */
function anthropicConfig(): void
{
    config([
        'ai.providers.anthropic.key' => 'ak-test-anthropic-key',
        'ai.providers.anthropic.base_url' => 'https://api.anthropic.com',
        'ai.providers.anthropic.version' => '2023-06-01',
        'ai.retries' => 2,
    ]);
}

/**
 * A full request without schema.
 *
 * @param  array<string, string>|null  $jsonSchema
 */
function anthropicRequest(?array $jsonSchema = null): AiRequest
{
    return new AiRequest(
        provider: 'anthropic',
        model: 'claude-haiku-4-5',
        systemPrompt: 'Système.',
        userPrompt: 'Utilisateur.',
        temperature: 0.4,
        maxTokens: 1024,
        jsonSchema: $jsonSchema,
        timeoutSeconds: 30,
    );
}

/**
 * A v1/messages answer payload (content blocks + anthropic usage shape).
 */
function anthropicPayload(array $blocks, int $inputTokens = 210, int $outputTokens = 33): string
{
    return (string) json_encode([
        'content' => $blocks,
        'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
    ]);
}

test('the request hits v1/messages with the anthropic headers and the mandatory max_tokens', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::response(anthropicPayload([['type' => 'text', 'text' => 'réponse']]), 200),
    ]);

    (new AnthropicProvider)->complete(anthropicRequest());

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->header('x-api-key') === ['ak-test-anthropic-key']
            && $request->header('anthropic-version') === ['2023-06-01']
            && $body['model'] === 'claude-haiku-4-5'
            && $body['max_tokens'] === 1024
            && $body['temperature'] === 0.4
            && $body['system'] === 'Système.'
            && $body['messages'] === [['role' => 'user', 'content' => 'Utilisateur.']];
    });
});

test('the concatenated text blocks and anthropic usage counters are parsed', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::response(anthropicPayload([
            ['type' => 'text', 'text' => 'bonjour '],
            ['type' => 'tool_use', 'id' => 'x', 'name' => 'f', 'input' => []],
            ['type' => 'text', 'text' => 'le monde'],
        ], 210, 33), 200),
    ]);

    $response = (new AnthropicProvider)->complete(anthropicRequest());

    expect($response->text)->toBe('bonjour le monde')
        ->and($response->structured)->toBeNull()
        ->and($response->usage->promptTokens)->toBe(210)
        ->and($response->usage->completionTokens)->toBe(33)
        ->and($response->provider)->toBe('anthropic')
        ->and($response->model)->toBe('claude-haiku-4-5');
});

test('a transient 429 is retried until success with the configured attempts', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->push(anthropicPayload([['type' => 'text', 'text' => 'succès']]), 200),
    ]);

    $response = (new AnthropicProvider)->complete(anthropicRequest());

    expect($response->text)->toBe('succès');
    Http::assertSentCount(3);
});

test('a persistent 429 ends as provider_rate_limited', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->pushStatus(429),
    ]);

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_rate_limited');
    Http::assertSentCount(3);
});

test('a connection timeout ends as provider_timeout after the transport retries', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::failedConnection('cURL error 28: Operation timed out'),
    ]);

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_timeout');
    Http::assertSentCount(3);
});

test('a connection refusal ends as provider_unreachable', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::failedConnection('cURL error 7: Failed to connect'),
    ]);

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_unreachable');
});

test('a 403 ends as provider_auth_failed without any retry', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::sequence()->pushStatus(403),
    ]);

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_auth_failed');
    Http::assertSentCount(1);
});

test('a 400 ends as provider_invalid_request with the provider message logged, never the key', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::response(
            (string) json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens: Field required']], 400),
            400,
        ),
    ]);

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(function (AiProviderException $exception): bool {
        return $exception->reason === 'provider_invalid_request'
            && str_contains($exception->technicalDetail(), 'max_tokens: Field required')
            && ! str_contains($exception->technicalDetail(), 'ak-test-anthropic-key')
            && ! str_contains($exception->getMessage(), 'ak-test-anthropic-key');
    });
});

test('a 529 overloaded response ends as provider_error after the transport retries', function () {
    Sleep::fake();
    anthropicConfig();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/*' => Http::sequence()->pushStatus(529)->pushStatus(529)->pushStatus(529),
    ]);

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_error');
    Http::assertSentCount(3);
});

test('a missing key fails as provider_not_configured before any request', function () {
    Sleep::fake();
    config(['ai.providers.anthropic.key' => null]);
    Http::preventStrayRequests();

    $complete = fn (): mixed => (new AnthropicProvider)->complete(anthropicRequest());

    expect($complete)->toThrow(fn (AiProviderException $exception) => $exception->reason === 'provider_not_configured');
    Http::assertNothingSent();
});
