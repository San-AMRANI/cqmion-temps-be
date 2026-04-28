<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    use HasFactory;

    public const ACTION_START = 'START';
    public const ACTION_ARRIVE = 'ARRIVE';
    public const ACTION_LEAVE = 'LEAVE';
    public const ACTION_RETURN = 'RETURN';

    public $timestamps = false;

    protected $fillable = [
        'truck_id',
        'trip_id',
        'user_id',
        'location',
        'action',
        'device_id',
        'scanned_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
