<?php

namespace App\Services;

use App\Events\ArrivedAtPort;
use App\Events\LeftPort;
use App\Exceptions\ScanException;
use App\Events\TripCompleted;
use App\Events\TripStarted;
use App\Models\ScanLog;
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

            $nextAction = $this->resolveNextAction($activeTrip);
            $this->validationService->validateScan($operator, $activeTrip, $nextAction);

            if (! $activeTrip) {
                $trip = $this->tripService->createTrip($truck->id);
                $this->scanLogService->logScan($trip, $operator, ScanLog::ACTION_START, $operator->location, $deviceId);
                TripStarted::dispatch($trip);

                return $this->buildResponse($trip, ScanLog::ACTION_START);
            }

            if ($activeTrip->status === Trip::STATUS_STARTED) {
                $trip = $this->tripService->updateStatus($activeTrip, Trip::STATUS_ARRIVED_PORT);
                $this->scanLogService->logScan($trip, $operator, ScanLog::ACTION_ARRIVE, $operator->location, $deviceId);
                ArrivedAtPort::dispatch($trip);

                return $this->buildResponse($trip, ScanLog::ACTION_ARRIVE);
            }

            if ($activeTrip->status === Trip::STATUS_ARRIVED_PORT) {
                $trip = $this->tripService->updateStatus($activeTrip, Trip::STATUS_LEFT_PORT);
                $this->scanLogService->logScan($trip, $operator, ScanLog::ACTION_LEAVE, $operator->location, $deviceId);
                LeftPort::dispatch($trip);

                return $this->buildResponse($trip, ScanLog::ACTION_LEAVE);
            }

            if ($activeTrip->status === Trip::STATUS_LEFT_PORT) {
                $trip = $this->tripService->completeTrip($activeTrip);
                $this->scanLogService->logScan($trip, $operator, ScanLog::ACTION_RETURN, $operator->location, $deviceId);
                TripCompleted::dispatch($trip);

                return $this->buildResponse($trip, ScanLog::ACTION_RETURN);
            }

            throw new ScanException('Invalid scan sequence or unauthorized location.');
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

    private function resolveNextAction(?Trip $trip): string
    {
        if (! $trip) {
            return ScanLog::ACTION_START;
        }

        return match ($trip->status) {
            Trip::STATUS_STARTED => ScanLog::ACTION_ARRIVE,
            Trip::STATUS_ARRIVED_PORT => ScanLog::ACTION_LEAVE,
            Trip::STATUS_LEFT_PORT => ScanLog::ACTION_RETURN,
            default => ScanLog::ACTION_START,
        };
    }

    private function buildResponse(Trip $trip, string $action): array
    {
        $trip->loadMissing('truck');

        $nextExpectedStep = match ($trip->status) {
            Trip::STATUS_STARTED => Trip::STATUS_ARRIVED_PORT,
            Trip::STATUS_ARRIVED_PORT => Trip::STATUS_LEFT_PORT,
            Trip::STATUS_LEFT_PORT => Trip::STATUS_COMPLETED,
            default => null,
        };

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
}
