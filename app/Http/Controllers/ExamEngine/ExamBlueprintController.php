<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExamEngine;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamEngine\Models\ExamBlueprint;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExamEngine\StoreExamBlueprintRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ExamBlueprintController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreExamBlueprintRequest $request, string $examId): JsonResponse
    {
        $exam = Exam::query()->findOrFail($examId);
        $this->authorize('update', $exam);

        $existingWeight = ExamBlueprint::query()
            ->where('exam_id', $examId)
            ->where('section_id', $request->validated('section_id'))
            ->sum('min_weight_percentage');

        $newTotal = $existingWeight + (float) $request->validated('min_weight_percentage');

        if ($newTotal > 100) {
            throw new RuntimeException("Blueprint weight total would exceed 100; got {$newTotal}.");
        }

        $blueprint = ExamBlueprint::query()->create([
            'exam_id' => $examId,
            ...$request->validated(),
        ]);

        return new JsonResponse(['data' => $blueprint], Response::HTTP_CREATED);
    }

    public function index(string $examId): JsonResponse
    {
        $blueprints = ExamBlueprint::query()->where('exam_id', $examId)->with('competency')->get();

        return new JsonResponse(['data' => $blueprints], Response::HTTP_OK);
    }
}