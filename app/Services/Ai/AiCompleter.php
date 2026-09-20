<?php

namespace App\Services\Ai;

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Exception\AiStructuredOutputException;
use InvalidArgumentException;

/**
 * The single boundary of the AI service (miroir d'EmailSender, D5).
 *
 * The handler knows neither providers nor the manager: it calls
 * AiCompleter::complete() and gets a validated response. When a schema is
 * requested, the provider is instructed to answer in JSON, the answer is
 * validated against the schema, ONE retry with a format reminder follows a
 * failure, then AiStructuredOutputException. Content retries are the
 * completer's concern — transport retries are the providers' concern.
 */
final class AiCompleter
{
    /**
     * The format reminder of the second attempt (D13).
     */
    private const string FormatReminder = 'RAPPEL : ta réponse précédente n’était pas conforme. Réponds UNIQUEMENT avec l’objet JSON demandé, sans aucun texte avant ni après.';

    public function __construct(private readonly AiProviderManager $providers) {}

    /**
     * Complete with structured-output guarantee: when jsonSchema is set, the
     * provider is instructed to answer in JSON, the answer is validated
     * against the schema, ONE retry with a format reminder follows a failure,
     * then AiStructuredOutputException.
     *
     * @throws AiProviderException transport/config
     * @throws AiStructuredOutputException réponse non conforme, après retry
     */
    public function complete(AiRequest $request): AiResponse
    {
        try {
            $provider = $this->providers->provider($request->provider);
        } catch (InvalidArgumentException $exception) {
            throw AiProviderException::notConfigured($exception->getMessage());
        }

        if ($request->jsonSchema === null) {
            return $provider->complete($request);
        }

        $schema = $request->jsonSchema;
        $systemPrompt = $request->systemPrompt."\n\n".AiJsonSchema::instruction($schema);

        $firstResponse = $provider->complete($this->withSystemPrompt($request, $systemPrompt));
        $firstDecoded = AiJsonSchema::decode($firstResponse->text);

        if ($firstDecoded !== null && AiJsonSchema::errors($schema, $firstDecoded) === []) {
            return $this->withStructured($firstResponse, $firstDecoded);
        }

        // One content retry with the format reminder (D5 step 5).
        $secondResponse = $provider->complete($this->withSystemPrompt(
            $request,
            $systemPrompt."\n\n".self::FormatReminder,
        ));
        $secondDecoded = AiJsonSchema::decode($secondResponse->text);

        if ($secondDecoded !== null && AiJsonSchema::errors($schema, $secondDecoded) === []) {
            return $this->withStructured($secondResponse, $secondDecoded);
        }

        throw new AiStructuredOutputException(
            errors: AiJsonSchema::errors($schema, $secondDecoded ?? []),
            rawText: $secondResponse->text,
        );
    }

    /**
     * Rebuild the immutable request with the enriched system prompt.
     */
    private function withSystemPrompt(AiRequest $request, string $systemPrompt): AiRequest
    {
        return new AiRequest(
            provider: $request->provider,
            model: $request->model,
            systemPrompt: $systemPrompt,
            userPrompt: $request->userPrompt,
            temperature: $request->temperature,
            maxTokens: $request->maxTokens,
            jsonSchema: $request->jsonSchema,
            timeoutSeconds: $request->timeoutSeconds,
        );
    }

    /**
     * Rebuild the response with the validated structured payload (D5 step 6).
     *
     * @param  array<string, mixed>  $structured
     */
    private function withStructured(AiResponse $response, array $structured): AiResponse
    {
        return new AiResponse(
            text: $response->text,
            structured: $structured,
            usage: $response->usage,
            provider: $response->provider,
            model: $response->model,
        );
    }
}
