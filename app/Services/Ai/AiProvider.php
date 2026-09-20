<?php

namespace App\Services\Ai;

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Services\Ai\Exception\AiProviderException;

/**
 * Contract of one AI provider driver (OpenAI, Anthropic, fake, …).
 */
interface AiProvider
{
    /**
     * Complete one request. Transport-level retries (429/5xx, backoff court)
     * are the provider's concern; content validation is NOT.
     *
     * @throws AiProviderException provider_timeout|provider_unreachable|provider_rate_limited
     *                             |provider_auth_failed|provider_invalid_request|provider_error
     *                             |provider_not_configured
     */
    public function complete(AiRequest $request): AiResponse;
}
