<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuestionBank;

use App\Domains\QuestionBank\Contracts\QuestionManagementService;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\QuestionBank\Repositories\QuestionRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionBank\ListQuestionsRequest;
use App\Http\Requests\QuestionBank\StoreQuestionRequest;
use App\Http\Requests\QuestionBank\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class QuestionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuestionManagementService $questions,
        private readonly QuestionRepository $questionRepository,
    ) {
    }

    public function store(StoreQuestionRequest $request): JsonResponse
    {
        $this->authorize('create', Question::class);

        $actor = $request->user();

        try {
            $question = $this->questions->createQuestion(
                categoryId: $request->categoryId(),
                createdByUserId: (string) $actor->id,
                title: $request->title(),
                type: $request->type(),
                questionText: $request->questionText(),
                stem: $request->stem(),
                bloomLevel: $request->bloomLevel(),
                difficultyLevel: $request->difficultyLevel(),
                choices: $request->choices(),
                answer: $request->answer(),
                evaluatorInstructions: $request->evaluatorInstructions(),
                psychometrics: $request->psychometrics(),
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse('question_create_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'data' => new QuestionResource($question),
        ], Response::HTTP_CREATED);
    }

    public function index(ListQuestionsRequest $request): JsonResponse
    {
        $paginator = $this->questions->listQuestions(
            filters: $request->filters(),
            perPage: $request->perPage(),
        );

        return new JsonResponse([
            'data' => QuestionResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(string $id): JsonResponse
    {
        $question = $this->questionRepository->findByIdWithDetails($id);

        if ($question === null) {
            return $this->errorResponse('question_not_found', 'Question not found.', Response::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $question);

        return new JsonResponse([
            'data' => new QuestionResource($question),
        ], Response::HTTP_OK);
    }

    public function update(UpdateQuestionRequest $request, string $id): JsonResponse
    {
        $question = $request->question();
        $this->authorize('update', $question);

        try {
            $updated = $this->questions->updateQuestion(
                questionId: $id,
                editedByUserId: (string) $request->user()->id,
                questionAttributes: $request->questionAttributes(),
                versionAttributes: $request->versionAttributes(),
                choices: $request->choices(),
                answer: $request->answer(),
                evaluatorInstructions: $request->evaluatorInstructions(),
                psychometrics: $request->psychometrics(),
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse('question_update_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'data' => new QuestionResource($updated),
        ], Response::HTTP_OK);
    }

    public function destroy(string $id): JsonResponse
    {
        $question = $this->questionRepository->findById($id);

        if ($question === null) {
            return $this->errorResponse('question_not_found', 'Question not found.', Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $question);

        try {
            $this->questions->deleteQuestion($id);
        } catch (RuntimeException $e) {
            return $this->errorResponse('question_delete_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
