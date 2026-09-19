<?php

namespace App\Data\Workflow;

/**
 * What a handler returns for one execution of its node.
 */
final readonly class NodeResult
{
    /**
     * @param  array<string, mixed>  $output
     * @param  string|null  $branch  Branch chosen (true/false…), consumed by the traverser.
     * @param  bool  $isTerminal  Ends the downstream traversal (output node).
     * @param  string|null  $exposeAs  Named alias placed on the output (data.input → « payload »).
     */
    public function __construct(
        public array $output,
        public ?string $branch = null,
        public bool $isTerminal = false,
        public ?string $exposeAs = null,
    ) {}

    /**
     * Output = received input unchanged (trigger.manual, transform without expression…).
     */
    public static function passthrough(NodeContext $context): self
    {
        return new self($context->input);
    }
}
