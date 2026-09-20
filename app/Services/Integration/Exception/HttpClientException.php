<?php

namespace App\Services\Integration\Exception;

use Exception;
use Throwable;

/**
 * An HTTP request refusal/failure carrying a machine reason and a
 * user-safe message (mirror of NodeExecutionException, D7).
 *
 * The user messages never embed data values; the technical detail is
 * meant for storage/logs only.
 */
final class HttpClientException extends Exception
{
    public function __construct(
        public readonly string $reason,
        public readonly string $userMessage,
        private readonly string $technicalDetail = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($userMessage, $code, $previous);
    }

    /**
     * The url is absent, malformed or uses a non-http(s) scheme.
     */
    public static function invalidUrl(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'invalid_url',
            userMessage: __('L’URL de la requête est invalide.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The host is private/reserved/unresolvable (SSRF guard), or a
     * redirect target violates the guard.
     */
    public static function blockedHost(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'blocked_host',
            userMessage: __('La destination de la requête est interdite par la politique de sécurité.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The request could not reach the destination (dns, connection, redirects).
     */
    public static function networkError(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'network_error',
            userMessage: __('La requête HTTP a échoué (erreur réseau).'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The request exceeded the configured response timeout.
     */
    public static function timeout(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'timeout',
            userMessage: __('La requête HTTP a dépassé le délai d’attente.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The response exceeds the configured size ceiling.
     */
    public static function responseTooLarge(int $maxBytes): self
    {
        return new self(
            reason: 'response_too_large',
            userMessage: __('La réponse dépasse la taille maximale autorisée.'),
            technicalDetail: 'Response size above ceiling ('.$maxBytes.' bytes).',
        );
    }

    /**
     * Get the technical detail for log reporting (never shown to users).
     */
    public function technicalDetail(): string
    {
        return $this->technicalDetail;
    }
}
