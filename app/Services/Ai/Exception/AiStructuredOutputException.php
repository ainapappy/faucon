<?php

namespace App\Services\Ai\Exception;

use Exception;

/**
 * The provider answer never matched the requested JSON schema, even after
 * the single format-reminder retry (D8).
 *
 * Message and detail are log-only material: the user-facing message is
 * built by the node handler.
 */
final class AiStructuredOutputException extends Exception
{
    /**
     * @param  list<string>  $errors  Erreurs de validation de la dernière tentative (logs uniquement).
     * @param  string  $rawText  Réponse brute de la dernière tentative (logs uniquement).
     */
    public function __construct(
        private readonly array $errors = [],
        private readonly string $rawText = '',
    ) {
        parent::__construct(__('La réponse du fournisseur IA n’est pas conforme au format demandé.'));
    }

    /**
     * Validation errors plus the raw answer, for storage/logs only (never the UI).
     */
    public function technicalDetail(): string
    {
        $detail = implode(' ', $this->errors);

        if ($this->rawText !== '') {
            $detail .= ' | Réponse brute : '.mb_substr($this->rawText, 0, 500);
        }

        return $detail;
    }
}
