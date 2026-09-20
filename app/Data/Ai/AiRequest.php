<?php

namespace App\Data\Ai;

/**
 * One completion request, addressed to a provider driver.
 *
 * Carries no secret: the API key is read by the provider from the config
 * at call time. Internal DTO — never serialized, never in props.
 */
final readonly class AiRequest
{
    /**
     * @param  string  $provider  Driver id : 'fake' | 'openai' | 'anthropic' (jamais une clé).
     * @param  string  $model  Model id SANS préfixe provider (ex. 'mon-modele').
     * @param  array<string, string>|null  $jsonSchema  Schéma simple {champ: type} — types
     *                                                  'text'|'number'|'boolean'|'enum:v1,v2,…'. Null = texte libre.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $systemPrompt,
        public string $userPrompt,
        public float $temperature,
        public int $maxTokens,
        public ?array $jsonSchema = null,
        public int $timeoutSeconds = 30,
    ) {}
}
