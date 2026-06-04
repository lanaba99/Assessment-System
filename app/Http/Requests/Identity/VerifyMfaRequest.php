<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'uuid'],
            'one_time_code' => ['required', 'string', 'max:32'],
        ];
    }

    public function sessionIdValue(): string
    {
        return (string) $this->validated('session_id');
    }

    public function oneTimeCodeValue(): string
    {
        return (string) $this->validated('one_time_code');
    }
}
