<?php

use App\Data\Workflow\NodeContext;
use App\Models\Integration;
use App\Services\Integration\HttpClient;
use App\Services\Integration\IntegrationResolver;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Action\HttpHandler;
use Illuminate\Support\Facades\Http;

/**
 * Build a node context for the action.http handler.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input
 */
function httpNodeContext(array $config = [], array $input = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n7',
        nodeType: 'action.http',
        nodeName: 'Requête HTTP',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

/**
 * A resolver stubbed in memory (no database involved).
 */
function resolverReturning(?Integration $integration): IntegrationResolver
{
    return new IntegrationResolver(fn (string $id): ?Integration => $integration);
}

/**
 * An in-memory Integration model (newInstance/forceFill: no persistence).
 *
 * @param  array<string, mixed>  $credentials
 */
function httpIntegration(array $credentials, string $type = 'generic_http'): Integration
{
    return (new Integration)->forceFill([
        'type' => $type,
        'credentials' => $credentials,
    ]);
}

/**
 * A handler whose DNS closure always throws: every tested URL is a literal
 * documentation-range IP, so DNS must never be consulted.
 */
function httpHandler(?IntegrationResolver $resolver = null): HttpHandler
{
    $client = new HttpClient(resolveHost: fn (): array => throw new RuntimeException('DNS must not be queried in unit tests'));

    return new HttpHandler($client, $resolver ?? resolverReturning(null));
}

test('http validates a complete configuration without errors', function () {
    $errors = httpHandler()->validate([
        'method' => 'POST',
        'url' => 'https://api.exemple.com',
        'headers' => "Content-Type: application/json\nX-Token: abc",
        'body' => '{"a":1}',
        'failure_policy' => 'continue',
    ]);

    expect($errors)->toBe([]);
});

test('http validate requires an url', function () {
    expect(httpHandler()->validate([]))->toBe(['L’URL est requise.'])
        ->and(httpHandler()->validate(['url' => '']))->toBe(['L’URL est requise.']);
});

test('http validate rejects an unknown method and failure policy', function () {
    $errors = httpHandler()->validate([
        'method' => 'TRACE',
        'url' => 'https://api.exemple.com',
        'failure_policy' => 'ignore',
    ]);

    expect($errors)->toHaveCount(2)
        ->and($errors[0])->toBe('La méthode HTTP est invalide.')
        ->and($errors[1])->toBe('La politique d’échec est invalide.');
});

test('http validate rejects more than twenty header lines', function () {
    $headers = implode("\n", array_map(fn (int $i): string => "X-H{$i}: v{$i}", range(1, 21)));

    $errors = httpHandler()->validate([
        'url' => 'https://api.exemple.com',
        'headers' => $headers,
    ]);

    expect($errors)->toBe(['Au maximum 20 en-têtes peuvent être définis.']);
});

test('http validate rejects a header line without a colon separator', function () {
    $errors = httpHandler()->validate([
        'url' => 'https://api.exemple.com',
        'headers' => 'X-Token abc',
    ]);

    expect($errors)->toBe(['Chaque ligne d’en-tête doit suivre le format « Clé: valeur ».']);
});

test('http validate rejects an oversized header key', function () {
    $errors = httpHandler()->validate([
        'url' => 'https://api.exemple.com',
        'headers' => str_repeat('K', 129).': value',
    ]);

    expect($errors)->toBe(['La clé d’en-tête ne doit pas dépasser 128 caractères.']);
});

test('a json response is decoded into the output body', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('{"ok":true,"items":[1,2]}', 200, ['Content-Type' => 'application/json']),
    ]);

    $result = httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
    ]));

    expect($result->output)->toBe(['status' => 200, 'body' => ['ok' => true, 'items' => [1, 2]]]);
});

test('a text response is exposed as a raw string body', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('plain text', 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
    ]));

    expect($result->output)->toBe(['status' => 200, 'body' => 'plain text']);
});

test('a non-2xx response with the continue policy passes through the real status', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('nope', 404),
    ]);

    $result = httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'failure_policy' => 'continue',
    ]));

    expect($result->output)->toBe(['status' => 404, 'body' => 'nope']);
});

test('a non-2xx response fails the node by default', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('oops', 500),
    ]);

    $execute = fn (): mixed => httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
    ]));

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'http_request_failed'
        && $exception->getMessage() === 'La requête HTTP a échoué (statut 500).');
});

test('a transport error with the continue policy produces a zero status output', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::failedConnection('cURL error 7: Failed to connect'),
    ]);

    $result = httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'failure_policy' => 'continue',
    ]));

    expect($result->output)->toBe(['status' => 0, 'body' => null, 'error' => 'network_error']);
});

test('a blocked host fails the node even with the continue policy', function () {
    Http::preventStrayRequests();

    $execute = fn (): mixed => httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'http://169.254.169.254/latest/meta-data',
        'failure_policy' => 'continue',
    ]));

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'blocked_host');
});

test('placeholders are interpolated in url, headers and body', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('ok', 200),
    ]);

    $context = httpNodeContext([
        'method' => 'POST',
        'url' => '{{ trigger.base }}/notify',
        'headers' => 'X-Token: {{ trigger.token }}',
        'body' => '{"email":"{{ trigger.email }}"}',
    ]);
    $context->execution->setVariable('trigger', [
        'base' => 'https://203.0.113.10',
        'token' => 'secret-token-value',
        'email' => 'client@example.com',
    ]);

    httpHandler()->execute($context);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://203.0.113.10/notify'
            && $request->header('X-Token') === ['secret-token-value']
            && $request->body() === '{"email":"client@example.com"}';
    });
});

test('a missing placeholder path fails the node', function () {
    Http::preventStrayRequests();

    $context = httpNodeContext([
        'method' => 'GET',
        'url' => '{{ trigger.missing }}/path',
    ]);
    $context->execution->setVariable('trigger', []);

    $execute = fn (): mixed => httpHandler()->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});

test('the default content type header is applied when a body is sent', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('ok', 200),
    ]);

    httpHandler()->execute(httpNodeContext([
        'method' => 'POST',
        'url' => 'https://203.0.113.10/data',
        'body' => '{"a":1}',
    ]));

    Http::assertSent(fn ($request): bool => $request->header('Content-Type') === ['application/json']);
});

test('a bearer integration injects the authorization header', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('ok', 200),
    ]);

    $handler = httpHandler(resolverReturning(httpIntegration([
        'baseUrl' => 'https://api.exemple.com',
        'auth' => 'bearer',
        'token' => 'secret-bearer-value',
    ])));

    $handler->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'integration_id' => '42',
    ]));

    Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Bearer secret-bearer-value']);
});

test('a basic integration injects the encoded authorization header', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('ok', 200),
    ]);

    $handler = httpHandler(resolverReturning(httpIntegration([
        'auth' => 'basic',
        'username' => 'user@x',
        'password' => 'p:ass',
    ])));

    $handler->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'integration_id' => '42',
    ]));

    Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Basic '.base64_encode('user@x:p:ass')]);
});

test('a header integration injects its custom header', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('ok', 200),
    ]);

    $handler = httpHandler(resolverReturning(httpIntegration([
        'auth' => 'header',
        'headerName' => 'X-Api-Key',
        'headerValue' => 'custom-key-value',
    ])));

    $handler->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'integration_id' => '42',
    ]));

    Http::assertSent(fn ($request): bool => $request->header('X-Api-Key') === ['custom-key-value']);
});

test('integration credentials win over a colliding node header', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('ok', 200),
    ]);

    $handler = httpHandler(resolverReturning(httpIntegration([
        'auth' => 'bearer',
        'token' => 'wins-value',
    ])));

    $handler->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'headers' => 'Authorization: loses-value',
        'integration_id' => '42',
    ]));

    Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Bearer wins-value']);
});

test('an integration id that no longer resolves fails the node', function () {
    Http::preventStrayRequests();

    $context = httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'integration_id' => '42',
    ]);

    $execute = fn (): mixed => httpHandler(resolverReturning(null))->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'integration_not_found');
});

test('an integration of the wrong type fails the node', function () {
    Http::preventStrayRequests();

    $context = httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'integration_id' => '42',
    ]);

    $smtp = httpIntegration(['host' => 'smtp.exemple.com', 'port' => 587], 'smtp');
    $execute = fn (): mixed => httpHandler(resolverReturning($smtp))->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'invalid_integration_type');
});

test('incomplete integration credentials fail the node', function () {
    Http::preventStrayRequests();

    $context = httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
        'integration_id' => '42',
    ]);

    $broken = httpIntegration(['auth' => 'bearer']);
    $execute = fn (): mixed => httpHandler(resolverReturning($broken))->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'integration_invalid');
});

test('transport error details never leak into the node failure message', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::failedConnection('cURL error 7: secret-internal-hostname'),
    ]);

    $execute = fn (): mixed => httpHandler()->execute(httpNodeContext([
        'method' => 'GET',
        'url' => 'https://203.0.113.10/data',
    ]));

    expect($execute)->toThrow(function (NodeExecutionException $exception): bool {
        return $exception->reason === 'network_error'
            && ! str_contains($exception->getMessage(), 'secret-internal-hostname');
    });
});
