<?php

namespace App\Services\Workflow;

/**
 * Mutable per-run variable store consumed through dot paths.
 *
 * Holds node outputs indexed by node key, the reserved `trigger` alias and
 * named variables (exposed via `exposeAs`, e.g. `{{ payload.email }}`).
 */
final class ExecutionContext
{
    /**
     * @var array<string, mixed>
     */
    private array $variables = [];

    public function __construct(private readonly Interpolator $interpolator = new Interpolator) {}

    /**
     * Store the output of an executed node, addressable by its node key.
     *
     * @param  array<string, mixed>  $output
     */
    public function setNodeOutput(string $nodeKey, array $output): void
    {
        $this->variables[$nodeKey] = $output;
    }

    /**
     * Store a named variable (reserved `trigger` alias, `exposeAs` aliases).
     */
    public function setVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * Resolve a dot path over the variables (null when absent).
     */
    public function resolve(string $path): mixed
    {
        return $this->interpolator->resolve($this->variables, $path);
    }

    /**
     * Render a template (throws when a placeholder path is absent).
     */
    public function interpolate(string $template): string
    {
        return $this->interpolator->interpolate($this->variables, $template);
    }

    /**
     * Raw value for a single placeholder, rendered string otherwise.
     */
    public function value(string $template): mixed
    {
        return $this->interpolator->value($this->variables, $template);
    }

    /**
     * Get the raw variables map.
     *
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        return $this->variables;
    }
}
