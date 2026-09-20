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
 * Anthropic driver: DTO ↔ POST {base_url}/v1/messages (D7).
 *
 * Reads `ai.providers.anthropic.{key,base_url,version}` at call time — the
 * key never leaves this class. `max_tokens` is MANDATORY on the Messages
 * API; the system prompt travels in the dedicated `system` field.
 */
final class AnthropicProvider implements AiProvider
{
    use MapsProviderErrors;

    public function complete(AiRequest $request): AiResponse
    {
        $key = (string) config('ai.providers.anthropic.key');

        if (! filled($key)) {
            throw AiProviderException::notConfigured('ANTHROPIC_API_KEY is not configured.');
        }

        $endpoint = rtrim((string) config('ai.providers.anthropic.base_url', 'https://api.anthropic.com'), '/').'/v1/messages';
        $version = (string) config('ai.providers.anthropic.version', '2023-06-01');

        $body = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'system' => $request->systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
        ];

        try {
            $response = $this->transport($request)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => $version,
                ])
                ->post($endpoint, $body);
        } catch (Throwable $exception) {
            throw $this->mapTransportException($exception);
        }

        return $this->toAiResponse($request, $response);
    }

    /**
     * Translate the v1/messages answer: concat of the `text` content blocks.
     */
    private function toAiResponse(AiRequest $request, Response $response): AiResponse
    {
        $payload = (array) $response->json();
        $blocks = is_array($payload['content'] ?? null) ? $payload['content'] : [];
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];

        $text = implode('', array_map(
            static fn (mixed $block): string => is_array($block) && ($block['type'] ?? null) === 'text'
                ? (string) ($block['text'] ?? '')
                : '',
            $blocks,
        ));

        return new AiResponse(
            text: $text,
            structured: null,
            usage: new AiUsage(
                (int) ($usage['input_tokens'] ?? 0),
                (int) ($usage['output_tokens'] ?? 0),
            ),
            provider: 'anthropic',
            model: $request->model,
        );
    }
}
