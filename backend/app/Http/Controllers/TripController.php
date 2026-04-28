<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorTripResource;
use App\Http\Resources\ScanLogResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\ScanLogService;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly ScanLogService $scanLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Trip::query()->with(['truck', 'latestScan'])->latest('id');
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('truck_id')) {
            $query->where('truck_id', $request->integer('truck_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        return $this->successResponse(TripResource::collection($query->paginate($limit)));
    }

    public function show(Trip $trip): JsonResponse
    {
        return $this->successResponse(new TripResource($trip->load(['truck', 'latestScan'])));
    }

    public function active(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        $trips = Trip::query()
            ->with(['truck', 'latestScan'])
            ->where('is_active', true)
            ->latest('id')
            ->limit($limit)
            ->get();

        return $this->successResponse(OperatorTripResource::collection($trips));
    }

    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        $trips = Trip::query()
            ->with(['truck', 'latestScan'])
            ->where('status', Trip::STATUS_COMPLETED)
            ->latest('id')
            ->paginate($limit);

        return $this->successResponse(TripResource::collection($trips));
    }

    public function logs(Trip $trip): JsonResponse
    {
        return $this->successResponse(ScanLogResource::collection($this->scanLogService->getLogsByTrip($trip->id)));
    }

    public function operatorLastScans(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 10)));

        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->successResponse(ScanLogResource::collection($this->scanLogService->getLastScansByUser($user->id, $limit)));
    }
}
