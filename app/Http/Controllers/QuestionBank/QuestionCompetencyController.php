<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuestionBank;

use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Models\QuestionCompetencyWeight;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionBank\LinkCompetencyRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class QuestionCompetencyController extends Controller
{
    use AuthorizesRequests;

    public function store(LinkCompetencyRequest $request, string $questionId): JsonResponse
    {
        $question = Question::query()->findOrFail($questionId);
        $this->authorize('update', $question);

        $weight = QuestionCompetencyWeight::query()->updateOrCreate(
            ['question_id' => $questionId, 'competency_id' => $request->validated('competency_id')],
            [
                'weight_percentage' => $request->validated('weight_percentage'),
                'is_primary_competency' => $request->boolean('is_primary_competency'),
            ],
        );

        return new JsonResponse(['data' => $weight], Response::HTTP_CREATED);
    }

    public function index(string $questionId): JsonResponse
    {
        $weights = QuestionCompetencyWeight::query()
            ->where('question_id', $questionId)
            ->with('competency')
            ->get();

        return new JsonResponse(['data' => $weights], Response::HTTP_OK);
    }
}