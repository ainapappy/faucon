<?php

namespace App\Services\Ai\Providers;

/**
 * z.ai driver (Zhipu platform, GLM models): DTO ↔ POST {base_url}/chat/completions (D7).
 *
 * Reads `ai.providers.zai.{key,base_url}` at call time — the key never
 * leaves this class. OpenAI-compatible API in the classic dialect: the
 * token limit travels in `max_tokens` (not max_completion_tokens).
 */
final class ZAiProvider extends OpenAiCompatibleProvider
{
    protected function configPrefix(): string
    {
        return 'zai';
    }

    protected function providerId(): string
    {
        return 'zai';
    }

    protected function tokenLimitField(): string
    {
        return 'max_tokens';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.z.ai/api/paas/v4';
    }
}
