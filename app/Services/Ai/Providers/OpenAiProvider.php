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
 * OpenAI driver: DTO ↔ POST {base_url}/chat/completions (D7).
 *
 * Reads `ai.providers.openai.{key,base_url}` at call time — the key never
 * leaves this class (no logs, no exceptions shown to users).
 */
final class OpenAiProvider implements AiProvider
{
    use MapsProviderErrors;

    /**
     * The modern body field (max_tokens is deprecated on recent models).
     */
    private const string JsonObjectName = 'json_object';

    public function complete(AiRequest $request): AiResponse
    {
        $key = (string) config('ai.providers.openai.key');

        if (! filled($key)) {
            throw AiProviderException::notConfigured('OPENAI_API_KEY is not configured.');
        }

        $endpoint = rtrim((string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions';

        $body = [
            'model' => $request->model,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
            'temperature' => $request->temperature,
            'max_completion_tokens' => $request->maxTokens,
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
            provider: 'openai',
            model: $request->model,
        );
    }
}
