<?php

namespace App\Data\Workflow;

/**
 * Explicit error identifying a node, a catalogued reason and a user-safe message.
 */
final readonly class ExecutionError
{
    /**
     * @param  string|null  $nodeKey  Null = graph-level error (no_trigger, cycle…).
     * @param  string  $type  'validation' or the type of the failing node.
     * @param  string  $reason  See the reason catalog in the phase 4 plan §2.7.
     * @param  string  $message  French, UI-displayable, never a data value.
     */
    public function __construct(
        public ?string $nodeKey,
        public string $type,
        public string $reason,
        public string $message,
    ) {}

    /**
     * @return array{nodeKey: string|null, type: string, reason: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'nodeKey' => $this->nodeKey,
            'type' => $this->type,
            'reason' => $this->reason,
            'message' => $this->message,
        ];
    }
}
