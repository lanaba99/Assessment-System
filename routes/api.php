<?php

declare(strict_types=1);

use App\Http\Controllers\AssessmentResultController;
use App\Http\Controllers\ExamSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
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
