<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalyticsDashboardController;
use App\Http\Controllers\AssessmentResultController;
use App\Http\Controllers\ExamEngine\ExamController;
use App\Http\Controllers\ExamSession\ExamSessionController;
use App\Http\Controllers\ExamSession\EnrollmentController;
use App\Http\Controllers\Identity\AuthController;
use App\Http\Controllers\Identity\IdentityController;
use App\Http\Controllers\Identity\RoleController;
use App\Http\Controllers\Identity\SecurityController;
use App\Http\Controllers\Identity\SystemController;
use App\Http\Controllers\Identity\UserController;
use App\Http\Controllers\Competency\CompetencyController;
use App\Http\Controllers\QuestionBank\CategoryController;
use App\Http\Controllers\QuestionBank\QuestionController;
use App\Http\Controllers\Cohorts\CohortController;
use App\Http\Controllers\Cohorts\CohortMemberController;
use App\Http\Controllers\Grading\ManualEvaluationController;
use App\Http\Controllers\Grading\ResultPublicationController;
use App\Http\Controllers\Penalties\PenaltyRuleController;
use App\Http\Controllers\Penalties\PenaltySanctionController;
use App\Http\Controllers\Workflows\ApprovalWorkflowController;
use App\Http\Controllers\Proctoring\ProctorEventController;
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
        // Identity — public (no session required) - 6
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
        // Identity — session-bound  - 22 
        // -----------------------------------------------------------------
        // `auth:sanctum` is the guard that actually validates the Bearer
        // tokens minted by AuthController. The bare `auth` middleware fell
        // back to the default `web` (session) guard, which never inspected
        // the token — every authenticated request 401'd.
        Route::middleware('auth:sanctum')->group(function (): void {
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

            Route::get('users/{userId}', [UserController::class, 'show'])
                ->whereUuid('userId')
                ->name('api.v1.users.show');

            Route::post('users/{userId}/reset-password', [UserController::class, 'resetPassword'])
                ->whereUuid('userId')
                ->name('api.v1.users.reset-password');

            Route::post('users/{userId}/deactivate', [UserController::class, 'deactivate'])
                ->whereUuid('userId')
                ->name('api.v1.users.deactivate');

            Route::prefix('roles')->group(function (): void {
                Route::get('/', [RoleController::class, 'index'])
                    ->name('api.v1.roles.index');

                Route::post('/', [RoleController::class, 'store'])
                    ->name('api.v1.roles.store');

                Route::patch('{roleId}', [RoleController::class, 'update'])
                    ->whereUuid('roleId')
                    ->name('api.v1.roles.update');

                Route::delete('{roleId}', [RoleController::class, 'destroy'])
                    ->whereUuid('roleId')
                    ->name('api.v1.roles.destroy');

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
            // Question bank — categories & questions - 9
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

            // -------------------------------------------------------------
            // Competency framework — tree CRUD - 4
            // -------------------------------------------------------------
            Route::prefix('competencies')->group(function (): void {
                Route::get('tree', [CompetencyController::class, 'tree'])
                    ->name('api.v1.competencies.tree');

                Route::post('/', [CompetencyController::class, 'store'])
                    ->name('api.v1.competencies.store');

                Route::patch('{id}/move', [CompetencyController::class, 'move'])
                    ->whereUuid('id')
                    ->name('api.v1.competencies.move');

                Route::delete('{id}', [CompetencyController::class, 'destroy'])
                    ->whereUuid('id')
                    ->name('api.v1.competencies.destroy');
            });

            // -------------------------------------------------------------
            // Exam Engine — template lifecycle - 7
            // -------------------------------------------------------------
            Route::prefix('exams')->group(function (): void {
                Route::get('/', [ExamController::class, 'index'])
                    ->name('api.v1.exams.index');

                Route::post('/', [ExamController::class, 'store'])
                    ->name('api.v1.exams.store');

                Route::get('{examId}', [ExamController::class, 'show'])
                    ->whereUuid('examId')
                    ->name('api.v1.exams.show');

                Route::patch('{examId}', [ExamController::class, 'update'])
                    ->whereUuid('examId')
                    ->name('api.v1.exams.update');

                Route::delete('{examId}', [ExamController::class, 'destroy'])
                    ->whereUuid('examId')
                    ->name('api.v1.exams.destroy');

                Route::post('{examId}/publish', [ExamController::class, 'publish'])
                    ->whereUuid('examId')
                    ->name('api.v1.exams.publish');

                Route::post('{examId}/archive', [ExamController::class, 'archive'])
                    ->whereUuid('examId')
                    ->name('api.v1.exams.archive');
            });

            // -------------------------------------------------------------
            // Cohorts — group management and membership - 8
            // -------------------------------------------------------------
            Route::prefix('cohorts')->group(function (): void {
                Route::get('/', [CohortController::class, 'index'])
                    ->name('api.v1.cohorts.index');

                Route::post('/', [CohortController::class, 'store'])
                    ->name('api.v1.cohorts.store');

                Route::get('{cohortId}', [CohortController::class, 'show'])
                    ->whereUuid('cohortId')
                    ->name('api.v1.cohorts.show');

                Route::patch('{cohortId}', [CohortController::class, 'update'])
                    ->whereUuid('cohortId')
                    ->name('api.v1.cohorts.update');

                Route::delete('{cohortId}', [CohortController::class, 'destroy'])
                    ->whereUuid('cohortId')
                    ->name('api.v1.cohorts.destroy');

                Route::prefix('{cohortId}/members')->group(function (): void {
                    Route::get('/', [CohortMemberController::class, 'index'])
                        ->whereUuid('cohortId')
                        ->name('api.v1.cohorts.members.index');

                    Route::post('/', [CohortMemberController::class, 'store'])
                        ->whereUuid('cohortId')
                        ->name('api.v1.cohorts.members.store');

                    Route::delete('{userId}', [CohortMemberController::class, 'destroy'])
                        ->whereUuid('cohortId')
                        ->whereUuid('userId')
                        ->name('api.v1.cohorts.members.destroy');
                });
            });

            // -------------------------------------------------------------
            // Exam Sessions — candidate lifecycle - 11
            // -------------------------------------------------------------
            Route::prefix('exam-sessions')->group(function (): void {
                Route::post('/', [ExamSessionController::class, 'start'])
                    ->name('api.v1.exam-sessions.start');

                Route::get('{sessionId}', [ExamSessionController::class, 'show'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.show');

                Route::post('{sessionId}/responses', [ExamSessionController::class, 'submitResponse'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.submit-response');

                Route::post('{sessionId}/suspend', [ExamSessionController::class, 'suspend'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.suspend');

                Route::post('{sessionId}/resume', [ExamSessionController::class, 'resume'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.resume');

                Route::post('{sessionId}/complete', [ExamSessionController::class, 'complete'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.complete');

                Route::post('{sessionId}/terminate', [ExamSessionController::class, 'terminate'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.terminate');

                Route::get('{sessionId}/result', [AssessmentResultController::class, 'index'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.result');

                // Heartbeat — candidate keep-alive; does NOT bump version_lock
                Route::post('{sessionId}/heartbeat', [ExamSessionController::class, 'heartbeat'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.heartbeat');

                // Proctoring events — submitted by the browser agent during the session
                Route::post('{sessionId}/proctor-events', [ProctorEventController::class, 'store'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.proctor-events.store');

                Route::get('{sessionId}/proctor-events', [ProctorEventController::class, 'index'])
                    ->whereUuid('sessionId')
                    ->name('api.v1.exam-sessions.proctor-events.index');
            });

            // -------------------------------------------------------------
            // Manual Grading — evaluator workflow - 4
            // -------------------------------------------------------------
            Route::get(
                'exam-sessions/{sessionId}/pending-evaluations',
                [ManualEvaluationController::class, 'pending']
            )
                ->whereUuid('sessionId')
                ->name('api.v1.exam-sessions.pending-evaluations');

            Route::patch(
                'answer-evaluations/{evaluationId}/score',
                [ManualEvaluationController::class, 'score']
            )
                ->whereUuid('evaluationId')
                ->name('api.v1.answer-evaluations.score');

            Route::post(
                'exam-sessions/{sessionId}/result/publish',
                [ResultPublicationController::class, 'publish']
            )
                ->whereUuid('sessionId')
                ->name('api.v1.exam-sessions.result.publish');

            Route::get(
                'exam-sessions/{sessionId}/result/publication-status',
                [ResultPublicationController::class, 'showPublicationStatus']
            )
                ->whereUuid('sessionId')
                ->name('api.v1.exam-sessions.result.publication-status');

            // -------------------------------------------------------------
            // Penalties — rule management and sanction review - 9
            // -------------------------------------------------------------
            Route::prefix('penalty-rules')->group(function (): void {
                Route::get('/', [PenaltyRuleController::class, 'index'])
                    ->name('api.v1.penalty-rules.index');

                Route::post('/', [PenaltyRuleController::class, 'store'])
                    ->name('api.v1.penalty-rules.store');

                Route::get('{ruleId}', [PenaltyRuleController::class, 'show'])
                    ->whereUuid('ruleId')
                    ->name('api.v1.penalty-rules.show');

                Route::patch('{ruleId}', [PenaltyRuleController::class, 'update'])
                    ->whereUuid('ruleId')
                    ->name('api.v1.penalty-rules.update');

                Route::delete('{ruleId}', [PenaltyRuleController::class, 'destroy'])
                    ->whereUuid('ruleId')
                    ->name('api.v1.penalty-rules.destroy');

                Route::post('{ruleId}/activate', [PenaltyRuleController::class, 'activate'])
                    ->whereUuid('ruleId')
                    ->name('api.v1.penalty-rules.activate');

                Route::post('{ruleId}/deactivate', [PenaltyRuleController::class, 'deactivate'])
                    ->whereUuid('ruleId')
                    ->name('api.v1.penalty-rules.deactivate');
            });

            Route::get('exam-sessions/{sessionId}/sanctions', [PenaltySanctionController::class, 'index'])
                ->whereUuid('sessionId')
                ->name('api.v1.exam-sessions.sanctions.index');

            Route::post('sanctions/{sanctionId}/void', [PenaltySanctionController::class, 'void'])
                ->whereUuid('sanctionId')
                ->name('api.v1.sanctions.void');

            // -------------------------------------------------------------
            // Workflows — approval gating for result publication - 3
            // -------------------------------------------------------------
            Route::prefix('workflows')->group(function (): void {
                Route::post('/', [ApprovalWorkflowController::class, 'initiate'])
                    ->name('api.v1.workflows.initiate');

                Route::get('{workflowId}', [ApprovalWorkflowController::class, 'show'])
                    ->whereUuid('workflowId')
                    ->name('api.v1.workflows.show');

                Route::post('{workflowId}/approve', [ApprovalWorkflowController::class, 'approve'])
                    ->whereUuid('workflowId')
                    ->name('api.v1.workflows.approve');
            });

            // -------------------------------------------------------------
            // Analytics — dashboard metrics - 1
            // -------------------------------------------------------------
            Route::get('analytics/dashboard', [AnalyticsDashboardController::class, 'summary'])
                ->name('api.v1.analytics.dashboard');

            // -------------------------------------------------------------
            // Exam Enrollments — admin enrollment management - 3 
            // -------------------------------------------------------------
            Route::prefix('exams/{examId}/enrollments')->group(function (): void {
                Route::get('/', [EnrollmentController::class, 'index'])
                    ->whereUuid('examId')
                    ->name('api.v1.enrollments.index');

                Route::post('/', [EnrollmentController::class, 'store'])
                    ->whereUuid('examId')
                    ->name('api.v1.enrollments.store');

                Route::delete('{enrollmentId}', [EnrollmentController::class, 'destroy'])
                    ->whereUuid('examId')
                    ->whereUuid('enrollmentId')
                    ->name('api.v1.enrollments.destroy');
            });
        });
    });
