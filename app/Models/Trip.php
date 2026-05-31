<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    use HasFactory;

    public const STATUS_STARTED = 'STARTED';
    public const STATUS_ARRIVED_PORT = 'ARRIVED_PORT';
    public const STATUS_LEFT_PORT = 'LEFT_PORT';
    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'truck_id',
        'status',
        'is_active',
        'started_at',
        'arrived_port_at',
        'left_port_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'arrived_port_at' => 'datetime',
            'left_port_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function latestScan(): HasOne
    {
        return $this->hasOne(ScanLog::class)->latestOfMany('scanned_at');
    }
}
