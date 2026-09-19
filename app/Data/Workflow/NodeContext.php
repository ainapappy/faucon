<?php

namespace App\Data\Workflow;

use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\ExecutionContext;

/**
 * What a handler receives: identity, config (never pre-interpolated) and
 * the merged input of already-executed upstream nodes.
 */
final readonly class NodeContext
{
    /**
     * @param  array<string, mixed>  $input  Merged outputs of executed upstream nodes (sample for the trigger).
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $nodeKey,
        public string $nodeType,
        public string $nodeName,
        public array $config,
        public array $input,
        public ExecutionContext $execution,
    ) {}

    /**
     * Strict interpolation of a template over the run context.
     *
     * @throws NodeExecutionException Reason 'path_not_found'.
     */
    public function interpolate(string $template): string
    {
        return $this->execution->interpolate($template);
    }

    /**
     * Raw value when the template is exactly one placeholder, string otherwise.
     *
     * @throws NodeExecutionException Reason 'path_not_found'.
     */
    public function value(string $template): mixed
    {
        return $this->execution->value($template);
    }

    /**
     * Tolerant resolution of a dot path (null when absent).
     */
    public function resolvePath(string $path): mixed
    {
        return $this->execution->resolve($path);
    }
}
