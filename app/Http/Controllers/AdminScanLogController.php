<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminScanLogResource;
use App\Models\ScanLog;
use App\Models\User;
use App\Services\ScanLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScanLogController extends Controller
{
    public function __construct(private readonly ScanLogService $scanLogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'truck_id' => ['nullable', 'integer', 'exists:trucks,id'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'role' => ['nullable', 'in:'.implode(',', [
                User::ROLE_ADMIN,
                User::ROLE_COMPANY_OPERATOR,
                User::ROLE_PORT_OPERATOR,
            ])],
            'location' => ['nullable', 'in:'.implode(',', [
                User::LOCATION_COMPANY,
                User::LOCATION_PORT,
            ])],
            'action' => ['nullable', 'in:'.implode(',', [
                ScanLog::ACTION_START,
                ScanLog::ACTION_ARRIVE,
                ScanLog::ACTION_LEAVE,
                ScanLog::ACTION_RETURN,
            ])],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $limit = max(1, min(100, (int) ($validated['limit'] ?? 20)));
        $logs = $this->scanLogService->getAdminLogs($validated, $limit);

        $items = collect($logs->items())
            ->map(fn ($log) => (new AdminScanLogResource($log))->toArray($request))
            ->values();

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'summary' => $this->scanLogService->getAdminLogsSummary($validated),
            'applied_filters' => [
                'user_id' => $validated['user_id'] ?? null,
                'truck_id' => $validated['truck_id'] ?? null,
                'trip_id' => $validated['trip_id'] ?? null,
                'role' => $validated['role'] ?? null,
                'location' => $validated['location'] ?? null,
                'action' => $validated['action'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'search' => $validated['search'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
        ]);
    }
}
