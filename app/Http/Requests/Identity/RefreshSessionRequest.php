<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class RefreshSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'uuid'],
        ];
    }

    public function sessionIdValue(): string
    {
        return (string) $this->validated('session_id');
    }
}
