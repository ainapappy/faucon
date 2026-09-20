<?php

namespace App\Http\Requests\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunWorkflowRequest extends FormRequest
{
    /**
     * The optional input is an arbitrary JSON object — validated by shape
     * here, bounded by byte size like the webhook payload (same config).
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'input' => ['nullable', 'array'],
        ];
    }

    /**
     * Bound the serialized input size and the JSON depth.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->input('input');

                if (! is_array($input)) {
                    return;
                }

                $maxBytes = (int) config('workflows.webhook.max_payload_bytes', 65536);

                if (strlen((string) json_encode($input)) > $maxBytes) {
                    $validator->errors()->add('input', __('L’input est trop volumineux.'));
                }

                if (json_encode($input) === false) {
                    $validator->errors()->add('input', __('L’input doit être un objet JSON.'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runInput(): array
    {
        $input = $this->validated('input');

        return is_array($input) ? $input : [];
    }
}
