<?php

namespace App\Http\Resources;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trip = $this->resource;

        return [
            'id' => $trip->id,
            'status' => $trip->status,
            'next_expected_step' => $this->nextExpectedStep($trip->status),
            'current_location' => $this->currentLocation($trip->status),
            'last_scan_at' => $trip->latestScan?->scanned_at,
            'is_active' => $trip->is_active,
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
            'truck' => [
                'id' => $trip->truck?->id ?? $trip->truck_id,
                'registration_number' => $trip->truck?->registration_number ?? '',
                'driver_name' => $trip->truck?->driver_name ?? '',
            ],
        ];
    }

    private function nextExpectedStep(string $status): ?string
    {
        return match ($status) {
            Trip::STATUS_STARTED => Trip::STATUS_ARRIVED_PORT,
            Trip::STATUS_ARRIVED_PORT => Trip::STATUS_LEFT_PORT,
            Trip::STATUS_LEFT_PORT => Trip::STATUS_COMPLETED,
            default => null,
        };
    }

    private function currentLocation(string $status): ?string
    {
        return match ($status) {
            Trip::STATUS_STARTED => 'ON_ROUTE_TO_PORT',
            Trip::STATUS_ARRIVED_PORT => 'AT_PORT',
            Trip::STATUS_LEFT_PORT => 'RETURNING',
            Trip::STATUS_COMPLETED => 'AT_COMPANY',
            default => null,
        };
    }

    private function diffInSeconds(mixed $from, mixed $to): ?int
    {
        if (! $from || ! $to) {
            return null;
        }

        return $from->diffInSeconds($to);
    }
}
