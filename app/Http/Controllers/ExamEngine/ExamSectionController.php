<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExamEngine;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Models\ExamSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamEngine\StoreExamSectionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExamSectionController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreExamSectionRequest $request, string $examId): JsonResponse
    {
        $exam = Exam::query()->findOrFail($examId);
        $this->authorize('update', $exam);

        $section = ExamSection::query()->create([
            'exam_id' => $examId,
            ...$request->validated(),
            'created_at' => now(),
        ]);

        return new JsonResponse(['data' => $section], Response::HTTP_CREATED);
    }

    public function index(string $examId): JsonResponse
    {
        $sections = ExamSection::query()->where('exam_id', $examId)->with('blueprints')->get();

        return new JsonResponse(['data' => $sections], Response::HTTP_OK);
    }
}