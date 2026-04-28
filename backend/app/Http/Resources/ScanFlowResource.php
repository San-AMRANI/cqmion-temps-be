<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanFlowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $flow = $this->resource;

        return [
            'id' => $flow->id,
            'steps' => $flow->steps ?? [],
            'is_active' => (bool) $flow->is_active,
            'updated_at' => $flow->updated_at,
            'created_at' => $flow->created_at,
        ];
    }
}
