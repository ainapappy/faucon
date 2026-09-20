<?php

namespace App\Services\Ai;

/**
 * Grammar of the simple JSON schema (`{champ: type}`), pure and static.
 *
 * Single source of truth shared by AiCompleter (instruction + validation)
 * and FakeProvider (deterministic demo payloads). Required fields are the
 * schema keys; extra keys in the answer are tolerated (models sometimes
 * add some — strict on the required, permissive on the superfluous).
 */
final class AiJsonSchema
{
    /**
     * Types admitted by the grammar: scalar text, number, boolean and
     * closed enumerations (`enum:v1,v2,…`).
     */
    private const string EnumPrefix = 'enum:';

    /**
     * The French JSON instruction appended to the system prompt by
     * AiCompleter when a schema is requested (D13: the mode prompt carries
     * the intent, this carries the JSON mechanics).
     *
     * @param  array<string, string>  $schema
     */
    public static function instruction(array $schema): string
    {
        $lines = [];

        foreach ($schema as $field => $type) {
            $lines[] = '- "'.$field.'" : '.self::typeLabel($type);
        }

        return "Ta réponse doit être un objet JSON valide contenant exactement ces champs :\n"
            .implode("\n", $lines)."\n"
            .'Réponds UNIQUEMENT avec cet objet JSON, sans aucun texte avant ni après.';
    }

    /**
     * Validate a decoded answer against the schema.
     *
     * @param  array<string, string>  $schema
     * @param  array<string, mixed>  $decoded
     * @return list<string> Erreurs FR (logs), [] si conforme.
     */
    public static function errors(array $schema, array $decoded): array
    {
        $errors = [];

        foreach ($schema as $field => $type) {
            if (! array_key_exists($field, $decoded)) {
                $errors[] = __('Le champ « :field » est requis.', ['field' => $field]);

                continue;
            }

            $error = self::valueError($field, $type, $decoded[$field]);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Deterministic demo value of one type (FakeProvider defaults):
     * 'text' → 'exemple', 'number' → 42, 'boolean' → true, 'enum:…' → 1ʳᵉ valeur.
     */
    public static function demoValue(string $type): mixed
    {
        if (str_starts_with($type, self::EnumPrefix)) {
            $values = explode(',', substr($type, strlen(self::EnumPrefix)));

            return trim((string) $values[0]);
        }

        return match ($type) {
            'number' => 42,
            'boolean' => true,
            default => 'exemple',
        };
    }

    /**
     * Tolerant decode of an answer: markdown fences removed BEFORE
     * json_decode, associative array required (a JSON list is refused).
     *
     * @return array<string, mixed>|null
     */
    public static function decode(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        $clean = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $matches) === 1) {
            $clean = trim((string) $matches[1]);
        }

        $decoded = json_decode($clean, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Human label of one schema type (instruction rendering).
     */
    private static function typeLabel(string $type): string
    {
        if (str_starts_with($type, self::EnumPrefix)) {
            $values = str_replace(',', ', ', substr($type, strlen(self::EnumPrefix)));

            return 'l’une des valeurs : '.$values;
        }

        return match ($type) {
            'number' => 'un nombre',
            'boolean' => 'un booléen',
            default => 'une chaîne de caractères',
        };
    }

    /**
     * The French error for one offending value, or null when it conforms.
     */
    private static function valueError(string $field, string $type, mixed $value): ?string
    {
        $label = __('Le champ « :field »', ['field' => $field]);

        if (str_starts_with($type, self::EnumPrefix)) {
            $values = array_map('trim', explode(',', substr($type, strlen(self::EnumPrefix))));

            if (! is_string($value) || ! in_array(trim($value), $values, true)) {
                return $label.' '.__('doit être l’une des valeurs : :values.', ['values' => implode(', ', $values)]);
            }

            return null;
        }

        $invalid = match ($type) {
            'number' => ! is_int($value) && ! is_float($value),
            'boolean' => ! is_bool($value),
            default => ! is_string($value),
        };

        if ($invalid) {
            $expected = $type === 'number'
                ? __('un nombre')
                : ($type === 'boolean' ? __('un booléen') : __('une chaîne de caractères'));

            return $label.' '.__('doit être :type.', ['type' => $expected]);
        }

        return null;
    }
}
