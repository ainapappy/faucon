<?php

namespace App\Services\Workflow;

use App\Services\Workflow\Exception\NodeExecutionException;

/**
 * Strict `{{ path }}` interpolation over a variables map.
 *
 * Resolution walks the dot path by hand: `data_get()` is rejected because it
 * conflates an absent key with a null value and accepts wildcards. There is
 * no evaluation of code, ever — only closed-charset dot paths.
 */
final class Interpolator
{
    /**
     * The `@{{ … }}` escaped form is excluded by the lookbehind and rendered
     * literally after interpolation.
     */
    private const string PlaceholderPattern = '/(?<!@)\{\{\s*([A-Za-z0-9_.\-]*)\s*\}\}/';

    /**
     * Resolve a dot path to its value, or null when absent (tolerant form).
     *
     * @param  array<string, mixed>  $variables
     */
    public function resolve(array $variables, string $path): mixed
    {
        [$found, $value] = $this->walk($variables, $path);

        return $found ? $value : null;
    }

    /**
     * Render a template, throwing when a placeholder path is absent.
     *
     * Inline casts are the native PHP ones: null → '', true → '1', false → '';
     * arrays are JSON-encoded. An existing null value renders as '' without error.
     *
     * @param  array<string, mixed>  $variables
     *
     * @throws NodeExecutionException Reason 'path_not_found'.
     */
    public function interpolate(array $variables, string $template): string
    {
        $rendered = preg_replace_callback(
            self::PlaceholderPattern,
            fn (array $matches): string => $this->renderPlaceholder($variables, $matches),
            $template,
        );

        return str_replace('@{{', '{{', (string) $rendered);
    }

    /**
     * Get the raw value when the template is exactly one placeholder
     * (structure copies preserved), otherwise render the template as a string.
     *
     * @param  array<string, mixed>  $variables
     *
     * @throws NodeExecutionException Reason 'path_not_found'.
     */
    public function value(array $variables, string $template): mixed
    {
        if (preg_match('/^\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}$/', $template, $matches) === 1) {
            [$found, $value] = $this->walk($variables, $matches[1]);

            if (! $found) {
                throw NodeExecutionException::pathNotFound($matches[1]);
            }

            return $value;
        }

        return $this->interpolate($variables, $template);
    }

    /**
     * Walk the path segment by segment, distinguishing an absent key
     * from an explicit null value.
     *
     * @param  array<string, mixed>  $variables
     * @return array{bool, mixed}
     */
    private function walk(array $variables, string $path): array
    {
        $current = $variables;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return [false, null];
            }

            $current = $current[$segment];
        }

        return [true, $current];
    }

    /**
     * Render one placeholder match; an empty path stays literal.
     *
     * @param  array<string, mixed>  $variables
     * @param  array{0: string, 1: string}  $matches
     *
     * @throws NodeExecutionException Reason 'path_not_found'.
     */
    private function renderPlaceholder(array $variables, array $matches): string
    {
        $path = trim($matches[1]);

        if ($path === '') {
            return $matches[0];
        }

        [$found, $value] = $this->walk($variables, $path);

        if (! $found) {
            throw NodeExecutionException::pathNotFound($path);
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }
}
