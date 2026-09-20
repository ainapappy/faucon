<?php

namespace App\Services\Integration;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\Integration\Exception\HttpClientException;
use Closure;
use Throwable;

/**
 * Runs a « Tester la connexion » check for an integration and persists
 * the outcome (last_tested_at + last_test_succeeded).
 */
final class ConnectionTester
{
    public function __construct(
        private readonly HttpClient $client,
        private readonly SmtpTransportFactory $smtpFactory,
        /** @var Closure(array<string, mixed>): void|null Tests only — défaut : probeFor(). */
        private readonly ?Closure $smtpProbe = null,
    ) {}

    /**
     * Test the connection and persist the result.
     *
     * @return array{ok: bool, message: string} — message FR, jamais de credential
     */
    public function test(Integration $integration): array
    {
        $ok = false;
        $message = __('La connexion a échoué.');

        try {
            [$ok, $message] = match ($integration->type) {
                IntegrationType::GenericHttp => $this->testGenericHttp($integration),
                IntegrationType::Smtp => $this->testSmtp($integration),
            };
        } catch (Throwable $exception) {
            report($exception);
        }

        $integration->forceFill([
            'last_tested_at' => now(),
            'last_test_succeeded' => $ok,
        ])->save();

        return ['ok' => $ok, 'message' => $message];
    }

    /**
     * GET the stored baseUrl through the hardened client.
     *
     * @return array{0: bool, 1: string}
     */
    private function testGenericHttp(Integration $integration): array
    {
        $baseUrl = (string) ($integration->credentials['baseUrl'] ?? '');

        try {
            $response = $this->client->send('GET', $baseUrl);
        } catch (HttpClientException $exception) {
            report($exception);

            return [false, __('La connexion a échoué.')];
        }

        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            return [true, __('Connexion établie.')];
        }

        if ($status === 401 || $status === 403) {
            return [false, __('Connexion établie mais authentification refusée (statut :code).', ['code' => $status])];
        }

        return [false, __('La connexion a échoué (statut :code).', ['code' => $status])];
    }

    /**
     * Open a real SMTP connection through the factory (or the injected probe).
     *
     * @return array{0: bool, 1: string}
     */
    private function testSmtp(Integration $integration): array
    {
        $probe = $this->smtpProbe ?? function (array $credentials): void {
            $this->smtpFactory->probeFor($credentials);
        };

        $probe($integration->credentials);

        return [true, __('Connexion SMTP établie.')];
    }
}
