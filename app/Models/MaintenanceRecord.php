<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'truck_id',
        'trip_id',
        'type',
        'description',
        'cost',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
