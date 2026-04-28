<?php

namespace App\Services;

use App\Events\ArrivedAtPort;
use App\Events\LeftPort;
use App\Exceptions\ScanException;
use App\Events\TripCompleted;
use App\Events\TripStarted;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScanService
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly ScanLogService $scanLogService,
        private readonly ValidationService $validationService,
        private readonly ScanFlowService $scanFlowService,
    ) {
    }

    public function process(string $qrCode, User $operator, ?string $deviceId = null): array
    {
        return DB::transaction(function () use ($qrCode, $operator, $deviceId) {
            $truck = $this->resolveTruckFromScanInput($qrCode);

            if (! $truck) {
                throw new ScanException('Truck not found for provided QR code.', 404);
            }

            if (! $truck->is_active) {
                throw new ScanException('Truck is inactive and cannot be scanned.', 422);
            }

            $activeTrip = Trip::where('truck_id', $truck->id)
                ->where('is_active', true)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $nextStatus = $this->scanFlowService->resolveNextStep($activeTrip);
            $nextAction = $this->scanFlowService->resolveAction($nextStatus);

            $this->validationService->validateScan($operator, $activeTrip, $nextAction);

            if (! $activeTrip) {
                $trip = $this->tripService->createTrip($truck->id, $nextStatus);
                $this->scanLogService->logScan($trip, $operator, $nextAction, $operator->location, $deviceId);
                $this->dispatchEvent($trip, $nextStatus);

                return $this->buildResponse($trip, $nextAction);
            }

            $trip = $this->tripService->updateStatus($activeTrip, $nextStatus);
            $this->scanLogService->logScan($trip, $operator, $nextAction, $operator->location, $deviceId);
            $this->dispatchEvent($trip, $nextStatus);

            return $this->buildResponse($trip, $nextAction);
        });
    }

    private function resolveTruckFromScanInput(string $rawScanValue): ?Truck
    {
        $candidates = $this->extractQrCandidates($rawScanValue);

        return Truck::query()
            ->whereIn('qr_code', $candidates)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return list<string>
     */
    private function extractQrCandidates(string $rawScanValue): array
    {
        $candidates = [];
        $addCandidate = function (string $value) use (&$candidates): void {
            $normalized = strtoupper(trim($value));
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
        };

        $rawScanValue = trim($rawScanValue);
        $addCandidate($rawScanValue);

        if (filter_var($rawScanValue, FILTER_VALIDATE_URL)) {
            $parts = parse_url($rawScanValue) ?: [];

            if (! empty($parts['query'])) {
                parse_str((string) $parts['query'], $query);

                foreach (['code', 'qr_code'] as $key) {
                    if (! empty($query[$key]) && is_string($query[$key])) {
                        $addCandidate($query[$key]);
                    }
                }
            }

            if (! empty($parts['path'])) {
                $segments = array_values(array_filter(explode('/', trim((string) $parts['path'], '/'))));

                if ($segments !== []) {
                    $addCandidate((string) urldecode((string) end($segments)));
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (preg_match('/SOMASTEEL-[A-Z0-9-]+/', $candidate, $matches) === 1) {
                $addCandidate($matches[0]);
            }
        }

        return array_values(array_unique($candidates));
    }

    private function buildResponse(Trip $trip, string $action): array
    {
        $trip->loadMissing('truck');

        $nextExpectedStep = $this->scanFlowService->getNextStepForStatus($trip->status);

        return [
            'status' => 'SUCCESS',
            'message' => 'Scan successful',
            'current_step' => $trip->status,
            'next_expected_step' => $nextExpectedStep,
            'is_locked' => true,
            'trip_summary' => [
                'trip_id' => $trip->id,
                'truck_id' => $trip->truck_id,
                'status' => $trip->status,
                'truck' => [
                    'id' => $trip->truck?->id ?? $trip->truck_id,
                    'registration_number' => $trip->truck?->registration_number ?? '',
                    'driver_name' => $trip->truck?->driver_name ?? '',
                ],
                'action' => $action,
                'timestamps' => [
                    'started_at' => $trip->started_at,
                    'arrived_port_at' => $trip->arrived_port_at,
                    'left_port_at' => $trip->left_port_at,
                    'completed_at' => $trip->completed_at,
                ],
            ],
        ];
    }

    private function dispatchEvent(Trip $trip, string $status): void
    {
        if ($status === Trip::STATUS_STARTED) {
            TripStarted::dispatch($trip);
        }

        if ($status === Trip::STATUS_ARRIVED_PORT) {
            ArrivedAtPort::dispatch($trip);
        }

        if ($status === Trip::STATUS_LEFT_PORT) {
            LeftPort::dispatch($trip);
        }

        if ($status === Trip::STATUS_COMPLETED) {
            TripCompleted::dispatch($trip);
        }
    }
}
