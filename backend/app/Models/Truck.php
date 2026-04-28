<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Truck extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_number',
        'driver_name',
        'qr_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function activeTrip(): HasOne
    {
        return $this->hasOne(Trip::class)->where('status', '!=', Trip::STATUS_COMPLETED);
    }
}
