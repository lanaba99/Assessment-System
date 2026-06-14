<?php

declare(strict_types=1);

namespace App\Http\Requests\Grading;

use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Repositories\AssessmentResultRepository;
use Illuminate\Foundation\Http\FormRequest;

class PublishResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $tenantId = (string) tenant()->getKey();
        $result = app(AssessmentResultRepository::class)
            ->findBySession($tenantId, (string) $this->route('sessionId'));

        if ($result === null) {
            abort(404, 'Assessment result not found.');
        }

        return $user->can('publishResult', $result);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
