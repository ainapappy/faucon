<?php

namespace App\Services\Integration;

use App\Services\Integration\Exception\HttpClientException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The single hardened wrapper of the Http facade (D7): URL/host guard,
 * manual per-hop redirect validation, short timeouts and a response
 * size ceiling.
 *
 * DNS resolution goes through an injectable closure (tests inject fixed
 * addresses; no test ever performs a real DNS query or network call).
 */
final class HttpClient
{
    /**
     * IPv4 ranges refused by the guard: unspecified, private, loopback,
     * link-local, CGNAT, multicast and reserved (D7 list).
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private const array BlockedIpv4Ranges = [
        ['0.0.0.0', 8],
        ['10.0.0.0', 8],
        ['127.0.0.0', 8],
        ['169.254.0.0', 16],
        ['172.16.0.0', 12],
        ['192.168.0.0', 16],
        ['100.64.0.0', 10],
        ['224.0.0.0', 4],
        ['240.0.0.0', 4],
    ];

    public function __construct(
        /** @var Closure(string): list<string>|null Tests only — défaut : A + AAAA. */
        private readonly ?Closure $resolveHost = null,
    ) {}

    /**
     * Send an HTTP request through the guard.
     *
     * @param  array<string, string>  $headers
     *
     * @throws HttpClientException reason invalid_url|blocked_host|network_error|timeout|response_too_large
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $this->guard($url);

        $maxRedirects = (int) config('workflows.http.max_redirects', 2);

        $pending = Http::withHeaders($headers)
            ->timeout((int) config('workflows.http.timeout', 10))
            ->connectTimeout((int) config('workflows.http.connect_timeout', 5))
            ->withoutRedirecting();

        $current = $url;
        $hops = 0;
        $response = null;

        while (true) {
            try {
                $response = $pending->send($method, $current, ['body' => $body]);
            } catch (ConnectionException $exception) {
                if (str_contains($exception->getMessage(), 'timed out')) {
                    throw HttpClientException::timeout($exception->getMessage());
                }

                throw HttpClientException::networkError($exception->getMessage());
            }

            if (! $response->redirect()) {
                break;
            }

            $location = $response->header('Location');

            if ($location === '') {
                break;
            }

            if ($hops >= $maxRedirects) {
                throw HttpClientException::networkError('Too many redirects (max '.$maxRedirects.').');
            }

            $current = $this->resolveLocation($current, $location);
            $this->guard($current);
            $hops++;
        }

        $maxBytes = (int) config('workflows.http.max_response_bytes', 1048576);
        $contentLength = $response->header('Content-Length');

        if ($contentLength !== '' && (int) $contentLength > $maxBytes) {
            throw HttpClientException::responseTooLarge($maxBytes);
        }

        if (strlen($response->body()) > $maxBytes) {
            throw HttpClientException::responseTooLarge($maxBytes);
        }

        return $response;
    }

    /**
     * Resolve a hostname to its IP addresses (A + AAAA), or through the
     * injected test closure when provided.
     *
     * @return list<string>
     */
    public function resolveHost(string $host): array
    {
        if ($this->resolveHost !== null) {
            return ($this->resolveHost)($host);
        }

        $addresses = [];

        $ipv4 = gethostbyname($host);

        if ($ipv4 !== $host) {
            $addresses[] = $ipv4;
        }

        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }

    /**
     * Validate the scheme and every resolved address of a url.
     *
     * @throws HttpClientException invalid_url|blocked_host
     */
    private function guard(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw HttpClientException::invalidUrl('Malformed url.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = (string) $parts['host'];

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw HttpClientException::invalidUrl('Scheme not http(s): '.$scheme);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->guardIp($host);

            return;
        }

        $addresses = $this->resolveHost($host);

        foreach ($addresses as $ip) {
            $this->guardIp($ip);
        }

        if ($addresses === []) {
            throw HttpClientException::blockedHost('Unresolvable host: '.$host);
        }
    }

    /**
     * Classify one address against the blocked ranges (conservative: an
     * unparsable address is refused).
     */
    private function guardIp(string $ip): void
    {
        if (str_contains($ip, ':')) {
            $blocked = $this->isBlockedIpv6($ip);
        } else {
            $blocked = $this->isBlockedIpv4($ip);
        }

        if ($blocked) {
            throw HttpClientException::blockedHost('Blocked address: '.$ip);
        }
    }

    /**
     * Resolve a redirect location against the url it came from.
     */
    private function resolveLocation(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw HttpClientException::invalidUrl('Malformed redirect base.');
        }

        $root = strtolower((string) $parts['scheme']).'://'.(string) $parts['host'];

        if (isset($parts['port'])) {
            $root .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $root.$location;
        }

        $path = (string) parse_url($base, PHP_URL_PATH);
        $directory = str_replace('\\', '/', dirname($path === '' ? '/' : $path));

        return $root.rtrim($directory, '/').'/'.$location;
    }

    /**
     * Unparseable addresses are refused (conservative stance).
     */
    private function isBlockedIpv4(string $ip): bool
    {
        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        $long = (int) sprintf('%u', $long);

        foreach (self::BlockedIpv4Ranges as [$subnet, $prefix]) {
            $mask = (-1 << (32 - $prefix)) & 0xFFFFFFFF;
            $base = (int) sprintf('%u', (int) ip2long($subnet));

            if (($long & $mask) === $base) {
                return true;
            }
        }

        return false;
    }

    /**
     * Block ::, ::1, ULA (fc00::/7), link-local (fe80::/10) and unwrap
     * IPv4-mapped addresses (::ffff:0:0/96) to classify them as IPv4.
     */
    private function isBlockedIpv6(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return true;
        }

        $mappedPrefix = "\0\0\0\0\0\0\0\0\0\0\xFF\xFF";

        if (str_starts_with($packed, $mappedPrefix)) {
            $unwrapped = inet_ntop(substr($packed, 12));

            if ($unwrapped === false) {
                return true;
            }

            return $this->isBlockedIpv4($unwrapped);
        }

        if ($packed === str_repeat("\0", 16)) {
            return true;
        }

        if ($packed === str_repeat("\0", 15)."\x01") {
            return true;
        }

        $first = ord($packed[0]);
        $second = ord($packed[1]);

        if ($first >= 0xFC && $first <= 0xFD) {
            return true;
        }

        if ($first === 0xFE && ($second & 0xC0) === 0x80) {
            return true;
        }

        return false;
    }
}
