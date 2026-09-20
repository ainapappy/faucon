<?php

namespace App\Services\Workflow\Exception;

use App\Data\Workflow\ExecutionError;

/**
 * The workflow being published as a team template is not executable
 * (phase 9): the validator refused it. Carries the validation errors so
 * the controller surfaces the first message under `errors.graph`.
 */
final class TemplateNotPublishableException extends \Exception
{
    /**
     * @param  list<ExecutionError>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Le workflow ne peut pas être publié comme template : son graphe n’est pas exécutable.');
    }

    /**
     * The first user-displayable message (French) of the validation errors.
     */
    public function firstMessage(): string
    {
        return $this->errors[0]->message;
    }
}
