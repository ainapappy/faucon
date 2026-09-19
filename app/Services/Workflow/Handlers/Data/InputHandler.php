<?php

namespace App\Services\Workflow\Handlers\Data;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the `data.input` node type: exposes its input under a named
 * alias (`{{ payload.* }}` by default) in addition to its node key.
 */
final class InputHandler implements NodeHandler
{
    /**
     * The reserved alias set by the runner for the trigger output.
     */
    private const string ReservedAlias = 'trigger';

    /**
     * Regex for a variable name: letter or underscore, then letters,
     * digits or underscores, up to 64 characters.
     */
    private const string NamePattern = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/';

    public function type(): string
    {
        return 'data.input';
    }

    public function validate(array $config): array
    {
        $name = $config['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return [];
        }

        if ($name === self::ReservedAlias || preg_match(self::NamePattern, $name) !== 1) {
            return [__('Le nom de la variable « :name » est invalide.', ['name' => $name])];
        }

        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        $name = $context->config['name'] ?? '';

        return new NodeResult(
            output: $context->input,
            exposeAs: is_string($name) && $name !== '' ? $name : 'payload',
        );
    }
}
