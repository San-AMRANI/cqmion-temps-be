<?php

namespace App\Services;

use App\Models\Truck;
use Illuminate\Support\Str;

class TruckService
{
    private const COMPANY_PREFIX = 'SOMASTEEL-';
    private const TYPE_PREFIX = 'TRUCK-';
    private const QR_PREFIX = self::COMPANY_PREFIX.self::TYPE_PREFIX;

    public function createTruck(array $data): Truck
    {
        if (empty($data['qr_code'])) {
            $source = (string) ($data['registration_number'] ?? Str::random(8));
            $data['qr_code'] = $this->buildQrCodeFromRegistration($source);
        } else {
            $data['qr_code'] = $this->normalizeQrCode((string) $data['qr_code']);
        }

        return Truck::create($data);
    }

    public function updateTruck(Truck $truck, array $data): Truck
    {
        if (array_key_exists('registration_number', $data) && ! array_key_exists('qr_code', $data)) {
            $data['qr_code'] = $this->buildQrCodeFromRegistration((string) $data['registration_number']);
        }

        if (array_key_exists('qr_code', $data) && ! empty($data['qr_code'])) {
            $data['qr_code'] = $this->normalizeQrCode((string) $data['qr_code']);
        }

        $truck->update($data);

        return $truck->fresh();
    }

    public function deleteTruck(Truck $truck): void
    {
        $truck->delete();
    }

    public function findByQrCode(string $qr): ?Truck
    {
        return Truck::query()->where('qr_code', $qr)->first();
    }

    public function generateQrCode(Truck $truck): string
    {
        $qr = $this->buildQrCodeFromRegistration($truck->registration_number);
        $truck->update(['qr_code' => $qr]);

        return $qr;
    }

    private function normalizeQrCode(string $qrCode): string
    {
        $normalized = strtoupper(trim($qrCode));
        $normalized = (string) preg_replace('/[^A-Z0-9-]+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if (str_starts_with($normalized, self::QR_PREFIX)) {
            return $normalized;
        }

        if (str_starts_with($normalized, self::COMPANY_PREFIX)) {
            $normalized = substr($normalized, strlen(self::COMPANY_PREFIX));
        }

        if (! str_starts_with($normalized, self::TYPE_PREFIX)) {
            $normalized = self::TYPE_PREFIX.$normalized;
        }

        return self::COMPANY_PREFIX.$normalized;
    }

    private function buildQrCodeFromRegistration(string $registrationNumber): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]+/', '-', $registrationNumber));

        return self::QR_PREFIX.trim($normalized, '-');
    }

    public function activate(Truck $truck): Truck
    {
        $truck->update(['is_active' => true]);

        return $truck->fresh();
    }

    public function deactivate(Truck $truck): Truck
    {
        $truck->update(['is_active' => false]);

        return $truck->fresh();
    }
}
