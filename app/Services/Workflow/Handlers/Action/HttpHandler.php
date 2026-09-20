<?php

namespace App\Services\Workflow\Handlers\Action;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\Integration\Exception\HttpClientException;
use App\Services\Integration\HttpClient;
use App\Services\Integration\IntegrationResolver;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\NodeHandler;
use Illuminate\Http\Client\Response;

final class HttpHandler implements NodeHandler
{
    private const array Methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private const array FailurePolicies = ['fail', 'continue'];

    public function __construct(
        private readonly HttpClient $client,
        private readonly IntegrationResolver $integrations,
    ) {}

    public function type(): string
    {
        return 'action.http';
    }

    public function validate(array $config): array
    {
        $errors = [];

        $url = $config['url'] ?? '';

        if (! is_string($url) || trim($url) === '') {
            $errors[] = __('L’URL est requise.');
        }

        $method = $config['method'] ?? 'GET';

        if (! in_array($method, self::Methods, true)) {
            $errors[] = __('La méthode HTTP est invalide.');
        }

        $errors = $this->validateHeaders($config, $errors);

        $failurePolicy = $config['failure_policy'] ?? 'fail';

        if (! in_array($failurePolicy, self::FailurePolicies, true)) {
            $errors[] = __('La politique d’échec est invalide.');
        }

        return $errors;
    }

    /**
     * Execute the node: interpolate, inject integration auth, send through
     * the guard and apply the failure policy (D9).
     *
     * @throws NodeExecutionException
     */
    public function execute(NodeContext $context): NodeResult
    {
        $failurePolicy = in_array($context->config['failure_policy'] ?? null, self::FailurePolicies, true)
            ? (string) $context->config['failure_policy']
            : 'fail';

        $url = $context->interpolate((string) ($context->config['url'] ?? ''));
        $headersRaw = (string) ($context->config['headers'] ?? '');

        $headers = $headersRaw === '' ? [] : $this->parseHeaders($context->interpolate($headersRaw));

        $body = (string) ($context->config['body'] ?? '');

        if ($body !== '') {
            $body = $context->interpolate($body);

            if (! array_key_exists('Content-Type', $headers)) {
                $headers['Content-Type'] = 'application/json';
            }
        } else {
            $body = null;
        }

        $headers = $this->applyIntegrationAuth($headers, $this->resolveIntegration($context->config['integration_id'] ?? null));

        try {
            $response = $this->client->send(
                in_array($context->config['method'] ?? null, self::Methods, true) ? (string) $context->config['method'] : 'GET',
                $url,
                $headers,
                $body,
            );
        } catch (HttpClientException $exception) {
            if ($exception->reason === 'blocked_host' || $exception->reason === 'invalid_url') {
                throw new NodeExecutionException(
                    reason: $exception->reason,
                    userMessage: $exception->getMessage(),
                    technicalDetail: $exception->technicalDetail(),
                );
            }

            if ($failurePolicy === 'continue') {
                return new NodeResult([
                    'status' => 0,
                    'body' => null,
                    'error' => $exception->reason,
                ]);
            }

            throw new NodeExecutionException(
                reason: $exception->reason,
                userMessage: $exception->getMessage(),
                technicalDetail: $exception->technicalDetail(),
            );
        }

        $decoded = $this->decodeBody($response);

        $status = $response->status();

        if ($status < 200 || $status >= 300) {
            if ($failurePolicy === 'continue') {
                return new NodeResult(['status' => $status, 'body' => $decoded]);
            }

            throw new NodeExecutionException(
                reason: 'http_request_failed',
                userMessage: __('La requête HTTP a échoué (statut :code).', ['code' => $status]),
            );
        }

        return new NodeResult(['status' => $status, 'body' => $decoded]);
    }

    /**
     * Append the header block errors (D8 format: one "Key: value" per line).
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function validateHeaders(array $config, array $errors): array
    {
        $headersRaw = $config['headers'] ?? '';

        if (! is_string($headersRaw) || $headersRaw === '') {
            return $errors;
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $headersRaw)),
            fn (string $line): bool => $line !== '',
        ));

        if (count($lines) > 20) {
            $errors[] = __('Au maximum 20 en-têtes peuvent être définis.');

            return $errors;
        }

        foreach ($lines as $line) {
            $colon = strpos($line, ':');

            if ($colon === false) {
                $errors[] = __('Chaque ligne d’en-tête doit suivre le format « Clé: valeur ».');

                continue;
            }

            $key = trim(substr($line, 0, $colon));

            if (mb_strlen($key) > 128) {
                $errors[] = __('La clé d’en-tête ne doit pas dépasser 128 caractères.');
            }

            if (mb_strlen(trim(substr($line, $colon + 1))) > 2000) {
                $errors[] = __('La valeur d’en-tête ne doit pas dépasser 2000 caractères.');
            }
        }

        return $errors;
    }

    /**
     * Parse the "Key: value" header lines (split on the first colon).
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $key = trim(substr($line, 0, $colon));
            $value = trim(substr($line, $colon + 1));

            if ($key !== '') {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * Resolve the referenced integration (id string in the config).
     *
     * @throws NodeExecutionException integration_not_found|invalid_integration_type
     */
    private function resolveIntegration(mixed $configValue): ?Integration
    {
        $id = is_string($configValue) && $configValue !== '' ? $configValue : null;

        if ($id === null) {
            return null;
        }

        $integration = $this->integrations->find($id);

        if ($integration === null) {
            throw new NodeExecutionException(
                reason: 'integration_not_found',
                userMessage: __('L’intégration référencée par ce node n’existe plus.'),
            );
        }

        if ($integration->type !== IntegrationType::GenericHttp) {
            throw new NodeExecutionException(
                reason: 'invalid_integration_type',
                userMessage: __('Le type d’intégration référencé ne convient pas pour ce node.'),
            );
        }

        return $integration;
    }

    /**
     * Merge the integration credentials into the request headers — after
     * the node headers, so the credentials win on collision (D13).
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     *
     * @throws NodeExecutionException integration_invalid
     */
    private function applyIntegrationAuth(array $headers, ?Integration $integration): array
    {
        if ($integration === null) {
            return $headers;
        }

        $credentials = $integration->credentials;
        $auth = $credentials['auth'] ?? 'none';

        if ($auth === 'bearer') {
            $token = $credentials['token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw $this->invalidIntegration();
            }

            $headers['Authorization'] = 'Bearer '.$token;

            return $headers;
        }

        if ($auth === 'basic') {
            $username = $credentials['username'] ?? null;
            $password = $credentials['password'] ?? null;

            if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
                throw $this->invalidIntegration();
            }

            $headers['Authorization'] = 'Basic '.base64_encode($username.':'.$password);

            return $headers;
        }

        if ($auth === 'header') {
            $headerName = $credentials['headerName'] ?? null;
            $headerValue = $credentials['headerValue'] ?? null;

            if (! is_string($headerName) || $headerName === '' || ! is_string($headerValue) || $headerValue === '') {
                throw $this->invalidIntegration();
            }

            $headers[$headerName] = $headerValue;

            return $headers;
        }

        return $headers;
    }

    private function invalidIntegration(): NodeExecutionException
    {
        return new NodeExecutionException(
            reason: 'integration_invalid',
            userMessage: __('Les identifiants de l’intégration sont incomplets.'),
        );
    }

    /**
     * Decode the body: JSON when the content type or the payload itself
     * parses as a JSON object/list, raw text otherwise (D10).
     */
    private function decodeBody(Response $response): mixed
    {
        $raw = $response->body();
        $contentType = strtolower($response->header('Content-Type'));

        if ($contentType !== '' && str_contains($contentType, 'json')) {
            return json_decode($raw, true);
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $raw;
    }
}
