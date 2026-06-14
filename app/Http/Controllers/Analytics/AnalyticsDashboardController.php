<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Domains\Analytics\Models\AnalyticsCache;
use App\Domains\Analytics\Services\AnalyticsIngestionService;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsDashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AnalyticsIngestionService $analytics,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', AnalyticsCache::class);

        $tenantId = (string) tenant()->getKey();
        $summary = $this->analytics->getDashboardSummary($tenantId);

        return new JsonResponse(['data' => $summary], Response::HTTP_OK);
    }
}
