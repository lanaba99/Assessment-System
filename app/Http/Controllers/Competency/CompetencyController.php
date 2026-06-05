<?php

declare(strict_types=1);

namespace App\Http\Controllers\Competency;

use App\Domains\Competency\Contracts\CompetencyTreeService;
use App\Domains\Competency\Exceptions\CompetencyNotEmptyException;
use App\Domains\Competency\Models\Competency;
use App\Domains\Competency\Repositories\CompetencyRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Competency\MoveCompetencyRequest;
use App\Http\Requests\Competency\StoreCompetencyRequest;
use App\Http\Resources\CompetencyResource;
use App\Http\Resources\CompetencyTreeResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CompetencyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CompetencyTreeService $competencyTree,
        private readonly CompetencyRepository $competencies,
    ) {
    }

    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Competency::class);

        $tenantId = (string) tenant()->getKey();
        $tree = $this->competencyTree->getTree($tenantId);

        return new JsonResponse([
            'data' => CompetencyTreeResource::collection(collect($tree))->resolve(),
        ], Response::HTTP_OK);
    }

    public function store(StoreCompetencyRequest $request): JsonResponse
    {
        $this->authorize('create', Competency::class);

        $tenantId = (string) tenant()->getKey();

        $competency = $this->competencyTree->createCompetency(
            tenantId: $tenantId,
            createdByUserId: (string) $request->user()->id,
            name: $request->name(),
            parentId: $request->parentId(),
            description: $request->description(),
        );

        return new JsonResponse([
            'data' => new CompetencyResource($competency),
        ], Response::HTTP_CREATED);
    }

    public function move(MoveCompetencyRequest $request, string $id): JsonResponse
    {
        $competency = $request->competency();
        $this->authorize('update', $competency);

        $tenantId = (string) tenant()->getKey();

        try {
            $moved = $this->competencyTree->moveCompetency(
                tenantId: $tenantId,
                competencyId: $id,
                parentId: $request->parentId(),
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse('competency_move_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'data' => new CompetencyResource($moved),
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $competency = $this->competencies->findById($tenantId, $id);

        if ($competency === null) {
            return $this->errorResponse('competency_not_found', 'Competency not found.', Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $competency);

        try {
            $this->competencyTree->deleteCompetency($tenantId, $id);
        } catch (CompetencyNotEmptyException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return $this->errorResponse('competency_delete_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
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
