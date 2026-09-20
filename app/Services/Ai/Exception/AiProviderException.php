<?php

namespace App\Services\Ai\Exception;

use Exception;
use Throwable;

/**
 * A provider transport/config failure carrying a machine reason and a
 * user-safe message (mirror of HttpClientException, D8).
 *
 * The user messages never embed keys or payloads; the technical detail is
 * meant for storage/logs only.
 */
final class AiProviderException extends Exception
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
     * The provider did not answer within the configured timeout.
     */
    public static function timeout(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_timeout',
            userMessage: __('Le fournisseur IA n’a pas répondu à temps.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The provider could not be reached (dns, connection).
     */
    public static function unreachable(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_unreachable',
            userMessage: __('Le fournisseur IA est injoignable.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The provider answered 429 after every transport retry.
     */
    public static function rateLimited(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_rate_limited',
            userMessage: __('Le fournisseur IA a reçu trop de requêtes. Réessayez plus tard.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The configured key was refused (401/403).
     */
    public static function authFailed(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_auth_failed',
            userMessage: __('L’authentification auprès du fournisseur IA a échoué. Vérifiez la clé configurée sur le serveur.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The provider refused the request (other 4xx: model or parameters).
     */
    public static function invalidRequest(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_invalid_request',
            userMessage: __('La demande au fournisseur IA a été refusée (modèle ou paramètres invalides).'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * The provider answered 5xx after every transport retry.
     */
    public static function providerError(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_error',
            userMessage: __('Le fournisseur IA a rencontré une erreur interne.'),
            technicalDetail: $technicalDetail,
        );
    }

    /**
     * No usable driver/key for the requested provider (checked BEFORE any HTTP call).
     */
    public static function notConfigured(string $technicalDetail = ''): self
    {
        return new self(
            reason: 'provider_not_configured',
            userMessage: __('Aucune clé n’est configurée pour ce fournisseur IA.'),
            technicalDetail: $technicalDetail,
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
