<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Services\MaintenanceService;
use App\Http\Resources\MaintenanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenanceService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => MaintenanceResource::collection(MaintenanceRecord::with(['truck', 'trip'])->get())
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'trip_id' => 'nullable|exists:trips,id',
            'type' => 'required|string',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'date' => 'nullable|date',
        ]);

        $record = $this->maintenanceService->createRecord($data);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => new MaintenanceResource($record)
        ], 201);
    }

    public function update(Request $request, MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $data = $request->validate([
            'type' => 'sometimes|string',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'date' => 'nullable|date',
        ]);

        $record = $this->maintenanceService->updateRecord($maintenanceRecord, $data);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => new MaintenanceResource($record)
        ]);
    }

    public function destroy(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $this->maintenanceService->deleteRecord($maintenanceRecord);
        return response()->json(null, 204);
    }
}
