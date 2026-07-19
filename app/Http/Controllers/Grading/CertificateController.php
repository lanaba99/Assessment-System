<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grading;

use App\Domains\Grading\Models\Certificate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    public function download(Request $request, string $sessionId)
    {
        $certificate = Certificate::query()->where('session_id', $sessionId)->firstOrFail();

        abort_unless(
            (string) $request->user()->id === (string) $certificate->candidate_user_id,
            Response::HTTP_FORBIDDEN,
        );

        return Storage::disk('local')->download($certificate->pdf_path, "{$certificate->certificate_number}.pdf");
    }

    public function verify(string $token): JsonResponse
    {
        $certificate = Certificate::query()->where('verification_token', $token)->first();

        if ($certificate === null) {
            return new JsonResponse(['valid' => false], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'valid' => true,
            'certificate_number' => $certificate->certificate_number,
            'issued_at' => $certificate->issued_at,
        ]);
    }
}