<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Policies;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Identity\Contracts\AuthorizationService;
use App\Domains\Identity\Models\User;

class ExamPolicy
{
    public function __construct(
        private readonly AuthorizationService $auth,
    ) {
    }

    public function viewAny(User $actor): bool
    {
        return $this->hasPermission($actor, 'exams.view');
    }

    public function view(User $actor, Exam $exam): bool
    {
        if (! $this->sameTenant($actor, $exam)) {
            return false;
        }

        return $this->hasPermission($actor, 'exams.view');
    }

    public function create(User $actor): bool
    {
        return $this->hasPermission($actor, 'exams.manage');
    }

    public function update(User $actor, Exam $exam): bool
    {
        if (! $this->sameTenant($actor, $exam)) {
            return false;
        }

        return $this->hasPermission($actor, 'exams.manage');
    }

    public function delete(User $actor, Exam $exam): bool
    {
        if (! $this->sameTenant($actor, $exam)) {
            return false;
        }

        return $this->hasPermission($actor, 'exams.manage');
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $this->auth->userHasPermission(
            (string) $actor->tenant_id,
            (string) $actor->id,
            $permission,
        );
    }

    private function sameTenant(User $actor, Exam $exam): bool
    {
        return (string) $actor->tenant_id === (string) $exam->tenant_id;
    }
    public function publish(User $actor, Exam $exam): bool
    {
        if (! $this->sameTenant($actor, $exam)) {
            return false;
        }

        return $this->hasPermission($actor, 'exams.manage');
    }

    public function archive(User $actor, Exam $exam): bool
    {
        if (! $this->sameTenant($actor, $exam)) {
            return false;
        }

        return $this->hasPermission($actor, 'exams.manage');
    }
}
