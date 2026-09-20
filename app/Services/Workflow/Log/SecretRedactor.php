<?php

namespace App\Services\Workflow\Log;

/**
 * Masks sensitive values and bounds string sizes before a log write (D6).
 *
 * The traversal preserves the structure (nested objects and lists): a key
 * is masked when it EXACTLY matches a configured `redacted_keys` entry —
 * case-insensitively — or ends with a `redacted_key_suffixes` entry
 * (catches `stripe_api_key`, `webhook_token`…). The masked value is the
 * literal '[masqué]'.
 *
 * The config is re-read on EVERY call: long-lived queue workers must never
 * keep a frozen copy of it (phase 6 directive).
 */
final class SecretRedactor
{
    /**
     * The masked placeholder.
     */
    public const string Masked = '[masqué]';

    /**
     * Marker replacing a branch beyond the depth cap — a fail-safe against
     * pathologically deep payloads that would otherwise exhaust memory.
     */
    public const string DepthMarker = '[profondeur max]';

    /**
     * Hard recursion cap (levels of arrays).
     */
    private const int MaxDepth = 32;

    /**
     * Redact one value: mask sensitive keys, truncate long strings, cap
     * recursion. Non-string scalars (int, float, bool, null) pass through.
     */
    public function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MaxDepth) {
            return self::DepthMarker;
        }

        if (is_string($value)) {
            return $this->truncate($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $redactedKeys = array_values(array_map(
            strtolower(...),
            (array) config('workflows.logs.redacted_keys', []),
        ));
        $redactedSuffixes = array_values(array_map(
            strtolower(...),
            (array) config('workflows.logs.redacted_key_suffixes', []),
        ));

        $result = [];

        foreach ($value as $key => $item) {
            $keyString = (string) $key;

            if ($this->isSensitive($keyString, $redactedKeys, $redactedSuffixes)) {
                $result[$key] = self::Masked;

                continue;
            }

            $result[$key] = $this->redact($item, $depth + 1);
        }

        return $result;
    }

    /**
     * Whether the key is an exact (case-insensitive) or suffix match.
     *
     * @param  list<string>  $redactedKeys
     * @param  list<string>  $redactedSuffixes
     */
    private function isSensitive(string $key, array $redactedKeys, array $redactedSuffixes): bool
    {
        $lowered = strtolower($key);

        foreach ($redactedKeys as $candidate) {
            if ($lowered === $candidate) {
                return true;
            }
        }

        foreach ($redactedSuffixes as $suffix) {
            if ($suffix !== '' && str_ends_with($lowered, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cut the string at the configured character budget, with an ellipsis.
     */
    private function truncate(string $value): string
    {
        $maxChars = (int) config('workflows.logs.max_string_chars', 2000);

        if ($maxChars > 0 && mb_strlen($value) > $maxChars) {
            return mb_substr($value, 0, $maxChars).'…';
        }

        return $value;
    }
}
