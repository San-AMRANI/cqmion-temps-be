<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    public function __construct(private readonly TripService $tripService)
    {
    }

    public function getSummary(): array
    {
        $completedTrips = Trip::query()->where('status', Trip::STATUS_COMPLETED)->get();

        $durations = $completedTrips->map(function (Trip $trip): array {
            return [
                'company_to_port' => $trip->started_at && $trip->arrived_port_at
                    ? $trip->started_at->diffInSeconds($trip->arrived_port_at)
                    : null,
                'port_duration' => $trip->arrived_port_at && $trip->left_port_at
                    ? $trip->arrived_port_at->diffInSeconds($trip->left_port_at)
                    : null,
                'port_to_company' => $trip->left_port_at && $trip->completed_at
                    ? $trip->left_port_at->diffInSeconds($trip->completed_at)
                    : null,
                'total_duration' => $trip->started_at && $trip->completed_at
                    ? $trip->started_at->diffInSeconds($trip->completed_at)
                    : null,
            ];
        });

        $delayThresholdSeconds = 6 * 3600;
        $delayedTrips = $durations->filter(fn (array $item) => ($item['port_duration'] ?? 0) > $delayThresholdSeconds)->count();

        return [
            'total_trucks' => Truck::count(),
            'total_trips' => Trip::count(),
            'active_trips' => Trip::where('is_active', true)->count(),
            'completed_trips' => $completedTrips->count(),
            'delayed_trips' => $delayedTrips,
            'status_breakdown' => Trip::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'average_company_to_port_seconds' => (int) round((float) $durations->pluck('company_to_port')->filter()->avg()),
            'average_port_duration_seconds' => (int) round((float) $durations->pluck('port_duration')->filter()->avg()),
            'average_port_to_company_seconds' => (int) round((float) $durations->pluck('port_to_company')->filter()->avg()),
            'average_total_duration_seconds' => (int) round((float) $durations->pluck('total_duration')->filter()->avg()),
        ];
    }

    public function getTruckReport(int $truckId): array
    {
        $truck = Truck::findOrFail($truckId);

        $trips = $truck->trips()->latest('id')->get()->map(
            fn (Trip $trip) => $this->tripService->formatWithDurations($trip)
        );

        return [
            'truck' => [
                'id' => $truck->id,
                'registration_number' => $truck->registration_number,
                'driver_name' => $truck->driver_name,
                'qr_code' => $truck->qr_code,
                'is_active' => $truck->is_active,
            ],
            'trips' => $trips,
            'total_trips' => $trips->count(),
        ];
    }

    public function getFilteredTrips(array $filters): Collection
    {
        $query = Trip::query()->with('truck')->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', (int) $filters['truck_id']);
        }

        return $query->get();
    }

    public function getDurationMetrics(): array
    {
        return $this->getSummary();
    }

    public function getDelayMetrics(): array
    {
        $threshold = 6 * 3600;

        $delayed = Trip::query()
            ->whereNotNull('arrived_port_at')
            ->whereNotNull('left_port_at')
            ->get()
            ->filter(fn (Trip $trip) => $trip->arrived_port_at->diffInSeconds($trip->left_port_at) > $threshold)
            ->values();

        return [
            'threshold_seconds' => $threshold,
            'count' => $delayed->count(),
            'trips' => $delayed->map(fn (Trip $trip) => $this->tripService->formatWithDurations($trip)),
        ];
    }
}
