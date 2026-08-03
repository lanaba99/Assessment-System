<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;
use App\Domains\Workflows\Enums\WorkflowType;
use Illuminate\Database\Eloquent\Relations\Relation;

class InitiateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('initiate', ApprovalWorkflow::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resource_type' => ['required', 'string', Rule::in(array_keys(Relation::morphMap()))],
            'resource_id' => ['required', 'uuid'],
            'workflow_type' => ['required', Rule::enum(WorkflowType::class)],
        ];
    }
}
