<?php

namespace App\Services;

use App\Models\MaintenanceRecord;

class MaintenanceService
{
    public function createRecord(array $data): MaintenanceRecord
    {
        return MaintenanceRecord::create($data);
    }

    public function updateRecord(MaintenanceRecord $record, array $data): MaintenanceRecord
    {
        $record->update($data);
        return $record->fresh();
    }

    public function deleteRecord(MaintenanceRecord $record): void
    {
        $record->delete();
    }
}
