<?php

namespace App\Data\Ai;

/**
 * Token usage of one completion (zeros when the provider exposes nothing).
 */
final readonly class AiUsage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
    ) {}

    /**
     * Clés phase 6, consommées telles quelles dans l'output du node.
     *
     * @return array{prompt_tokens: int, completion_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
        ];
    }
}
