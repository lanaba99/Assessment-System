<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;
use App\Domains\QuestionBank\Models\Question;

class QuestionPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'questions.manage');
    }

    public function view(User $actor, Question $question): bool
    {
        if (! $this->sameTenant($actor, $question)) {
            return false;
        }

        return $this->hasPermission($actor, 'questions.manage');
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'questions.manage');
    }

    public function update(User $actor, Question $question): bool
    {
        if (! $this->sameTenant($actor, $question)) {
            return false;
        }

        return $this->hasPermission($actor, 'questions.manage');
    }

    public function delete(User $actor, Question $question): bool
    {
        if (! $this->sameTenant($actor, $question)) {
            return false;
        }

        return $this->hasPermission($actor, 'questions.manage');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    private function sameTenant(User $actor, Question $question): bool
    {
        return (string) $actor->tenant_id === (string) $question->tenant_id;
    }
}
