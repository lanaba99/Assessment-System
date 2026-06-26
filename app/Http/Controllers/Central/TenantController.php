<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Domains\Central\Services\TenantManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Http\Requests\Central\UpdateTenantRequest;
use App\Http\Resources\Central\TenantResource;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @group CentralTenant
 */

class TenantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantManagementService $tenants,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $items = $this->tenants->list();

        return new JsonResponse(
            ['data' => TenantResource::collection($items)->resolve()],
            Response::HTTP_OK,
        );
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenants->create($request->validated());

        return new JsonResponse(
            ['data' => new TenantResource($tenant)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->tenants->find($tenantId);

        if ($tenant === null) {
            return $this->notFound($tenantId);
        }

        $this->authorize('view', $tenant);

        return new JsonResponse(
            ['data' => new TenantResource($tenant)],
            Response::HTTP_OK,
        );
    }

    public function update(UpdateTenantRequest $request, string $tenantId): JsonResponse
    {
        $tenant = $this->tenants->find($tenantId);

        if ($tenant === null) {
            return $this->notFound($tenantId);
        }

        $updated = $this->tenants->update($tenant, $request->validated());

        return new JsonResponse(
            ['data' => new TenantResource($updated)],
            Response::HTTP_OK,
        );
    }

    private function notFound(string $tenantId): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => 'tenant_not_found', 'message' => "Tenant {$tenantId} not found."]],
            Response::HTTP_NOT_FOUND,
        );
    }
}
