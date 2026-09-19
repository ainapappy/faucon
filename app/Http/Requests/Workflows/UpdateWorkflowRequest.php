<?php

namespace App\Http\Requests\Workflows;

use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\Workflow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkflowRequest extends FormRequest
{
    /**
     * Get the workflow resolved from the current team (scoped resolution).
     */
    public function workflow(): Workflow
    {
        return $this->currentTeam()->workflows()
            ->whereKey($this->route('workflow'))
            ->firstOrFail();
    }

    /**
     * Get the team of the request, whatever the raw route parameter is.
     */
    private function currentTeam(): Team
    {
        $team = $this->route('current_team');

        if ($team instanceof Team) {
            return $team;
        }

        return Team::query()->where('slug', (string) $team)->firstOrFail();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(WorkflowStatus::class)],
        ];
    }

    /**
     * Get the after-validation callables (cross-field rules).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('status') !== WorkflowStatus::Active->value) {
                    return;
                }

                $workflow = $this->workflow();

                if ($workflow->triggerNode()->exists()) {
                    return;
                }

                $validator->errors()->add('status', __('A workflow needs at least one trigger node to be activated.'));
            },
        ];
    }
}
