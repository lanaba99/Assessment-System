<?php

declare(strict_types=1);

use App\Http\Controllers\AssessmentResultController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\Identity\AuthController;
use App\Http\Controllers\Identity\IdentityController;
use App\Http\Controllers\Identity\RoleController;
use App\Http\Controllers\Identity\SecurityController;
use App\Http\Controllers\Identity\SystemController;
use App\Http\Controllers\Identity\UserController;
use App\Http\Controllers\QuestionBank\CategoryController;
use App\Http\Controllers\QuestionBank\QuestionController;
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
        // -----------------------------------------------------------------
        // Identity — public (no session required)
        // -----------------------------------------------------------------
        Route::post('auth/login', [AuthController::class, 'login'])
            ->middleware('throttle.login')
            ->name('api.v1.auth.login');

        Route::post('auth/mfa/verify', [AuthController::class, 'verifyMfa'])
            ->name('api.v1.auth.mfa.verify');

        Route::post('auth/password/forgot', [AuthController::class, 'forgotPassword'])
            ->name('api.v1.auth.password.forgot');

        Route::post('auth/password/reset', [AuthController::class, 'resetPassword'])
            ->name('api.v1.auth.password.reset');

        Route::post('auth/accept-invite', [AuthController::class, 'acceptInvite'])
            ->name('api.v1.auth.accept-invite');

        Route::get('system/status', [SystemController::class, 'status'])
            ->name('api.v1.system.status');

        // -----------------------------------------------------------------
        // Identity — session-bound
        // -----------------------------------------------------------------
        Route::middleware('auth')->group(function (): void {
            Route::post('auth/logout', [AuthController::class, 'logout'])
                ->name('api.v1.auth.logout');

            Route::post('auth/refresh', [AuthController::class, 'refresh'])
                ->name('api.v1.auth.refresh');

            Route::post('users', [UserController::class, 'store'])
                ->name('api.v1.users.store');

            Route::get('users', [UserController::class, 'index'])
                ->name('api.v1.users.index');

            Route::post('users/invite', [UserController::class, 'invite'])
                ->name('api.v1.users.invite');

            Route::post('users/{userId}/reset-password', [UserController::class, 'resetPassword'])
                ->whereUuid('userId')
                ->name('api.v1.users.reset-password');

            Route::post('users/{userId}/deactivate', [UserController::class, 'deactivate'])
                ->whereUuid('userId')
                ->name('api.v1.users.deactivate');

            Route::prefix('roles')->group(function (): void {
                Route::get('/', [RoleController::class, 'index'])
                    ->name('api.v1.roles.index');

                Route::patch('{roleId}', [RoleController::class, 'update'])
                    ->whereUuid('roleId')
                    ->name('api.v1.roles.update');

                Route::post('{roleId}/users/{userId}', [RoleController::class, 'assignToUser'])
                    ->whereUuid('roleId')
                    ->whereUuid('userId')
                    ->name('api.v1.roles.assign');

                Route::delete('{roleId}/users/{userId}', [RoleController::class, 'removeFromUser'])
                    ->whereUuid('roleId')
                    ->whereUuid('userId')
                    ->name('api.v1.roles.unassign');
            });

            Route::prefix('security')->group(function (): void {
                Route::get('policies', [SecurityController::class, 'policies'])
                    ->name('api.v1.security.policies.show');

                Route::patch('policies', [SecurityController::class, 'updatePolicies'])
                    ->name('api.v1.security.policies.update');
            });

            Route::get('identity/profile', [IdentityController::class, 'profile'])
                ->name('api.v1.identity.profile.show');

            Route::patch('identity/profile', [IdentityController::class, 'updateProfile'])
                ->name('api.v1.identity.profile.update');

            Route::get('identity/permissions', [IdentityController::class, 'permissions'])
                ->name('api.v1.identity.permissions.index');

            Route::get('identity/sessions', [IdentityController::class, 'sessions'])
                ->name('api.v1.identity.sessions.index');

            Route::delete('identity/sessions/all', [IdentityController::class, 'deleteAllSessions'])
                ->name('api.v1.identity.sessions.delete-all');

            Route::delete('identity/sessions/{id}', [IdentityController::class, 'deleteSession'])
                ->whereUuid('id')
                ->name('api.v1.identity.sessions.delete');

            // -------------------------------------------------------------
            // Question bank — categories & questions
            // -------------------------------------------------------------
            Route::prefix('categories')->group(function (): void {
                Route::get('tree', [CategoryController::class, 'tree'])
                    ->name('api.v1.categories.tree');

                Route::post('/', [CategoryController::class, 'store'])
                    ->name('api.v1.categories.store');

                Route::patch('{id}/move', [CategoryController::class, 'move'])
                    ->whereUuid('id')
                    ->name('api.v1.categories.move');

                Route::delete('{id}', [CategoryController::class, 'destroy'])
                    ->whereUuid('id')
                    ->name('api.v1.categories.destroy');
            });

            Route::prefix('questions')->group(function (): void {
                Route::get('/', [QuestionController::class, 'index'])
                    ->name('api.v1.questions.index');

                Route::post('/', [QuestionController::class, 'store'])
                    ->name('api.v1.questions.store');

                Route::get('{id}', [QuestionController::class, 'show'])
                    ->whereUuid('id')
                    ->name('api.v1.questions.show');

                Route::match(['put', 'patch'], '{id}', [QuestionController::class, 'update'])
                    ->whereUuid('id')
                    ->name('api.v1.questions.update');

                Route::delete('{id}', [QuestionController::class, 'destroy'])
                    ->whereUuid('id')
                    ->name('api.v1.questions.destroy');
            });
        });

        // -----------------------------------------------------------------
        // Assessment & exams (auth wiring TBD per domain)
        // -----------------------------------------------------------------
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
