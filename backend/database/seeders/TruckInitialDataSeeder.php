<?php

namespace Database\Seeders;

use App\Models\Truck;
use Illuminate\Database\Seeder;

class TruckInitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $trucks = [
            ['registration_number' => '47829 A 73', 'driver_name' => 'JILLALI EL BOUAMRI'],
            ['registration_number' => '47835 A 73', 'driver_name' => 'ISMAIL EL QOURS'],
            ['registration_number' => '47828 A 73', 'driver_name' => 'RACHID LAMCHAHAR'],
            ['registration_number' => '47840 A 73', 'driver_name' => 'MOUHAMED EZZAOUINE'],
            ['registration_number' => '47841 A 73', 'driver_name' => 'MOUJANE JAOUAD'],
            ['registration_number' => '47836 A 73', 'driver_name' => 'MANSOUR EL ACHBY'],
            ['registration_number' => '47837 A 73', 'driver_name' => 'ZAKARIYA ECHAOUTA'],
            ['registration_number' => '47833 A 73', 'driver_name' => 'ADIL ED DAHBI'],
            ['registration_number' => '47827 A 73', 'driver_name' => 'BOUCHAIB SEEDKI'],
            ['registration_number' => '47838 A 73', 'driver_name' => 'YOUNESS EL ALAMI'],
            ['registration_number' => '47831 A 73', 'driver_name' => 'AHMED MAHER'],
            ['registration_number' => '47865 A 73', 'driver_name' => 'MOUADE ROUCHD'],
            ['registration_number' => '47834 A 73', 'driver_name' => 'YASSINE AADLI'],
            ['registration_number' => '47832 A 73', 'driver_name' => ''],
            ['registration_number' => '66588 A 6', 'driver_name' => 'LMAACHI'],
            ['registration_number' => '4987 A 36', 'driver_name' => 'LMAACHI'],
            ['registration_number' => '56983 A 6', 'driver_name' => 'LMAACHI'],
            ['registration_number' => '66592 A 6', 'driver_name' => 'LMAACHI'],
            ['registration_number' => '30268 A 15', 'driver_name' => 'AZIZ'],
            ['registration_number' => '67095 A 1', 'driver_name' => 'AZIZ'],
            ['registration_number' => '55969 A 6', 'driver_name' => 'RACHID'],
            ['registration_number' => '39152 A 8', 'driver_name' => 'RACHID'],
            ['registration_number' => '32228 B 59', 'driver_name' => 'RHIMOU TRANS'],
            ['registration_number' => '32229 B 59', 'driver_name' => 'RHIMOU TRANS'],
            ['registration_number' => '32231 B 59', 'driver_name' => 'RHIMOU TRANS'],
            ['registration_number' => '37516 B 72', 'driver_name' => 'NEGSA'],
            ['registration_number' => '16179 A 59', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '15812 A 44', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '83958 A 8', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '3193 A 25', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '8436 A 20', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '33245 H 6', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '7409 A 10', 'driver_name' => 'ST STEEL (FOUAD)'],
            ['registration_number' => '6257 A 7', 'driver_name' => 'ST STEEL (FOUAD)'],
        ];

        foreach ($trucks as $truckData) {
            $registrationNumber = $truckData['registration_number'];
            $driverName = trim($truckData['driver_name']) !== '' ? trim($truckData['driver_name']) : null;

            Truck::query()->updateOrCreate(
                ['registration_number' => $registrationNumber],
                [
                    'driver_name' => $driverName,
                    'qr_code' => $this->buildQrCode($registrationNumber),
                    'is_active' => true,
                ]
            );
        }
    }

    private function buildQrCode(string $registrationNumber): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '-', $registrationNumber));

        return 'SOMASTEEL-TRUCK-'.trim($normalized, '-');
    }
}
