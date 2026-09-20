<?php

namespace App\Http\Requests\Workflows;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishWorkflowTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. The category is
     * free-form (no enum): a fixed list would freeze it and require a
     * migration per new category — the gallery filters are derived from
     * the data.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'string', 'max:60'],
        ];
    }
}
