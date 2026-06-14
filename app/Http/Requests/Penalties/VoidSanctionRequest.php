<?php

declare(strict_types=1);

namespace App\Http\Requests\Penalties;

use App\Domains\Penalties\Models\PenaltySanction;
use App\Domains\Penalties\Repositories\PenaltySanctionRepository;
use Illuminate\Foundation\Http\FormRequest;

class VoidSanctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $sanction = app(PenaltySanctionRepository::class)->findById(
            (string) tenant()->getKey(),
            (string) $this->route('sanctionId'),
        );

        if ($sanction === null) {
            abort(404, 'Sanction not found.');
        }

        return $user->can('voidSanction', $sanction);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
