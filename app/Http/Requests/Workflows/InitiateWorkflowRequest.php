<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use Illuminate\Foundation\Http\FormRequest;

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
            'resource_type' => ['required', 'string', 'max:255'],
            'resource_id' => ['required', 'uuid'],
            'workflow_type' => ['required', 'string', 'max:100'],
        ];
    }
}
