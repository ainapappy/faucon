<?php

namespace App\Services\Workflow;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\Exception\NodeExecutionException;

/**
 * Contract implemented by every node type handler.
 *
 * The type id is declared once by the handler itself (see type()) and is
 * the registry index — no desynchronization is possible between the
 * registry key and the handler.
 */
interface NodeHandler
{
    /**
     * The managed type id (`category.type`) — single source of the type.
     */
    public function type(): string;

    /**
     * Validate the config of a node (declarative, outside execution).
     *
     * @param  array<string, mixed>  $config
     * @return list<string> French error messages; empty list = valid config.
     */
    public function validate(array $config): array;

    /**
     * Execute the node.
     *
     * @throws NodeExecutionException Reason + user-safe message (no data values).
     */
    public function execute(NodeContext $context): NodeResult;
}
