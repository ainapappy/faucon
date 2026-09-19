<?php

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TestRunWorkflowRequest extends FormRequest
{
    /**
     * Decoded sample input, memoized (consumed by the action).
     *
     * @var array<string, mixed>|null
     */
    private ?array $sampleInput = null;

    /**
     * The sample input is a JSON string in the textarea payload.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'input' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Decode once and validate the JSON form: object shape, depth 10.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $raw = $this->input('input');

                if (! is_string($raw) || $raw === '') {
                    return;
                }

                $decoded = json_decode($raw, true, 10);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = json_last_error() === JSON_ERROR_DEPTH
                        ? 'Le JSON est trop profond (10 niveaux max).'
                        : "L'input doit être un JSON valide.";

                    $validator->errors()->add('input', $message);

                    return;
                }

                if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                    $validator->errors()->add('input', "L'input doit être un objet JSON.");

                    return;
                }

                $this->sampleInput = $decoded;
            },
        ];
    }

    /**
     * The decoded sample input for the trigger (empty object when absent).
     *
     * @return array<string, mixed>
     */
    public function sampleInput(): array
    {
        return $this->sampleInput ?? [];
    }
}
