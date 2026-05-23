<?php

declare(strict_types=1);

use App\Http\Controllers\AssessmentResultController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API routes
|--------------------------------------------------------------------------
|
| Loaded by App\Providers\TenancyServiceProvider::mapRoutes() — NOT by
| Laravel's default routing. That means:
|   - no automatic `api` middleware group → we add it explicitly below
|   - no automatic `/api` prefix         → we add `/api/v1` explicitly below
|
| Every request that lands here has already passed InitializeTenancyBySubdomain,
| so the default DB connection points at `tenant_<uuid>` and `tenant()` is set.
*/

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('api/v1')
    ->group(function (): void {
        // Identity — public auth (no session required, but throttled)
        Route::post('auth/login', [AuthController::class, 'login'])
            ->middleware('throttle.login')
            ->name('api.v1.auth.login');

        Route::post('auth/mfa/verify', [AuthController::class, 'verifyMfa'])
            ->name('api.v1.auth.mfa.verify');

        // Identity — session-bound (auth wiring lands in Layer 3)
        Route::middleware('auth')->group(function (): void {
            Route::post('auth/logout', [AuthController::class, 'logout'])
                ->name('api.v1.auth.logout');

            Route::post('auth/refresh', [AuthController::class, 'refresh'])
                ->name('api.v1.auth.refresh');

            Route::post('users', [UserController::class, 'store'])
                ->name('api.v1.users.store');

            Route::post('users/{userId}/reset-password', [UserController::class, 'resetPassword'])
                ->whereUuid('userId')
                ->name('api.v1.users.reset-password');

            Route::post('users/{userId}/deactivate', [UserController::class, 'deactivate'])
                ->whereUuid('userId')
                ->name('api.v1.users.deactivate');

            Route::patch('roles/{roleId}', [RoleController::class, 'update'])
                ->whereUuid('roleId')
                ->name('api.v1.roles.update');

            Route::post('roles/{roleId}/users/{userId}', [RoleController::class, 'assignToUser'])
                ->whereUuid('roleId')
                ->whereUuid('userId')
                ->name('api.v1.roles.assign');

            Route::delete('roles/{roleId}/users/{userId}', [RoleController::class, 'removeFromUser'])
                ->whereUuid('roleId')
                ->whereUuid('userId')
                ->name('api.v1.roles.unassign');
        });

        Route::post('exams', [ExamController::class, 'store'])
            ->name('api.v1.exams.store');

        Route::get('exams/{examId}', [ExamController::class, 'show'])
            ->whereUuid('examId')
            ->name('api.v1.exams.show');

        Route::post('exams/{examId}/publish', [ExamController::class, 'publish'])
            ->whereUuid('examId')
            ->name('api.v1.exams.publish');

        Route::post('exams/{examId}/archive', [ExamController::class, 'archive'])
            ->whereUuid('examId')
            ->name('api.v1.exams.archive');

        Route::post('exam-sessions', [ExamSessionController::class, 'start'])
            ->name('api.v1.exam-sessions.start');

        Route::get('exam-sessions/{sessionId}', [ExamSessionController::class, 'show'])
            ->whereUuid('sessionId')
            ->name('api.v1.exam-sessions.show');

        Route::post('exam-sessions/{sessionId}/responses', [ExamSessionController::class, 'submitResponse'])
            ->whereUuid('sessionId')
            ->name('api.v1.exam-sessions.submit-response');

        Route::post('exam-sessions/{sessionId}/terminate', [ExamSessionController::class, 'terminate'])
            ->whereUuid('sessionId')
            ->name('api.v1.exam-sessions.terminate');

        Route::get('exam-sessions/{sessionId}/result', [AssessmentResultController::class, 'index'])
            ->whereUuid('sessionId')
            ->name('api.v1.exam-sessions.result');
    });
