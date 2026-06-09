<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly ExcelExportService $excelExportService
    ) {
    }

    public function generateGeneralReport(array $filters = []): array
    {
        $query = Trip::query()->with('truck');
        
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $trips = $query->get();

        $durations = $trips->filter(fn($trip) => $trip->status === Trip::STATUS_COMPLETED)
                           ->map(fn($trip) => $this->tripService->formatWithDurations($trip)['durations']);

        return [
            'total_trips' => $trips->count(),
            'active_trips' => $trips->where('is_active', true)->count(),
            'completed_trips' => $trips->where('status', Trip::STATUS_COMPLETED)->count(),
            'cancelled_trips' => $trips->where('status', Trip::STATUS_CANCELLED)->count(),
            'average_total_duration' => (int) round((float) $durations->pluck('total_duration')->filter()->avg()),
            'trips' => $trips->map(fn($t) => $this->tripService->formatWithDurations($t))
        ];
    }

    public function generateTruckReport(int $truckId, array $filters = []): array
    {
        $truck = Truck::findOrFail($truckId);
        $filters['truck_id'] = $truckId;
        
        $trips = $this->tripService->getTrips($filters);
        
        return [
            'truck' => $truck,
            'total_trips' => $trips->count(),
            'completed_trips' => $trips->where('status', Trip::STATUS_COMPLETED)->count(),
            'cancelled_trips' => $trips->where('status', Trip::STATUS_CANCELLED)->count(),
            'trips' => $trips->map(fn($t) => $this->tripService->formatWithDurations($t))
        ];
    }
    
    public function exportReportToExcel(array $filters = []): string
    {
        $trips = $this->tripService->getTrips($filters);
        return $this->excelExportService->exportTrips($trips);
    }
}

