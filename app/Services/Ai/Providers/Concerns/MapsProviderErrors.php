<?php

namespace App\Services\Ai\Providers\Concerns;

use App\Data\Ai\AiRequest;
use App\Services\Ai\Exception\AiProviderException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared transport layer of the HTTP providers (D7): timeout, transport
 * retries on 429/5xx/ConnectionException and the status → exception
 * mapping. Request/response translation stays in each provider.
 */
trait MapsProviderErrors
{
    /**
     * Build the pending request: timeout from the request, transport
     * retries from the config (attempts = 1 + ai.retries, short fixed
     * backoff — content retries are AiCompleter's concern).
     */
    private function transport(AiRequest $request): PendingRequest
    {
        $retries = max(0, (int) config('ai.retries', 2));

        return Http::timeout($request->timeoutSeconds)
            ->retry($retries + 1, 500, when: $this->retryWhen());
    }

    /**
     * Retry 429, 5xx and connection failures; refuse the rest.
     */
    private function retryWhen(): Closure
    {
        return fn (Throwable $exception): bool => $exception instanceof ConnectionException
            || ($exception instanceof RequestException
                && ($exception->response->status() === 429 || $exception->response->status() >= 500));
    }

    /**
     * Map a transport failure to the provider exception taxonomy (D7).
     *
     * @throws AiProviderException
     */
    private function mapTransportException(Throwable $exception): AiProviderException
    {
        if ($exception instanceof ConnectionException) {
            if (str_contains($exception->getMessage(), 'timed out')) {
                return AiProviderException::timeout($exception->getMessage());
            }

            return AiProviderException::unreachable($exception->getMessage());
        }

        if ($exception instanceof RequestException) {
            return $this->mapStatusException($exception);
        }

        return AiProviderException::providerError($exception->getMessage());
    }

    /**
     * Map a non-2xx final response to the provider exception taxonomy.
     */
    private function mapStatusException(RequestException $exception): AiProviderException
    {
        $status = $exception->response->status();
        $detail = 'HTTP '.$status.': '.$this->providerMessage($exception);

        if ($status === 429) {
            return AiProviderException::rateLimited($detail);
        }

        if ($status === 401 || $status === 403) {
            return AiProviderException::authFailed($detail);
        }

        if ($status >= 500) {
            return AiProviderException::providerError($detail);
        }

        return AiProviderException::invalidRequest($detail);
    }

    /**
     * Extract the provider error message (logs only, truncated, never a key).
     */
    private function providerMessage(RequestException $exception): string
    {
        $message = $exception->response->json('error.message');

        if (! is_string($message) || $message === '') {
            $message = $exception->response->body();
        }

        return mb_substr(trim($message), 0, 500);
    }
}
