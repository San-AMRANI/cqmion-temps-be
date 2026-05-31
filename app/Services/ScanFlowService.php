<?php

namespace App\Services;

use App\Exceptions\ScanException;
use App\Models\ScanFlow;
use App\Models\ScanLog;
use App\Models\Trip;

class ScanFlowService
{
    public const DEFAULT_STEPS = [
        Trip::STATUS_STARTED,
        Trip::STATUS_ARRIVED_PORT,
        Trip::STATUS_LEFT_PORT,
        Trip::STATUS_COMPLETED,
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_TO_ACTION = [
        Trip::STATUS_STARTED => ScanLog::ACTION_START,
        Trip::STATUS_ARRIVED_PORT => ScanLog::ACTION_ARRIVE,
        Trip::STATUS_LEFT_PORT => ScanLog::ACTION_LEAVE,
        Trip::STATUS_COMPLETED => ScanLog::ACTION_RETURN,
    ];

    private ?array $cachedSteps = null;

    /**
     * @return list<string>
     */
    public function getActiveSteps(): array
    {
        if ($this->cachedSteps !== null) {
            return $this->cachedSteps;
        }

        $flow = ScanFlow::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $steps = $this->normalizeSteps($flow?->steps);

        $this->cachedSteps = $steps !== [] ? $steps : self::DEFAULT_STEPS;

        return $this->cachedSteps;
    }

    public function getNextStepForStatus(string $status): ?string
    {
        $steps = $this->getActiveSteps();
        $currentIndex = array_search($status, $steps, true);

        if ($currentIndex === false) {
            return $steps[0] ?? null;
        }

        return $steps[$currentIndex + 1] ?? null;
    }

    public function resolveNextStep(?Trip $trip): string
    {
        $steps = $this->getActiveSteps();

        if ($steps === []) {
            throw new ScanException('Scan flow is not configured.', 500);
        }

        if (! $trip) {
            return $steps[0];
        }

        if ($trip->status === Trip::STATUS_COMPLETED) {
            throw new ScanException('Trip is already completed.', 409);
        }

        $currentIndex = array_search($trip->status, $steps, true);

        if ($currentIndex === false) {
            return $steps[0];
        }

        $nextStep = $steps[$currentIndex + 1] ?? null;

        if (! $nextStep) {
            throw new ScanException('Trip is already completed.', 409);
        }

        return $nextStep;
    }

    public function resolveAction(string $status): string
    {
        $action = self::STATUS_TO_ACTION[$status] ?? null;

        if (! $action) {
            throw new ScanException('Scan flow action is not supported.', 500);
        }

        return $action;
    }

    /**
     * @param mixed $steps
     * @return list<string>
     */
    private function normalizeSteps(mixed $steps): array
    {
        if (! is_array($steps)) {
            return [];
        }

        $normalized = [];
        foreach ($steps as $step) {
            if (is_string($step) && isset(self::STATUS_TO_ACTION[$step])) {
                $normalized[] = $step;
            }
        }

        return array_values(array_unique($normalized));
    }
}
