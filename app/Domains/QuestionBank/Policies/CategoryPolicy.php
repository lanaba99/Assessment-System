<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Policies;

use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;
use App\Domains\QuestionBank\Models\Category;

class CategoryPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'categories.manage');
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'categories.manage');
    }

    public function update(User $actor, Category $category): bool
    {
        if (! $this->sameTenant($actor, $category)) {
            return false;
        }

        return $this->hasPermission($actor, 'categories.manage');
    }

    public function delete(User $actor, Category $category): bool
    {
        if (! $this->sameTenant($actor, $category)) {
            return false;
        }

        return $this->hasPermission($actor, 'categories.manage');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    private function sameTenant(User $actor, Category $category): bool
    {
        return (string) $actor->tenant_id === (string) $category->tenant_id;
    }
}
