<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grading;

use App\Domains\Grading\Models\Certificate;
use App\Domains\Grading\Services\CertificateGenerationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\RevokeCertificateRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CertificateGenerationService $certificates,
    ) {
    }

public function index(Request $request): JsonResponse
    {
        $this->authorize('listAny', Certificate::class);

        $actor = $request->user();
        $tenantId = (string) $actor->tenant_id;

        $canViewAll = app(\App\Domains\Identity\Contracts\AuthorizationService::class)
            ->userHasPermission($tenantId, (string) $actor->id, 'grading.view');

        $query = Certificate::query()->where('tenant_id', $tenantId);

        if (! $canViewAll) {
            $query->where('candidate_user_id', $actor->id);
        }

        $certificates = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 15));

        return new JsonResponse([
            'data' => $certificates->items(),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(string $certificateId): JsonResponse
    {
        $certificate = Certificate::query()->findOrFail($certificateId);
        $this->authorize('view', $certificate);

        return new JsonResponse(['data' => $certificate], Response::HTTP_OK);
    }

    public function download(Request $request, string $sessionId)
    {
        $certificate = Certificate::query()
            ->whereHas('result', fn ($q) => $q->where('session_id', $sessionId))
            ->firstOrFail();

        $this->authorize('view', $certificate);

        return Storage::disk('local')->download(
            "certificates/{$certificate->certificate_code}.pdf",
            "{$certificate->certificate_code}.pdf",
        );
    }

    public function regenerate(string $certificateId): JsonResponse
    {
        $certificate = Certificate::query()->findOrFail($certificateId);
        $this->authorize('manage', $certificate);

        $result = $certificate->result()->firstOrFail();
        $grade = \App\Domains\Grading\Models\Grade::query()
            ->where('session_id', $result->session_id)
            ->where('is_final_grade', true)
            ->firstOrFail();

        $certificate->delete();
        $fresh = $this->certificates->generate($result, $grade);

        return new JsonResponse(['data' => $fresh], Response::HTTP_CREATED);
    }

    public function revoke(RevokeCertificateRequest $request, string $certificateId): JsonResponse
    {
        $certificate = Certificate::query()->findOrFail($certificateId);
        $this->authorize('manage', $certificate);

        $certificate->forceFill([
            'verification_status' => 'revoked',
            'certificate_metadata' => array_merge($certificate->certificate_metadata ?? [], [
                'revoked_reason' => $request->validated('reason'),
                'revoked_at' => now()->toIso8601String(),
                'revoked_by_user_id' => (string) $request->user()->id,
            ]),
        ])->save();

        return new JsonResponse(['data' => $certificate->fresh()], Response::HTTP_OK);
    }

    public function verify(string $token): JsonResponse
    {
        $certificate = Certificate::query()->where('certificate_code', $token)->first();

        if ($certificate === null) {
            return new JsonResponse(['valid' => false], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'valid' => $certificate->verification_status === 'valid',
            'certificate_code' => $certificate->certificate_code,
            'issued_at' => $certificate->issued_at,
        ]);
    }
}