<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Services\TruckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckController extends Controller
{
    public function __construct(private readonly TruckService $truckService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        $query = Truck::query()->latest('id');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->successResponse($query->paginate($limit));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_number' => ['required', 'string', 'max:255', 'unique:trucks,registration_number'],
            'driver_name' => ['required', 'string', 'max:255'],
            'qr_code' => ['nullable', 'string', 'max:255', 'unique:trucks,qr_code'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $truck = $this->truckService->createTruck($validated);

        return $this->successResponse($truck, 'Truck created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Truck $truck): JsonResponse
    {
        return $this->successResponse($truck);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'registration_number' => ['sometimes', 'string', 'max:255', 'unique:trucks,registration_number,'.$truck->id],
            'driver_name' => ['sometimes', 'string', 'max:255'],
            'qr_code' => ['sometimes', 'string', 'max:255', 'unique:trucks,qr_code,'.$truck->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $truck = $this->truckService->updateTruck($truck, $validated);

        return $this->successResponse($truck, 'Truck updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Truck $truck): JsonResponse
    {
        $this->truckService->deleteTruck($truck);

        return $this->successResponse(null, 'Truck deleted successfully');
    }

    public function basic(Truck $truck): JsonResponse
    {
        $truck->load('activeTrip');

        return $this->successResponse([
            'id' => $truck->id,
            'registration_number' => $truck->registration_number,
            'driver_name' => $truck->driver_name,
            'qr_code' => $truck->qr_code,
            'is_active' => $truck->is_active,
            'active_trip_status' => $truck->activeTrip?->status,
        ]);
    }

    public function generateQr(Truck $truck): JsonResponse
    {
        $qrCode = $this->truckService->generateQrCode($truck);

        return $this->successResponse([
            'id' => $truck->id,
            'qr_code' => $qrCode,
        ], 'QR code generated successfully');
    }

    public function activate(Truck $truck): JsonResponse
    {
        return $this->successResponse(
            $this->truckService->activate($truck),
            'Truck activated successfully'
        );
    }

    public function deactivate(Truck $truck): JsonResponse
    {
        return $this->successResponse(
            $this->truckService->deactivate($truck),
            'Truck deactivated successfully'
        );
    }
}
