<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorTripResource;
use App\Http\Resources\ScanLogResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\ScanLogService;
use App\Services\TripCalendarService;
use App\Services\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    private TripService $tripService;
    private ScanLogService $scanLogService;
    private TripCalendarService $tripCalendarService;

    public function __construct(
        TripService $tripService,
        ScanLogService $scanLogService,
        TripCalendarService $tripCalendarService,
    ) {
        $this->tripService = $tripService;
        $this->scanLogService = $scanLogService;
        $this->tripCalendarService = $tripCalendarService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Trip::query()->with(['truck', 'latestScan'])->latest('id');
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('truck_id')) {
            $query->where('truck_id', $request->integer('truck_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        return $this->successResponse(TripResource::collection($query->paginate($limit)));
    }

    public function show(Trip $trip): JsonResponse
    {
        return $this->successResponse(new TripResource($trip->load(['truck', 'latestScan'])));
    }

    public function active(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        $trips = Trip::query()
            ->with(['truck', 'latestScan'])
            ->where('is_active', true)
            ->latest('id')
            ->limit($limit)
            ->get();

        return $this->successResponse(OperatorTripResource::collection($trips));
    }

    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        $trips = Trip::query()
            ->with(['truck', 'latestScan'])
            ->where('status', Trip::STATUS_COMPLETED)
            ->latest('id')
            ->paginate($limit);

        return $this->successResponse(TripResource::collection($trips));
    }

    public function logs(Trip $trip): JsonResponse
    {
        return $this->successResponse(ScanLogResource::collection($this->scanLogService->getLogsByTrip($trip->id)));
    }

    public function calendarSummary(Request $request): JsonResponse
    {
        $fromInput = $request->query('from');
        $toInput = $request->query('to');

        if (! $fromInput || ! $toInput) {
            return $this->errorResponse('Validation failed', [
                'from' => ['The from field is required.'],
                'to' => ['The to field is required.'],
            ]);
        }

        $timezone = $this->parseTimezone($request->query('timezone'));
        if (! $timezone) {
            return $this->errorResponse('Validation failed', [
                'timezone' => ['The timezone field must be a valid IANA timezone.'],
            ]);
        }

        $dayStart = $this->parseDayStart($request->query('day_start', '07:00'));
        if (! $dayStart) {
            return $this->errorResponse('Validation failed', [
                'day_start' => ['The day_start field must be in HH:mm format.'],
            ]);
        }

        $from = $this->parseDate($fromInput, $timezone);
        $to = $this->parseDate($toInput, $timezone);

        if (! $from || ! $to) {
            return $this->errorResponse('Validation failed', [
                'from' => ['The from field must be in YYYY-MM-DD format.'],
                'to' => ['The to field must be in YYYY-MM-DD format.'],
            ]);
        }

        if ($from->gt($to)) {
            return $this->errorResponse('Validation failed', [
                'to' => ['The to date must be the same or after the from date.'],
            ]);
        }

        $filters = $this->extractCalendarFilters($request);
        $days = $this->tripCalendarService->getCalendarSummary($filters, $from, $to, $dayStart);

        return $this->successResponse([
            'data' => $days,
            'meta' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'day_start' => $dayStart,
                'timezone' => $timezone,
            ],
        ]);
    }

    public function byDay(Request $request): JsonResponse
    {
        $dayInput = $request->query('day');

        if (! $dayInput) {
            return $this->errorResponse('Validation failed', [
                'day' => ['The day field is required.'],
            ]);
        }

        $timezone = $this->parseTimezone($request->query('timezone'));
        if (! $timezone) {
            return $this->errorResponse('Validation failed', [
                'timezone' => ['The timezone field must be a valid IANA timezone.'],
            ]);
        }

        $dayStart = $this->parseDayStart($request->query('day_start', '07:00'));
        if (! $dayStart) {
            return $this->errorResponse('Validation failed', [
                'day_start' => ['The day_start field must be in HH:mm format.'],
            ]);
        }

        $day = $this->parseDate($dayInput, $timezone);
        if (! $day) {
            return $this->errorResponse('Validation failed', [
                'day' => ['The day field must be in YYYY-MM-DD format.'],
            ]);
        }

        $limit = max(1, min(100, (int) $request->query('limit', 20)));
        $filters = $this->extractCalendarFilters($request);

        $result = $this->tripCalendarService->getTripsByDay($filters, $day, $dayStart, $limit);
        $resource = TripResource::collection($result['paginator'])->additional([
            'summary' => $result['summary'],
            'window' => [
                'day' => $day->format('Y-m-d'),
                'start_at' => $result['window']['start_local']->toIso8601String(),
                'end_at' => $result['window']['end_local']->toIso8601String(),
                'day_start' => $dayStart,
                'timezone' => $timezone,
            ],
        ]);

        return $this->successResponse($resource);
    }

    public function operatorLastScans(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 10)));

        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->successResponse(ScanLogResource::collection($this->scanLogService->getLastScansByUser($user->id, $limit)));
    }

    private function parseTimezone(?string $timezone): ?string
    {
        $resolved = $timezone ?: (string) config('app.timezone');

        if (! in_array($resolved, timezone_identifiers_list(), true)) {
            return null;
        }

        return $resolved;
    }

    private function parseDayStart(?string $dayStart): ?string
    {
        if (! $dayStart || ! preg_match('/^\d{2}:\d{2}$/', $dayStart)) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $dayStart));

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function parseDate(string $value, string $timezone): ?CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('Y-m-d', $value, $timezone);

        if (! $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCalendarFilters(Request $request): array
    {
        return [
            'status' => $request->query('status'),
            'truck_id' => $request->query('truck_id'),
            'registration_number' => $request->query('registration_number'),
            'driver_name' => $request->query('driver_name'),
        ];
    }
}
