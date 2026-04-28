<?php

namespace App\Services;

use App\Exceptions\ScanException;
use App\Models\ScanLog;
use App\Models\Trip;
use App\Models\User;

class ValidationService
{
    public function validateScan(User $user, ?Trip $trip, string $nextAction): void
    {
        if (! in_array($user->role, [User::ROLE_COMPANY_OPERATOR, User::ROLE_PORT_OPERATOR], true)) {
            throw new ScanException('Only operators can perform scan actions.', 403);
        }

        $this->validateRoleAndLocation($user, $trip, $nextAction);
    }

    private function validateRoleAndLocation(User $user, ?Trip $trip, string $nextAction): void
    {
        if ($nextAction === ScanLog::ACTION_START) {
            if ($user->role !== User::ROLE_COMPANY_OPERATOR || $user->location !== User::LOCATION_COMPANY) {
                throw new ScanException('Invalid scan sequence or unauthorized location.', 403);
            }

            return;
        }

        if (in_array($nextAction, [ScanLog::ACTION_ARRIVE, ScanLog::ACTION_LEAVE], true)) {
            if ($user->role !== User::ROLE_PORT_OPERATOR || $user->location !== User::LOCATION_PORT) {
                throw new ScanException('Invalid scan sequence or unauthorized location.', 403);
            }

            return;
        }

        if ($nextAction === ScanLog::ACTION_RETURN) {
            if ($user->role !== User::ROLE_COMPANY_OPERATOR || $user->location !== User::LOCATION_COMPANY) {
                throw new ScanException('Invalid scan sequence or unauthorized location.', 403);
            }
        }
    }

}
