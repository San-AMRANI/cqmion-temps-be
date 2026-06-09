<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function getOverviewStats(): array
    {
        return [
            'total_trucks' => Truck::count(),
            'active_trucks' => Truck::where('is_active', true)->count(),
            'total_trips' => Trip::count(),
            'active_trips' => Trip::where('is_active', true)->count(),
            'total_users' => User::count(),
            'trips_today' => Trip::whereDate('created_at', Carbon::today())->count(),
        ];
    }
}
