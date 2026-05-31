<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class TripService
{
    public function createTrip(int $truckId, string $status = Trip::STATUS_STARTED): Trip
    {
        $now = now();

        $payload = [
            'truck_id' => $truckId,
            'status' => $status,
            'started_at' => $now,
            'is_active' => $status !== Trip::STATUS_COMPLETED,
        ];

        if ($status === Trip::STATUS_ARRIVED_PORT) {
            $payload['arrived_port_at'] = $now;
        }

        if ($status === Trip::STATUS_LEFT_PORT) {
            $payload['left_port_at'] = $now;
        }

        if ($status === Trip::STATUS_COMPLETED) {
            $payload['completed_at'] = $now;
            $payload['is_active'] = null;
        }

        return Trip::create($payload);
    }

    public function getActiveTrip(int $truckId): ?Trip
    {
        return Trip::query()
            ->where('truck_id', $truckId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public function updateStatus(Trip $trip, string $status): Trip
    {
        $updates = ['status' => $status];

        if ($status === Trip::STATUS_ARRIVED_PORT) {
            $updates['arrived_port_at'] = now();
        }

        if ($status === Trip::STATUS_LEFT_PORT) {
            $updates['left_port_at'] = now();
        }

        if ($status === Trip::STATUS_COMPLETED) {
            $updates['completed_at'] = now();
            $updates['is_active'] = null;
        }

        $trip->update($updates);

        return $trip->fresh();
    }

    public function completeTrip(Trip $trip): Trip
    {
        return $this->updateStatus($trip, Trip::STATUS_COMPLETED);
    }

    public function getTrips(array $filters = []): Collection
    {
        $query = Trip::query()->with('truck')->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', (int) $filters['truck_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->get();
    }

    public function formatWithDurations(Trip $trip): array
    {
        $trip->loadMissing('truck');

        return [
            'id' => $trip->id,
            'truck_id' => $trip->truck_id,
            'truck_registration_number' => $trip->truck?->registration_number,
            'truck_driver_name' => $trip->truck?->driver_name,
            'status' => $trip->status,
            'started_at' => $trip->started_at,
            'arrived_port_at' => $trip->arrived_port_at,
            'left_port_at' => $trip->left_port_at,
            'completed_at' => $trip->completed_at,
            'durations' => [
                'company_to_port' => $this->diffInSeconds($trip->started_at, $trip->arrived_port_at),
                'port_duration' => $this->diffInSeconds($trip->arrived_port_at, $trip->left_port_at),
                'port_to_company' => $this->diffInSeconds($trip->left_port_at, $trip->completed_at),
                'total_duration' => $this->diffInSeconds($trip->started_at, $trip->completed_at),
            ],
        ];
    }

    private function diffInSeconds(?Carbon $from, ?Carbon $to): ?int
    {
        if (! $from || ! $to) {
            return null;
        }

        return $from->diffInSeconds($to);
    }
}
