<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuestionBank;

use App\Domains\QuestionBank\Contracts\CategoryTreeService;
use App\Domains\QuestionBank\Exceptions\CategoryNotEmptyException;
use App\Domains\QuestionBank\Models\QuestionBank;
use App\Domains\QuestionBank\Repositories\CategoryRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionBank\MoveCategoryRequest;
use App\Http\Requests\QuestionBank\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryTreeResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CategoryTreeService $categoryTree,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', QuestionBank::class);

        $tenantId = (string) tenant()->getKey();
        $tree = $this->categoryTree->getTree($tenantId);

        return new JsonResponse([
            'data' => CategoryTreeResource::collection(collect($tree))->resolve(),
        ], Response::HTTP_OK);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', QuestionBank::class);

        $tenantId = (string) tenant()->getKey();

        $category = $this->categoryTree->createCategory(
            tenantId: $tenantId,
            title: $request->title(),
            parentId: $request->parentId(),
            description: $request->description(),
        );

        return new JsonResponse([
            'data' => new CategoryResource($category),
        ], Response::HTTP_CREATED);
    }

    public function move(MoveCategoryRequest $request, string $id): JsonResponse
    {
        $category = $request->category();
        $this->authorize('update', $category);

        $tenantId = (string) tenant()->getKey();

        try {
            $moved = $this->categoryTree->moveCategory(
                tenantId: $tenantId,
                categoryId: $id,
                parentId: $request->parentId(),
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse('category_move_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'data' => new CategoryResource($moved),
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();
        $category = $this->categories->findById($tenantId, $id);

        if ($category === null) {
            return $this->errorResponse('category_not_found', 'Category not found.', Response::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $category);

        try {
            $this->categoryTree->deleteCategory($tenantId, $id);
        } catch (CategoryNotEmptyException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return $this->errorResponse('category_delete_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
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
