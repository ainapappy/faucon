<?php

namespace App\Services\Ai\Providers;

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Data\Ai\AiUsage;
use App\Services\Ai\AiProvider;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Providers\Concerns\MapsProviderErrors;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Shared DTO ↔ POST {base_url}/chat/completions translation of the
 * OpenAI-compatible drivers (D7): OpenAI itself and z.ai (GLM models).
 *
 * Hooks: config prefix (ai.providers.{prefix}), response provider id, the
 * token-limit body field (max_completion_tokens vs max_tokens) and the
 * fallback endpoint base. The key never leaves this class (no logs, no
 * exceptions shown to users).
 */
abstract class OpenAiCompatibleProvider implements AiProvider
{
    use MapsProviderErrors;

    /**
     * The instructive response_format type (validation stays the barrier).
     */
    private const string JsonObjectName = 'json_object';

    /**
     * The config prefix under ai.providers.
     */
    abstract protected function configPrefix(): string;

    /**
     * The driver id reflected in the AiResponse.
     */
    abstract protected function providerId(): string;

    /**
     * The body field carrying the token limit (API dialect).
     */
    abstract protected function tokenLimitField(): string;

    /**
     * The fallback endpoint base when the config omits it.
     */
    abstract protected function defaultBaseUrl(): string;

    public function complete(AiRequest $request): AiResponse
    {
        $key = (string) config('ai.providers.'.$this->configPrefix().'.key');

        if (! filled($key)) {
            throw AiProviderException::notConfigured($this->missingKeyMessage());
        }

        $endpoint = rtrim((string) config('ai.providers.'.$this->configPrefix().'.base_url', $this->defaultBaseUrl()), '/').'/chat/completions';

        $body = [
            'model' => $request->model,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
            'temperature' => $request->temperature,
            $this->tokenLimitField() => $request->maxTokens,
        ];

        if ($request->jsonSchema !== null) {
            // Instructive only — the completer's validation stays the barrier.
            $body['response_format'] = ['type' => self::JsonObjectName];
        }

        try {
            $response = $this->transport($request)
                ->withToken($key)
                ->post($endpoint, $body);
        } catch (Throwable $exception) {
            throw $this->mapTransportException($exception);
        }

        return $this->toAiResponse($request, $response);
    }

    /**
     * The env variable name the driver reads (config error message).
     */
    private function missingKeyMessage(): string
    {
        return strtoupper($this->configPrefix()).'_API_KEY is not configured.';
    }

    /**
     * Translate the chat/completions answer (usage defaults to 0/0).
     */
    private function toAiResponse(AiRequest $request, Response $response): AiResponse
    {
        $payload = (array) $response->json();
        $choices = is_array($payload['choices'] ?? null) ? $payload['choices'] : [];
        $choice = is_array($choices[0] ?? null) ? $choices[0] : [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];

        return new AiResponse(
            text: (string) ($message['content'] ?? ''),
            structured: null,
            usage: new AiUsage(
                (int) ($usage['prompt_tokens'] ?? 0),
                (int) ($usage['completion_tokens'] ?? 0),
            ),
            provider: $this->providerId(),
            model: $request->model,
        );
    }
}
