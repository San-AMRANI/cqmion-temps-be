<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'truck_id' => $this->truck_id,
            'trip_id' => $this->trip_id,
            'type' => $this->type,
            'description' => $this->description,
            'cost' => $this->cost,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'truck' => new TruckResource($this->whenLoaded('truck')),
        ];
    }
}
