<?php

namespace App\Services\Ai\Providers;

/**
 * OpenAI driver: DTO ↔ POST {base_url}/chat/completions (D7).
 *
 * Reads `ai.providers.openai.{key,base_url}` at call time — the key never
 * leaves this class. The modern body field (max_tokens is deprecated on
 * recent models); the shared chat/completions translation lives in
 * OpenAiCompatibleProvider.
 */
final class OpenAiProvider extends OpenAiCompatibleProvider
{
    protected function configPrefix(): string
    {
        return 'openai';
    }

    protected function providerId(): string
    {
        return 'openai';
    }

    protected function tokenLimitField(): string
    {
        return 'max_completion_tokens';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }
}
