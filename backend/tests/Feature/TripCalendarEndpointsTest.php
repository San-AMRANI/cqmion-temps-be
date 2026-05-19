<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripCalendarEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_summary_groups_trips_by_day_start(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'location' => null,
        ]);

        Sanctum::actingAs($admin);

        $truck = Truck::query()->create([
            'registration_number' => 'CAL-TRUCK-001',
            'driver_name' => 'Calendar Driver',
            'qr_code' => 'CAL-QR-001',
            'is_active' => true,
        ]);

        Trip::query()->create([
            'truck_id' => $truck->id,
            'status' => Trip::STATUS_STARTED,
            'is_active' => true,
            'started_at' => CarbonImmutable::parse('2026-05-18 08:00:00', 'UTC'),
        ]);

        Trip::query()->create([
            'truck_id' => $truck->id,
            'status' => Trip::STATUS_COMPLETED,
            'is_active' => null,
            'started_at' => CarbonImmutable::parse('2026-05-19 06:30:00', 'UTC'),
            'completed_at' => CarbonImmutable::parse('2026-05-19 08:00:00', 'UTC'),
        ]);

        Trip::query()->create([
            'truck_id' => $truck->id,
            'status' => Trip::STATUS_COMPLETED,
            'is_active' => null,
            'started_at' => CarbonImmutable::parse('2026-05-19 07:30:00', 'UTC'),
            'completed_at' => CarbonImmutable::parse('2026-05-19 12:00:00', 'UTC'),
        ]);

        $response = $this->getJson('/api/trips/calendar?from=2026-05-18&to=2026-05-19&day_start=07:00&timezone=UTC');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $days = $response->json('data.data');

        $this->assertCount(2, $days);
        $this->assertSame('2026-05-18', $days[0]['day']);
        $this->assertSame(2, $days[0]['total']);
        $this->assertSame('2026-05-19', $days[1]['day']);
        $this->assertSame(1, $days[1]['total']);
    }

    public function test_trips_by_day_returns_summary_and_paginated_trips(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'location' => null,
        ]);

        Sanctum::actingAs($admin);

        $truck = Truck::query()->create([
            'registration_number' => 'DAY-TRUCK-001',
            'driver_name' => 'Day Driver',
            'qr_code' => 'DAY-QR-001',
            'is_active' => true,
        ]);

        Trip::query()->create([
            'truck_id' => $truck->id,
            'status' => Trip::STATUS_STARTED,
            'is_active' => true,
            'started_at' => CarbonImmutable::parse('2026-05-18 08:00:00', 'UTC'),
        ]);

        Trip::query()->create([
            'truck_id' => $truck->id,
            'status' => Trip::STATUS_COMPLETED,
            'is_active' => null,
            'started_at' => CarbonImmutable::parse('2026-05-19 06:30:00', 'UTC'),
            'completed_at' => CarbonImmutable::parse('2026-05-19 08:00:00', 'UTC'),
        ]);

        $response = $this->getJson('/api/trips/by-day?day=2026-05-18&day_start=07:00&timezone=UTC&limit=10');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.summary.total', 2);
        $this->assertCount(2, $response->json('data.data'));
    }
}
