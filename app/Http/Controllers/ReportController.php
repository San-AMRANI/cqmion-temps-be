<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function summary(): JsonResponse
    {
        return $this->successResponse($this->reportService->getSummary());
    }

    public function truck(Truck $truck): JsonResponse
    {
        return $this->successResponse($this->reportService->getTruckReport($truck->id));
    }

    public function durations(): JsonResponse
    {
        return $this->successResponse($this->reportService->getDurationMetrics());
    }

    public function delays(): JsonResponse
    {
        return $this->successResponse($this->reportService->getDelayMetrics());
    }

    public function export(): JsonResponse
    {
        return $this->successResponse([
            'generated_at' => now(),
            'summary' => $this->reportService->getSummary(),
            'durations' => $this->reportService->getDurationMetrics(),
            'delays' => $this->reportService->getDelayMetrics(),
        ]);
    }
}
