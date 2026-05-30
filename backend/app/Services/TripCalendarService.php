<?php

namespace App\Services;

use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TripCalendarService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarSummary(array $filters, CarbonImmutable $from, CarbonImmutable $to, string $dayStart): array
    {
        $days = [];
        $cursor = $from->startOfDay();
        $end = $to->startOfDay();

        while ($cursor->lte($end)) {
            $window = $this->buildDayWindow($cursor, $dayStart);
            $byStatus = $this->summaryByStatus($filters, $window['start_utc'], $window['end_utc']);

            $total = (int) $byStatus->sum();
            $completed = (int) ($byStatus[Trip::STATUS_COMPLETED] ?? 0);

            $days[] = [
                'day' => $cursor->format('Y-m-d'),
                'start_at' => $window['start_local']->toIso8601String(),
                'end_at' => $window['end_local']->toIso8601String(),
                'total' => $total,
                'active' => $total - $completed,
                'completed' => $completed,
                'by_status' => [
                    Trip::STATUS_STARTED => (int) ($byStatus[Trip::STATUS_STARTED] ?? 0),
                    Trip::STATUS_ARRIVED_PORT => (int) ($byStatus[Trip::STATUS_ARRIVED_PORT] ?? 0),
                    Trip::STATUS_LEFT_PORT => (int) ($byStatus[Trip::STATUS_LEFT_PORT] ?? 0),
                    Trip::STATUS_COMPLETED => $completed,
                ],
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator:LengthAwarePaginator,summary:array<string,int>,window:array<string,CarbonImmutable>}
     */
    public function getTripsByDay(array $filters, CarbonImmutable $day, string $dayStart, int $limit): array
    {
        $window = $this->buildDayWindow($day, $dayStart);

        $query = $this->buildBaseQuery($filters)
            ->with(['truck', 'latestScan'])
            ->whereBetween('created_at', [$window['start_utc'], $window['end_utc']])
            ->latest('id');

        $paginator = $query->paginate($limit)->withQueryString();

        $summaryCounts = $this->summaryByStatus($filters, $window['start_utc'], $window['end_utc']);
        $total = (int) $summaryCounts->sum();
        $completed = (int) ($summaryCounts[Trip::STATUS_COMPLETED] ?? 0);

        return [
            'paginator' => $paginator,
            'summary' => [
                'total' => $total,
                'active' => $total - $completed,
                'completed' => $completed,
            ],
            'window' => $window,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getTripsForDay(array $filters, CarbonImmutable $day, string $dayStart): Collection
    {
        $window = $this->buildDayWindow($day, $dayStart);

        return $this->buildBaseQuery($filters)
            ->with(['truck', 'latestScan'])
            ->whereBetween('created_at', [$window['start_utc'], $window['end_utc']])
            ->latest('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildBaseQuery(array $filters): Builder
    {
        $query = Trip::query();

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', (int) $filters['truck_id']);
        }

        $registrationNumber = (string) ($filters['registration_number'] ?? '');
        $driverName = (string) ($filters['driver_name'] ?? '');

        if ($registrationNumber !== '' || $driverName !== '') {
            $query->whereHas('truck', function (Builder $truckQuery) use ($registrationNumber, $driverName): void {
                if ($registrationNumber !== '') {
                    $truckQuery->where('registration_number', 'like', '%'.$registrationNumber.'%');
                }

                if ($driverName !== '') {
                    $truckQuery->where('driver_name', 'like', '%'.$driverName.'%');
                }
            });
        }

        return $query;
    }

    /**
     * @return array<string, CarbonImmutable>
     */
    private function buildDayWindow(CarbonImmutable $day, string $dayStart): array
    {
        $startLocal = $day->setTimeFromTimeString($dayStart);
        $endLocal = $startLocal->addDay()->subSecond();

        return [
            'start_local' => $startLocal,
            'end_local' => $endLocal,
            'start_utc' => $startLocal->setTimezone('UTC'),
            'end_utc' => $endLocal->setTimezone('UTC'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function summaryByStatus(array $filters, CarbonImmutable $startUtc, CarbonImmutable $endUtc)
    {
        return $this->buildBaseQuery($filters)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }
}
