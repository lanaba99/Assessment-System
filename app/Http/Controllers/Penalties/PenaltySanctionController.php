<?php

declare(strict_types=1);

namespace App\Http\Controllers\Penalties;

use App\Domains\Penalties\Models\PenaltySanction;
use App\Domains\Penalties\Services\PenaltySanctionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Penalties\VoidSanctionRequest;
use App\Http\Resources\Penalties\PenaltySanctionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group PenaltySanctions
 */

class PenaltySanctionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PenaltySanctionService $service,
    ) {
    }

    public function index(Request $request, string $sessionId): JsonResponse
    {
        $this->authorize('viewAny', PenaltySanction::class);

        $tenantId = (string) tenant()->getKey();
        $sanctions = $this->service->listForSession($tenantId, $sessionId);

        return new JsonResponse(
            ['data' => PenaltySanctionResource::collection($sanctions)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function void(VoidSanctionRequest $request, string $sanctionId): JsonResponse
    {
        $tenantId = (string) tenant()->getKey();

        $sanction = $this->service->voidSanction(
            $tenantId,
            $sanctionId,
            (string) $request->user()->id,
            (string) $request->validated('reason'),
        );

        return new JsonResponse(
            ['data' => new PenaltySanctionResource($sanction)],
            Response::HTTP_OK,
        );
    }
}
