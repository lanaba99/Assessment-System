<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflows;

use App\Domains\Workflows\Models\ApprovalWorkflow;
use App\Domains\Workflows\Repositories\ApprovalWorkflowRepository;
use Illuminate\Foundation\Http\FormRequest;

class RejectWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $workflow = app(ApprovalWorkflowRepository::class)->findById(
            (string) tenant()->getKey(),
            (string) $this->route('workflowId'),
        );

        if ($workflow === null) {
            abort(404, 'Workflow not found.');
        }

        return $user->can('reject', $workflow);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
