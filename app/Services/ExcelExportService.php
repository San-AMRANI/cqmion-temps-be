<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Collection;

class ExcelExportService
{
    public function exportTrips(Collection $trips): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // French headers
        $headers = [
            'ID du Voyage', 'Camion (Matricule)', 'Chauffeur', 'Statut', 
            'Date de Début', 'Arrivée au Port', 'Départ du Port', 'Date de Fin',
            'Durée (Compagnie -> Port)', 'Durée au Port', 'Durée (Port -> Compagnie)', 'Durée Totale'
        ];
        
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($trips as $trip) {
            // Assume formatWithDurations hasn't been called directly here, or we calculate it manually
            $durations = [
                'company_to_port' => $trip->started_at && $trip->arrived_port_at ? $trip->started_at->diffInSeconds($trip->arrived_port_at) : null,
                'port_duration' => $trip->arrived_port_at && $trip->left_port_at ? $trip->arrived_port_at->diffInSeconds($trip->left_port_at) : null,
                'port_to_company' => $trip->left_port_at && $trip->completed_at ? $trip->left_port_at->diffInSeconds($trip->completed_at) : null,
                'total_duration' => $trip->started_at && $trip->completed_at ? $trip->started_at->diffInSeconds($trip->completed_at) : null,
            ];

            $sheet->fromArray([
                $trip->id,
                $trip->truck?->registration_number,
                $trip->truck?->driver_name,
                $trip->status,
                $trip->started_at,
                $trip->arrived_port_at,
                $trip->left_port_at,
                $trip->completed_at,
                $durations['company_to_port'],
                $durations['port_duration'],
                $durations['port_to_company'],
                $durations['total_duration']
            ], null, 'A' . $row);
            $row++;
        }

        $fileName = 'Export_Voyages_' . now()->format('Y_m_d_His') . '.xlsx';
        $filePath = storage_path('app/public/' . $fileName);
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }
}
