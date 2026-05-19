<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\ExamSession\Services\ExamSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ExamSessionService $examSessionService,
    ) {
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['required', 'uuid'],
            'exam_id' => ['required', 'uuid'],
        ]);

        $session = $this->examSessionService->startSession(
            $validated['candidate_id'],
            $validated['exam_id'],
        );

        return response()->json([
            'session_id' => $session->session_id,
            'exam_id' => $session->exam_id,
            'candidate_user_id' => $session->candidate_user_id,
            'session_state' => $session->session_state,
            'session_started_at' => $session->session_started_at,
        ], Response::HTTP_CREATED);
    }
}
