<?php

namespace App\Services\Workflow;

/**
 * Registry of node handlers, indexed by the type id declared by each handler.
 *
 * Filled explicitly in AppServiceProvider::register() — one line per handler,
 * ready for DI of later phases without touching the registry itself.
 */
final class NodeHandlerRegistry
{
    /**
     * @var array<string, NodeHandler>
     */
    private array $handlers = [];

    /**
     * Register a handler, indexed by its declared type id.
     */
    public function register(NodeHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    /**
     * Whether a handler is registered for the type id.
     */
    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /**
     * Get the handler for the type id, if any.
     */
    public function forType(string $type): ?NodeHandler
    {
        return $this->handlers[$type] ?? null;
    }

    /**
     * Get the registered type ids in registration order.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->handlers);
    }
}
