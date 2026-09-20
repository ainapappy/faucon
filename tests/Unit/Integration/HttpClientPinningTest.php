<?php

use App\Services\Integration\Exception\HttpClientException;
use App\Services\Integration\HttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * S7 (audit phase 11): the DNS answers validated by the guard must be
 * pinned for the duration of the request. Every test here is offline:
 * DNS answers come from the injected resolveHost closure and the
 * transport is faked, so a public documentation-range address
 * (203.0.113.0/24, TEST-NET-3) plays the validated public target.
 */
dataset('obfuscated_private_urls', [
    'decimal ipv4' => 'http://2130706433',
    'hex ipv4' => 'http://0x7f000001',
    'octal ipv4' => 'http://017700000001',
    'bracketed ipv6 loopback' => 'http://[::1]/',
    'unspecified ipv4' => 'http://0.0.0.0',
    'ipv4-mapped loopback' => 'http://[::ffff:127.0.0.1]/',
]);

test('pin entries derive the port from the scheme or the explicit port', function () {
    $client = new HttpClient(resolveHost: fn (): array => ['203.0.113.10']);

    expect($client->pinEntries('http://api.exemple.com/endpoint'))->toBe(['api.exemple.com:80:203.0.113.10'])
        ->and($client->pinEntries('https://api.exemple.com/endpoint'))->toBe(['api.exemple.com:443:203.0.113.10'])
        ->and($client->pinEntries('http://api.exemple.com:8080/endpoint'))->toBe(['api.exemple.com:8080:203.0.113.10']);
});

test('a literal ip host is pinned to itself without any resolution', function () {
    $client = new HttpClient(resolveHost: fn (): array => throw new RuntimeException('DNS must not be queried for literal hosts'));

    expect($client->pinEntries('http://203.0.113.10/hook'))->toBe(['203.0.113.10:80:203.0.113.10'])
        ->and($client->pinEntries('https://203.0.113.10:8443/hook'))->toBe(['203.0.113.10:8443:203.0.113.10']);
});

test('pin entries cover exactly the addresses classified for the request', function () {
    $client = new HttpClient(resolveHost: fn (): array => ['203.0.113.10', '198.51.100.20', '2001:db8::1']);

    expect($client->pinEntries('https://api.exemple.com/endpoint'))->toBe([
        'api.exemple.com:443:203.0.113.10',
        'api.exemple.com:443:198.51.100.20',
        // curl requires the address part of a pin to bracket IPv6 (7.57.0+).
        'api.exemple.com:443:[2001:db8::1]',
    ]);
});

test('a rebound dns answer is re-classified instead of being connected to', function () {
    // DNS rebinding (TOCTOU): the guard used to classify resolution n while
    // Guzzle performed its own resolution at connection time — an attacker
    // DNS could answer a public address to the guard and a private one to
    // curl. With pinning, the transport receives CURLOPT_RESOLVE entries
    // derived from the very resolution the guard classified, so the
    // scenario "the guard sees a public address while curl connects to a
    // private one" is structurally impossible: curl never resolves the
    // host again, and a changed DNS answer is re-classified (below) rather
    // than connected to.
    $resolutions = 0;
    $client = new HttpClient(resolveHost: function () use (&$resolutions): array {
        $resolutions++;

        return $resolutions === 1 ? ['203.0.113.10'] : ['127.0.0.1'];
    });

    expect($client->pinEntries('https://rebind.exemple.com/start'))->toBe(['rebind.exemple.com:443:203.0.113.10'])
        ->and($resolutions)->toBe(1);

    $rebound = fn (): array => $client->pinEntries('https://rebind.exemple.com/start');

    expect($rebound)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host')
        ->and($resolutions)->toBe(2);
});

test('each redirect hop pins exactly the addresses classified for that hop', function () {
    Http::preventStrayRequests();

    $resolutions = 0;
    $client = new HttpClient(resolveHost: function () use (&$resolutions): array {
        $resolutions++;

        return $resolutions === 1 ? ['203.0.113.10'] : ['198.51.100.20'];
    });

    $captured = [];
    Http::fake(function (Request $request, array $options) use (&$captured) {
        $captured[] = $options;

        if (str_contains($request->url(), '/start')) {
            return Http::response('', 302, ['Location' => '/final']);
        }

        return Http::response('arrived');
    });

    $response = $client->send('GET', 'https://hop.exemple.com/start');

    expect($response->status())->toBe(200)
        ->and($resolutions)->toBe(2)
        ->and($captured[0]['curl'][CURLOPT_RESOLVE])->toBe(['hop.exemple.com:443:203.0.113.10'])
        ->and($captured[1]['curl'][CURLOPT_RESOLVE])->toBe(['hop.exemple.com:443:198.51.100.20']);
});

test('the transport receives the pinned addresses of the single resolution', function () {
    Http::preventStrayRequests();

    $resolutions = 0;
    $client = new HttpClient(resolveHost: function () use (&$resolutions): array {
        $resolutions++;

        return ['203.0.113.10'];
    });

    $captured = [];
    Http::fake(function (Request $request, array $options) use (&$captured) {
        $captured[] = $options;

        return Http::response('{"ok":true}', 200);
    });

    $response = $client->send('GET', 'https://single.exemple.com/hook');

    expect($response->status())->toBe(200)
        ->and($resolutions)->toBe(1)
        ->and($captured[0]['curl'][CURLOPT_RESOLVE])->toBe(['single.exemple.com:443:203.0.113.10']);
});

test('a multi-a answer mixing a public and a private address is blocked', function () {
    Http::preventStrayRequests();

    $client = new HttpClient(resolveHost: fn (): array => ['203.0.113.10', '10.0.0.5']);

    $send = fn (): mixed => $client->send('GET', 'https://multi.exemple.com/hook');

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host');
});

test('the production resolution keeps every a and aaaa record', function () {
    $ipv4Records = [
        ['host' => 'multi.exemple.com', 'type' => 'A', 'ip' => '203.0.113.10'],
        ['host' => 'multi.exemple.com', 'type' => 'A', 'ip' => '10.0.0.5'],
    ];
    $ipv6Records = [
        ['host' => 'multi.exemple.com', 'type' => 'AAAA', 'ipv6' => '2001:db8::1'],
    ];

    expect(HttpClient::addressesFromDnsRecords($ipv4Records, $ipv6Records))
        ->toBe(['203.0.113.10', '10.0.0.5', '2001:db8::1']);
});

test('dns records without an address value are skipped', function () {
    expect(HttpClient::addressesFromDnsRecords(
        [['host' => 'multi.exemple.com', 'type' => 'A']],
        [['host' => 'multi.exemple.com', 'type' => 'AAAA', 'ipv6' => null]],
    ))->toBe([]);
});

test('obfuscated private destinations stay blocked', function (string $url) {
    Http::preventStrayRequests();

    // Mirrors what getaddrinfo eventually yields: whole-number forms
    // collapse to loopback, bracketed hosts to themselves.
    $client = new HttpClient(resolveHost: fn (string $host): array => str_starts_with($host, '[')
        ? [trim($host, '[]')]
        : ['127.0.0.1']);

    $send = fn (): mixed => $client->send('GET', $url);

    expect($send)->toThrow(fn (HttpClientException $exception) => $exception->reason === 'blocked_host');
})->with('obfuscated_private_urls');
