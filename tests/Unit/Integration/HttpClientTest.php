<?php

use App\Services\Integration\Exception\HttpClientException;
use App\Services\Integration\HttpClient;
use Illuminate\Support\Facades\Http;

/**
 * A public documentation-range address (203.0.113.0/24, TEST-NET-3) is used
 * everywhere: it is not in the blocked ranges and Http::fake() never hits the
 * network. DNS is always provided through the injected closure.
 */
function hardenedClient(?Closure $resolveHost = null): HttpClient
{
    return new HttpClient(resolveHost: $resolveHost ?? fn (): array => []);
}

dataset('blocked_urls', [
    'http://localhost',
    'http://127.0.0.1',
    'http://10.0.0.1',
    'http://172.16.0.5',
    'http://192.168.1.1',
    'http://169.254.169.254',
    'http://0.0.0.0',
    'http://100.64.0.1',
    'http://240.0.0.1',
    'http://224.0.0.1',
    'http://[::1]/',
    'http://[fe80::1]/',
    'http://[fc00::1]/',
    'http://[::ffff:127.0.0.1]/',
]);

test('blocks private, loopback and reserved destinations without any network call', function (string $url) {
    Http::preventStrayRequests();

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', $url);

    expect($send)->toThrow(function (HttpClientException $exception) {
        return $exception->reason === 'blocked_host';
    });
})->with('blocked_urls');

test('literal ip hosts never trigger a dns resolution', function () {
    Http::preventStrayRequests();

    $client = new HttpClient(resolveHost: fn (): array => throw new RuntimeException('DNS must not be queried for literal hosts'));

    $send = fn (): mixed => $client->send('GET', 'http://127.0.0.1');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host');
});

test('a hostname resolving to a private ip is blocked', function () {
    Http::preventStrayRequests();

    $client = hardenedClient(fn (): array => ['10.0.0.1', '198.51.100.5']);

    $send = fn (): mixed => $client->send('GET', 'https://api.exemple.com');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host');
});

test('an unresolvable hostname is blocked', function () {
    Http::preventStrayRequests();

    $client = hardenedClient(fn (): array => []);

    $send = fn (): mixed => $client->send('GET', 'https://api.exemple.com');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host');
});

test('non http(s) schemes are rejected as invalid url', function () {
    Http::preventStrayRequests();

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'ftp://203.0.113.10/file');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'invalid_url');
});

test('malformed urls are rejected as invalid url', function () {
    Http::preventStrayRequests();

    $client = hardenedClient();

    expect(fn (): mixed => $client->send('GET', 'not-an-url'))->toThrow(fn (HttpClientException $exception) => $exception->reason === 'invalid_url')
        ->and(fn (): mixed => $client->send('GET', '/relative/only'))->toThrow(fn (HttpClientException $exception) => $exception->reason === 'invalid_url');
});

test('the injected resolver provides the resolved ips', function () {
    $client = hardenedClient(fn (): array => ['198.51.100.5', '2001:db8::1']);

    expect($client->resolveHost('api.exemple.com'))->toBe(['198.51.100.5', '2001:db8::1']);
});

test('a public url sends the request with the given method, headers and body', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::response('{"ok":true}', 200),
    ]);

    $client = hardenedClient();

    $response = $client->send('POST', 'https://203.0.113.10/hook', ['X-Custom' => 'valeur'], '{"a":1}');

    expect($response->status())->toBe(200)
        ->and($response->body())->toBe('{"ok":true}');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://203.0.113.10/hook'
            && $request->header('X-Custom') === ['valeur']
            && $request->body() === '{"a":1}';
    });
});

test('a redirect towards a private ip is blocked before the second hop', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/redirect' => Http::response('', 302, ['Location' => 'http://127.0.0.1/steal']),
    ]);

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'https://203.0.113.10/redirect');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host');

    Http::assertSentCount(1);
});

test('a redirect towards a public url is followed within the limit', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/start' => Http::response('', 302, ['Location' => 'https://198.51.100.20/final']),
        'https://198.51.100.20/final' => Http::response('done'),
    ]);

    $client = hardenedClient();

    $response = $client->send('GET', 'https://203.0.113.10/start');

    expect($response->status())->toBe(200)
        ->and($response->body())->toBe('done');

    Http::assertSentCount(2);
});

test('a relative redirect location is resolved against the previous url', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/start' => Http::response('', 302, ['Location' => '/moved']),
        'https://203.0.113.10/moved' => Http::response('arrived'),
    ]);

    $client = hardenedClient();

    $response = $client->send('GET', 'https://203.0.113.10/start');

    expect($response->body())->toBe('arrived');

    Http::assertSentCount(2);
});

test('a redirect beyond the maximum is a network error', function () {
    config(['workflows.http.max_redirects' => 2]);
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/loop' => Http::response('', 302, ['Location' => 'https://203.0.113.10/loop']),
    ]);

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'https://203.0.113.10/loop');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'network_error');
});

test('a content-length above the ceiling is rejected before reading the body', function () {
    config(['workflows.http.max_response_bytes' => 1024]);
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/big' => Http::response('ignored', 200, ['Content-Length' => '2097152']),
    ]);

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'https://203.0.113.10/big');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'response_too_large');
});

test('a body larger than the ceiling is rejected after the request', function () {
    config(['workflows.http.max_response_bytes' => 16]);
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/big' => Http::response(str_repeat('x', 128), 200),
    ]);

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'https://203.0.113.10/big');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'response_too_large');
});

test('network failures carry the network_error reason', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::failedConnection('cURL error 7: Failed to connect'),
    ]);

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'https://203.0.113.10/hook');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'network_error');
});

test('timeouts carry the timeout reason', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://203.0.113.10/*' => Http::failedConnection('cURL error 28: Operation timed out'),
    ]);

    $client = hardenedClient();

    $send = fn (): mixed => $client->send('GET', 'https://203.0.113.10/hook');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'timeout');
});
