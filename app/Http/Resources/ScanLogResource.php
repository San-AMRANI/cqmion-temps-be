<?php

namespace App\Http\Resources;

use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->id,
            'action' => $this->actionLabel($log->action),
            'scanned_at' => $log->scanned_at,
            'truck' => [
                'id' => $log->truck?->id,
                'registration_number' => $log->truck?->registration_number,
                'driver_name' => $log->truck?->driver_name,
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
