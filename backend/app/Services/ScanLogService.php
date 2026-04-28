<?php

namespace App\Services;

use App\Models\ScanLog;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ScanLogService
{
    public function logScan(Trip $trip, User $user, string $action, string $location, ?string $deviceId = null): void
    {
        ScanLog::create([
            'truck_id' => $trip->truck_id,
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'location' => $location,
            'action' => $action,
            'device_id' => $deviceId,
            'scanned_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function getLogsByTrip(int $tripId): Collection
    {
        return ScanLog::query()
            ->with('user:id,name,email,role,location', 'truck:id,registration_number,driver_name')
            ->where('trip_id', $tripId)
            ->orderBy('scanned_at')
            ->get();
    }

    public function getLastScansByUser(int $userId, int $limit = 10): Collection
    {
        return ScanLog::query()
            ->with('truck:id,registration_number,driver_name', 'trip:id,status')
            ->where('user_id', $userId)
            ->latest('scanned_at')
            ->limit($limit)
            ->get();
    }

    public function getAdminLogs(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return $this->buildAdminLogsQuery($filters)
            ->paginate($limit)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminLogsSummary(array $filters): array
    {
        $query = $this->buildAdminLogsQuery($filters);

        return [
            'total_logs' => (clone $query)->count(),
            'unique_operators' => (clone $query)->distinct('user_id')->count('user_id'),
            'by_action' => (clone $query)
                ->selectRaw('action, COUNT(*) as total')
                ->groupBy('action')
                ->pluck('total', 'action'),
            'by_location' => (clone $query)
                ->selectRaw('location, COUNT(*) as total')
                ->groupBy('location')
                ->pluck('total', 'location'),
        ];
    }

    private function buildAdminLogsQuery(array $filters): Builder
    {
        $query = ScanLog::query()
            ->with(
                'user:id,name,email,role,location',
                'truck:id,registration_number,driver_name,qr_code',
                'trip:id,status,is_active'
            )
            ->latest('scanned_at');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', (int) $filters['truck_id']);
        }

        if (! empty($filters['trip_id'])) {
            $query->where('trip_id', (int) $filters['trip_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', (string) $filters['action']);
        }

        if (! empty($filters['location'])) {
            $query->where('location', (string) $filters['location']);
        }

        if (! empty($filters['role'])) {
            $role = (string) $filters['role'];

            $query->whereHas('user', function (Builder $userQuery) use ($role): void {
                $userQuery->where('role', $role);
            });
        }

        if (! empty($filters['registration_number'])) {
            $registrationNumber = (string) $filters['registration_number'];

            $query->whereHas('truck', function (Builder $truckQuery) use ($registrationNumber): void {
                $truckQuery->where('registration_number', 'like', '%'.$registrationNumber.'%');
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('scanned_at', '>=', (string) $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('scanned_at', '<=', (string) $filters['to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];

            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('device_id', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('truck', function (Builder $truckQuery) use ($search): void {
                        $truckQuery
                            ->where('registration_number', 'like', '%'.$search.'%')
                            ->orWhere('driver_name', 'like', '%'.$search.'%')
                            ->orWhere('qr_code', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query;
    }
}
