<?php

namespace App\Services\Workflow\Handlers\Ai;

/**
 * The five AI node modes (V2): one enum, one handler, five registrations.
 *
 * String values are the `ai.<mode>` type ids carried by the catalog, the
 * registry and the stored graphs — typeId() derives them, no duplication.
 */
enum AiMode: string
{
    case Prompt = 'prompt';
    case Classification = 'classification';
    case Extraction = 'extraction';
    case Summarization = 'summarization';
    case Generation = 'generation';

    /**
     * The node type id (`ai.<value>`) — single source (D9).
     */
    public function typeId(): string
    {
        return 'ai.'.$this->value;
    }

    /**
     * The mode for a node type id, null when not an AI type.
     */
    public static function forTypeId(string $type): ?self
    {
        return self::tryFrom(str_starts_with($type, 'ai.') ? substr($type, 3) : $type);
    }

    /**
     * Modes whose answer must conform to the JSON schema.
     */
    public function expectsStructured(): bool
    {
        return $this === self::Classification || $this === self::Extraction;
    }
}
