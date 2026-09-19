<?php

namespace App\Data\Workflow;

use App\Enums\NodeCategory;

readonly class NodeDefinition
{
    /**
     * @param  array<int, array{id: string, label: string|null, position: float}>  $outputs
     * @param  array<int, array{key: string, label: string, type: 'text'|'textarea'|'select'|'range', required: bool, placeholder: string|null, options: array<int, string>|null, min: float|null, max: float|null, step: float|null, mono: bool}>  $fields
     */
    public function __construct(
        public string $type,
        public NodeCategory $category,
        public string $label,
        public string $description,
        public string $icon,
        public bool $input,
        public array $outputs,
        public array $fields,
    ) {
        //
    }
}
