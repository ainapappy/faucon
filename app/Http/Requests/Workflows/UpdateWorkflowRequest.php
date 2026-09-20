<?php

namespace App\Http\Requests\Workflows;

use App\Data\Workflow\ExecutionError;
use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\Workflow;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkflowRequest extends FormRequest
{
    public function __construct(
        private readonly WorkflowGraphMapper $graphs,
        private readonly WorkflowValidator $graphValidator,
    ) {}

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

                if (! $workflow->triggerNode()->exists()) {
                    $validator->errors()->add('status', __('A workflow needs at least one trigger node to be activated.'));

                    return;
                }

                // Full executability applies to a transition to active: a
                // workflow already active is never re-blocked, and neither
                // deactivation nor metadata updates are ever blocked.
                if ($workflow->status === WorkflowStatus::Active) {
                    return;
                }

                foreach ($this->executabilityErrors($workflow) as $error) {
                    $validator->errors()->add('status', $error->message);
                }
            },
        ];
    }

    /**
     * Run the same executability validation as the engine (mapper +
     * WorkflowValidator) over the persisted graph — the exact path used
     * at run and publish time.
     *
     * @return list<ExecutionError>
     */
    private function executabilityErrors(Workflow $workflow): array
    {
        [$nodes, $edges] = $this->graphs->map($workflow);

        return $this->graphValidator->validate($nodes, $edges);
    }
}
