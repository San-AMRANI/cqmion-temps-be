<?php

namespace App\Http\Resources;

use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminScanLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => $this->actionLabel($log->action),
            'location' => $log->location,
            'device_id' => $log->device_id,
            'scanned_at' => $log->scanned_at,
            'created_at' => $log->created_at,
            'operator' => [
                'id' => $log->user?->id ?? $log->user_id,
                'name' => $log->user?->name,
                'email' => $log->user?->email,
                'role' => $log->user?->role,
                'location' => $log->user?->location,
            ],
            'truck' => [
                'id' => $log->truck?->id ?? $log->truck_id,
                'registration_number' => $log->truck?->registration_number,
                'driver_name' => $log->truck?->driver_name,
                'qr_code' => $log->truck?->qr_code,
            ],
            'trip' => [
                'id' => $log->trip?->id ?? $log->trip_id,
                'status' => $log->trip?->status,
                'is_active' => $log->trip?->is_active,
            ],
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            ScanLog::ACTION_START => 'STARTED',
            ScanLog::ACTION_ARRIVE => 'ARRIVED_PORT',
            ScanLog::ACTION_LEAVE => 'LEFT_PORT',
            ScanLog::ACTION_RETURN => 'COMPLETED',
            default => $action,
        };
    }
}
