<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => $this->reportService->generateGeneralReport($request->query())
        ]);
    }

    public function truck(Truck $truck, Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => $this->reportService->generateTruckReport($truck->id, $request->query())
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filePath = $this->reportService->exportReportToExcel($request->query());
        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
