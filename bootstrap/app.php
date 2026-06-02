<?php

use App\Domains\ExamEngine\Exceptions\InvalidExamStateException;
use App\Domains\Identity\Exceptions\InvalidInviteTokenException;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\QuestionBank\Exceptions\CategoryNotEmptyException;
use App\Http\Middleware\ThrottleLoginMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle.login' => ThrottleLoginMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidExamStateException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'invalid_exam_state',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (InvalidInviteTokenException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'invalid_invite_token',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (PasswordPolicyViolationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'password_policy_violation',
                    'message' => $e->getMessage(),
                    'violations' => $e->violations,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (CategoryNotEmptyException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'category_not_empty',
                    'message' => $e->getMessage(),
                    'has_children' => $e->hasChildren,
                    'has_questions' => $e->hasQuestions,
                ],
            ], Response::HTTP_CONFLICT);
        });
    })->create();
