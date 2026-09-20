<?php

namespace App\Data\Ai;

/**
 * One provider answer: the raw text always, the validated structured
 * payload only when a schema was requested.
 */
final readonly class AiResponse
{
    /**
     * @param  string  $text  Réponse texte brute (toujours renseignée).
     * @param  array<string, mixed>|null  $structured  JSON décodé et VALIDÉ quand un schéma était
     *                                                 demandé ; null sinon. Set par AiCompleter, jamais par les providers HTTP.
     */
    public function __construct(
        public string $text,
        public ?array $structured,
        public AiUsage $usage,
        public string $provider,
        public string $model,
    ) {}
}
