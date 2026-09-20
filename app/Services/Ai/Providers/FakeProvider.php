<?php

namespace App\Services\Ai\Providers;

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Data\Ai\AiUsage;
use App\Services\Ai\AiJsonSchema;
use App\Services\Ai\AiProvider;
use Closure;

/**
 * Deterministic driver for tests and local dev (no key, no network).
 *
 * Default behaviour: the answer text embeds the user prompt; when a schema
 * is requested, the structured payload carries the demo value of every
 * field (a classification node answers the first label, an extraction node
 * returns a conforming object). Tests inject a closure for full control.
 */
final class FakeProvider implements AiProvider
{
    /**
     * @param  Closure(AiRequest): AiResponse|null  $handler  Tests only — défaut : comportement déterministe.
     */
    public function __construct(private readonly ?Closure $handler = null) {}

    public function complete(AiRequest $request): AiResponse
    {
        if ($this->handler !== null) {
            return ($this->handler)($request);
        }

        $usage = new AiUsage(0, 0);

        if ($request->jsonSchema === null) {
            return new AiResponse(
                text: 'Démo (FakeProvider) : '.$request->userPrompt,
                structured: null,
                usage: $usage,
                provider: $request->provider,
                model: $request->model,
            );
        }

        $structured = [];

        foreach ($request->jsonSchema as $field => $type) {
            $structured[$field] = AiJsonSchema::demoValue($type);
        }

        return new AiResponse(
            text: (string) json_encode($structured, JSON_UNESCAPED_UNICODE),
            structured: $structured,
            usage: $usage,
            provider: $request->provider,
            model: $request->model,
        );
    }
}
