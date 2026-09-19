<?php

namespace App\Services\Workflow\Exception;

use Exception;
use Throwable;

/**
 * A node execution failure carrying a machine reason and a user-safe message.
 *
 * The user message never embeds data values (master §16); the technical
 * detail is meant for storage/logs only.
 */
final class NodeExecutionException extends Exception
{
    /**
     * @param  string  $reason  Catalogued reason ('path_not_found', 'exception', …).
     * @param  string  $userMessage  French, UI-displayable, free of data values.
     * @param  string  $technicalDetail  Technical context, logs only.
     */
    public function __construct(
        public readonly string $reason,
        public readonly string $userMessage,
        private readonly string $technicalDetail = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($userMessage, $code, $previous);
    }

    public static function pathNotFound(string $path): self
    {
        return new self(
            reason: 'path_not_found',
            userMessage: __('Le chemin « :path » est introuvable dans le contexte.', ['path' => $path]),
        );
    }

    /**
     * Get the technical detail for log reporting (never shown to users).
     */
    public function technicalDetail(): string
    {
        return $this->technicalDetail;
    }
}
