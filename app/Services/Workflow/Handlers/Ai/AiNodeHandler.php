<?php

namespace App\Services\Workflow\Handlers\Ai;

use App\Data\Ai\AiRequest;
use App\Data\Ai\AiResponse;
use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Ai\AiCompleter;
use App\Services\Ai\Exception\AiProviderException;
use App\Services\Ai\Exception\AiStructuredOutputException;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the five `ai.*` node types (V2): one class parametrized by
 * AiMode — the modes share ~90% of their pipeline (interpolate → request →
 * complete → output + usage), the differences are data (system prompt,
 * schema, output shape), not code.
 *
 * Config contract (D10/D11): {model (`provider/model` composite), prompt,
 * temperature, max_tokens, labels (classification), fields (extraction)}.
 */
final class AiNodeHandler implements NodeHandler
{
    /**
     * Composite `{provider}/{model}` shape (V6).
     */
    private const string ModelPattern = '#^([a-z0-9_-]+)/([A-Za-z0-9._-]+)$#';

    private const string FieldTypePattern = '#^(text|number|boolean)$#';

    private const string FieldKeyPattern = '#^[A-Za-z0-9_]+$#';

    private const int MaxLabels = 20;

    private const int MaxLabelLength = 64;

    private const int MaxFields = 20;

    private const int MaxFieldKeyLength = 64;

    private const int MaxTokensBound = 8192;

    public function __construct(
        private readonly AiMode $mode,
        private readonly AiCompleter $completer,
    ) {}

    public function type(): string
    {
        return $this->mode->typeId();
    }

    /**
     * Validate the config (D11): French messages, first error is surfaced
     * as invalid_config by the WorkflowValidator (existing circuit).
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config): array
    {
        $errors = $this->validateModel($config);

        $temperature = $config['temperature'] ?? null;

        if ($temperature !== null && $temperature !== '' && (! is_numeric($temperature) || (float) $temperature < 0 || (float) $temperature > 1)) {
            $errors[] = __('La température doit être un nombre entre 0 et 1.');
        }

        $maxTokens = $config['max_tokens'] ?? null;

        if ($maxTokens !== null && $maxTokens !== '' && ! $this->isTokenCount($maxTokens)) {
            $errors[] = __('Le maximum de tokens doit être un nombre entre 1 et 8192.');
        }

        if ($this->mode === AiMode::Classification) {
            $errors = $this->validateLabels($config, $errors);
        }

        if ($this->mode === AiMode::Extraction) {
            $errors = $this->validateFields($config, $errors);
        }

        return $errors;
    }

    /**
     * Execute the node (D10): resolve model, interpolate the prompt,
     * complete through the AI boundary and shape the output + usage.
     *
     *
     * @throws NodeExecutionException Reason preserved from the AI exceptions.
     */
    public function execute(NodeContext $context): NodeResult
    {
        [$provider, $model] = $this->resolveModel($context->config['model'] ?? null);

        $labels = [];

        if ($this->mode === AiMode::Classification) {
            $labels = $this->parseLabels($context->config['labels'] ?? null);

            if ($labels === []) {
                throw new NodeExecutionException(
                    reason: 'invalid_config',
                    userMessage: __('Au moins une étiquette est requise.'),
                );
            }
        }

        $request = new AiRequest(
            provider: $provider,
            model: $model,
            systemPrompt: $this->systemPrompt($labels),
            userPrompt: $context->interpolate((string) ($context->config['prompt'] ?? '')),
            temperature: $this->temperature($context->config),
            maxTokens: $this->maxTokens($context->config),
            jsonSchema: $this->jsonSchema($context->config, $labels),
            timeoutSeconds: (int) config('ai.timeout', 30),
        );

        try {
            $response = $this->completer->complete($request);
        } catch (AiStructuredOutputException $exception) {
            throw new NodeExecutionException(
                reason: 'structured_output_invalid',
                userMessage: __('La réponse du fournisseur IA n’a pas pu être mise au format attendu.'),
                technicalDetail: $exception->technicalDetail(),
            );
        } catch (AiProviderException $exception) {
            throw new NodeExecutionException(
                reason: $exception->reason,
                userMessage: $exception->getMessage(),
                technicalDetail: $exception->technicalDetail(),
            );
        }

        return new NodeResult(output: $this->output($response));
    }

    /**
     * Resolve the composite model reference (D10 step 1): empty falls back
     * to the configured default provider + its first model; anything else
     * must match `{provider}/{model}` with BOTH known/enabled and listed.
     *
     * @return array{0: string, 1: string}
     *
     * @throws NodeExecutionException ai_model_unavailable
     */
    private function resolveModel(mixed $configValue): array
    {
        $raw = is_string($configValue) ? trim($configValue) : '';

        if ($raw === '') {
            $providerId = (string) config('ai.default_provider', 'fake');
            $model = $this->providerModels($providerId)[0] ?? '';
        } elseif (preg_match(self::ModelPattern, $raw, $matches) === 1) {
            $providerId = (string) $matches[1];
            $model = (string) $matches[2];
        } else {
            throw $this->modelUnavailable();
        }

        if ($model === '' || ! $this->providerEnabled($providerId) || ! in_array($model, $this->providerModels($providerId), true)) {
            throw $this->modelUnavailable();
        }

        return [$providerId, $model];
    }

    /**
     * Whether the provider is enabled in the config (the key presence drives
     * it — the provider then re-checks the key itself before any HTTP call).
     */
    private function providerEnabled(string $providerId): bool
    {
        return (bool) config("ai.providers.$providerId.enabled", false);
    }

    /**
     * The provider model ids, in config priority order.
     *
     * @return list<string>
     */
    private function providerModels(string $providerId): array
    {
        $models = config("ai.providers.$providerId.models", []);

        return is_array($models) ? array_values(array_filter($models, 'is_string')) : [];
    }

    private function modelUnavailable(): NodeExecutionException
    {
        return new NodeExecutionException(
            reason: 'ai_model_unavailable',
            userMessage: __('Le modèle IA configuré n’est pas disponible. Vérifiez la configuration du serveur.'),
        );
    }

    /**
     * The mode system prompt (D13) — carries the intent, never the JSON
     * mechanics (AiJsonSchema::instruction() is appended by the completer).
     *
     * @param  list<string>  $labels
     */
    private function systemPrompt(array $labels): string
    {
        return match ($this->mode) {
            AiMode::Classification => 'Tu classes un contenu en choisissant exactement une étiquette dans la liste fournie. N’invente jamais d’étiquette.'
                .($labels === [] ? '' : ' Étiquettes possibles : '.implode(', ', $labels).'.'),
            AiMode::Extraction => 'Tu extrais des informations factuelles du contenu fourni, sans inventer de données. Une information absente reste vide ou à sa valeur neutre.',
            AiMode::Summarization => 'Tu produis un résumé fidèle et concis du contenu fourni, dans la langue du contenu, en conservant les faits essentiels.',
            AiMode::Generation => 'Tu produis un texte de qualité en suivant les instructions données.',
            default => 'Tu es un assistant au sein d’un workflow automatisé. Réponds de façon claire, directe et utile, dans la langue de la demande.',
        };
    }

    /**
     * The structured-output schema of the mode (D10 step 3).
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $labels
     * @return array<string, string>|null
     */
    private function jsonSchema(array $config, array $labels): ?array
    {
        if ($this->mode === AiMode::Classification) {
            return ['label' => 'enum:'.implode(',', $labels)];
        }

        if ($this->mode === AiMode::Extraction) {
            return $this->parseFields($config['fields'] ?? null);
        }

        return null;
    }

    /**
     * Parsed classification labels (trimmed, non-empty).
     *
     * @return list<string>
     */
    private function parseLabels(mixed $configValue): array
    {
        if (! is_string($configValue)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $configValue)),
            fn (string $label): bool => $label !== '',
        ));
    }

    /**
     * Parsed extraction fields, `clé: type` per line (V8 — string config,
     * parsed by the handler, never a structure).
     *
     * @return array<string, string>
     */
    private function parseFields(mixed $configValue): array
    {
        if (! is_string($configValue) || trim($configValue) === '') {
            return [];
        }

        $fields = [];

        foreach (explode("\n", $configValue) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $key = trim(substr($line, 0, $colon));
            $type = trim(substr($line, $colon + 1));

            if ($key !== '' && preg_match(self::FieldTypePattern, $type) === 1) {
                $fields[$key] = $type;
            }
        }

        return $fields;
    }

    /**
     * Output shape of the mode (V10): `usage` is a reserved output key of
     * the AI nodes — no runner/DTO change (phase 4 contract intact).
     *
     * @return array<string, mixed>
     */
    private function output(AiResponse $response): array
    {
        return match ($this->mode) {
            AiMode::Classification => [
                'label' => $response->structured['label'] ?? '',
                'usage' => $response->usage->toArray(),
            ],
            AiMode::Extraction => [
                'structured' => $response->structured ?? [],
                'usage' => $response->usage->toArray(),
            ],
            default => [
                'text' => $response->text,
                'usage' => $response->usage->toArray(),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function temperature(array $config): float
    {
        $value = $config['temperature'] ?? null;

        return is_numeric($value) ? (float) $value : (float) config('ai.temperature', 0.7);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function maxTokens(array $config): int
    {
        $value = $config['max_tokens'] ?? null;

        return is_numeric($value) ? (int) $value : (int) config('ai.max_tokens', 2048);
    }

    /**
     * Positive integer string/int within the 1-8192 bound.
     */
    private function isTokenCount(mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return (string) (int) $value === (string) $value
            && (int) $value >= 1
            && (int) $value <= self::MaxTokensBound;
    }

    /**
     * The model reference rule of validate() (D11): absent/empty is
     * tolerated (config default at execution), anything present must
     * resolve to a known enabled provider + listed model.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function validateModel(array $config): array
    {
        $raw = is_string($config['model'] ?? null) ? trim((string) $config['model']) : '';

        if ($raw === '') {
            return [];
        }

        if (preg_match(self::ModelPattern, $raw, $matches) !== 1) {
            return [$this->modelReferenceError()];
        }

        $providerId = (string) $matches[1];
        $model = (string) $matches[2];

        if (! $this->providerEnabled($providerId) || ! in_array($model, $this->providerModels($providerId), true)) {
            return [$this->modelReferenceError()];
        }

        return [];
    }

    private function modelReferenceError(): string
    {
        return __('Le modèle référencé n’existe pas pour ce fournisseur.');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function validateLabels(array $config, array $errors): array
    {
        $labels = $this->parseLabels($config['labels'] ?? null);

        if ($labels === []) {
            $errors[] = __('Au moins une étiquette est requise.');

            return $errors;
        }

        if (count($labels) > self::MaxLabels) {
            $errors[] = __('Au maximum :max étiquettes peuvent être définies.', ['max' => self::MaxLabels]);

            return $errors;
        }

        foreach ($labels as $label) {
            if (mb_strlen($label) > self::MaxLabelLength) {
                $errors[] = __('Une étiquette ne doit pas dépasser :max caractères.', ['max' => self::MaxLabelLength]);

                return $errors;
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function validateFields(array $config, array $errors): array
    {
        $raw = is_string($config['fields'] ?? null) ? trim((string) $config['fields']) : '';

        if ($raw === '') {
            return $errors;
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $raw)),
            fn (string $line): bool => $line !== '',
        ));

        if (count($lines) > self::MaxFields) {
            $errors[] = __('Au maximum :max champs peuvent être définis.', ['max' => self::MaxFields]);

            return $errors;
        }

        foreach ($lines as $line) {
            $colon = strpos($line, ':');

            if ($colon === false) {
                $errors[] = __('Chaque ligne doit suivre le format « clé: type » (types : text, number, boolean).');

                continue;
            }

            $key = trim(substr($line, 0, $colon));
            $type = trim(substr($line, $colon + 1));

            if (! preg_match(self::FieldKeyPattern, $key) || mb_strlen($key) > self::MaxFieldKeyLength) {
                $errors[] = __('La clé de champ « :key » est invalide (alphanumérique, :max caractères max).', ['key' => $key, 'max' => self::MaxFieldKeyLength]);

                continue;
            }

            if (preg_match(self::FieldTypePattern, $type) !== 1) {
                $errors[] = __('Chaque ligne doit suivre le format « clé: type » (types : text, number, boolean).');
            }
        }

        return $errors;
    }
}
