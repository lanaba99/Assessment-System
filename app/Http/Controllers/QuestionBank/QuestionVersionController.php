<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuestionBank;

use App\Domains\QuestionBank\Models\QuestionPsychometrics;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionBank\UpdatePsychometricsRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuestionVersionController extends Controller
{
    use AuthorizesRequests;

    public function approve(Request $request, string $versionId): JsonResponse
    {
        $version = QuestionVersion::query()->findOrFail($versionId);
        $this->authorize('update', $version->question);

        $version->forceFill([
            'approval_status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        return new JsonResponse(['data' => $version->fresh()], Response::HTTP_OK);
    }

    public function updatePsychometrics(UpdatePsychometricsRequest $request, string $versionId): JsonResponse
    {
        $version = QuestionVersion::query()->findOrFail($versionId);
        $this->authorize('update', $version->question);

        $psychometrics = QuestionPsychometrics::query()->updateOrCreate(
            ['question_version_id' => $versionId],
            [
                ...$request->validated(),
                'is_calibrated' => true,
                'calibration_status' => 'calibrated',
                'last_calibrated_at' => now(),
            ],
        );

        return new JsonResponse(['data' => $psychometrics], Response::HTTP_OK);
    }
}