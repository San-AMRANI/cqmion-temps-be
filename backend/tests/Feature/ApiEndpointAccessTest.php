<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEndpointAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_frontend_endpoints_do_not_return_404_when_authenticated(): void
    {
        $context = $this->buildRouteContext();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'location' => null,
        ]);

        Sanctum::actingAs($admin);

        $endpoints = [
            ['GET', '/api/me', []],
            ['GET', '/api/trucks?limit=20&page=1&is_active=true', []],
            ['GET', '/api/trucks/'.$context['truck_id'], []],
            ['GET', '/api/trucks/'.$context['truck_id'].'/basic', []],
            ['GET', '/api/users?limit=20&page=1', []],
            ['GET', '/api/users/'.$context['user_id'], []],
            ['GET', '/api/trips?limit=20&page=1', []],
            ['GET', '/api/trips/active', []],
            ['GET', '/api/trips/history', []],
            ['GET', '/api/trips/'.$context['trip_id'], []],
            ['GET', '/api/trips/'.$context['trip_id'].'/logs', []],
            ['GET', '/api/scan-logs?limit=20&page=1', []],
            ['GET', '/api/reports/summary', []],
            ['GET', '/api/reports/truck/'.$context['truck_id'], []],
            ['GET', '/api/reports/durations', []],
            ['GET', '/api/reports/delays', []],
            ['GET', '/api/reports/export', []],
        ];

        foreach ($endpoints as [$method, $uri, $payload]) {
            $response = $this->requestJson($method, $uri, $payload);

            if ($response->getStatusCode() === 404) {
                throw new \RuntimeException(sprintf('Authenticated admin request returned 404 for [%s] %s', $method, $uri));
            }
        }
    }

    public function test_operator_frontend_endpoints_do_not_return_404_when_authenticated(): void
    {
        $context = $this->buildRouteContext();

        $operator = User::factory()->create([
            'role' => User::ROLE_COMPANY_OPERATOR,
            'location' => User::LOCATION_COMPANY,
        ]);

        Sanctum::actingAs($operator);

        $endpoints = [
            ['GET', '/api/me', []],
            ['GET', '/api/trucks/'.$context['truck_id'].'/basic', []],
            ['GET', '/api/operator/last-scans', []],
            ['POST', '/api/scan', [
                'truck_qr' => 'QR-TEST-001',
                'device_id' => 'dev-test-1',
            ]],
        ];

        foreach ($endpoints as [$method, $uri, $payload]) {
            $response = $this->requestJson($method, $uri, $payload);

            if ($response->getStatusCode() === 404) {
                throw new \RuntimeException(sprintf('Authenticated operator request returned 404 for [%s] %s', $method, $uri));
            }
        }
    }

    public function test_all_protected_endpoints_return_401_when_unauthenticated(): void
    {
        $context = $this->buildRouteContext();

        foreach ($this->allProtectedEndpoints($context) as [$method, $uri, $payload]) {
            $response = $this->requestJson($method, $uri, $payload);

            $response->assertStatus(401);
        }
    }

    public function test_admin_endpoints_return_403_for_company_operator(): void
    {
        $context = $this->buildRouteContext();

        $operator = User::factory()->create([
            'role' => User::ROLE_COMPANY_OPERATOR,
            'location' => User::LOCATION_COMPANY,
        ]);

        Sanctum::actingAs($operator);

        foreach ($this->adminEndpoints($context) as [$method, $uri, $payload]) {
            $response = $this->requestJson($method, $uri, $payload);

            $response->assertStatus(403);
        }
    }

    public function test_operator_only_endpoints_return_403_for_admin(): void
    {
        $context = $this->buildRouteContext();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'location' => null,
        ]);

        Sanctum::actingAs($admin);

        foreach ($this->operatorOnlyEndpoints() as [$method, $uri, $payload]) {
            $response = $this->requestJson($method, $uri, $payload);

            $response->assertStatus(403);
        }
    }

    /**
     * @return array{truck_id:int,trip_id:int,user_id:int}
     */
    private function buildRouteContext(): array
    {
        $truck = Truck::query()->create([
            'registration_number' => 'TEST-TRUCK-001',
            'driver_name' => 'Test Driver 001',
            'qr_code' => 'QR-TEST-001',
            'is_active' => true,
        ]);

        $trip = Trip::query()->create([
            'truck_id' => $truck->id,
            'status' => Trip::STATUS_STARTED,
            'is_active' => true,
            'started_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'location' => null,
        ]);

        return [
            'truck_id' => $truck->id,
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ];
    }

    /**
     * @param  array{truck_id:int,trip_id:int,user_id:int}  $context
     * @return list<array{string,string,array<string,mixed>}>
     */
    private function allProtectedEndpoints(array $context): array
    {
        return [
            ['POST', '/api/logout', []],
            ['GET', '/api/me', []],
            ...$this->adminEndpoints($context),
            ...$this->operatorOnlyEndpoints(),
            ['GET', '/api/trucks/'.$context['truck_id'].'/basic', []],
        ];
    }

    /**
     * @param  array{truck_id:int,trip_id:int,user_id:int}  $context
     * @return list<array{string,string,array<string,mixed>}>
     */
    private function adminEndpoints(array $context): array
    {
        return [
            ['GET', '/api/trucks', []],
            ['POST', '/api/trucks', [
                'registration_number' => 'NEW-TRUCK-001',
                'driver_name' => 'New Driver 001',
                'qr_code' => 'NEW-QR-001',
                'is_active' => true,
            ]],
            ['GET', '/api/trucks/'.$context['truck_id'], []],
            ['PUT', '/api/trucks/'.$context['truck_id'], [
                'registration_number' => 'UPDATED-TRUCK-001',
                'driver_name' => 'Updated Driver 001',
            ]],
            ['DELETE', '/api/trucks/'.$context['truck_id'], []],
            ['PATCH', '/api/trucks/'.$context['truck_id'].'/activate', []],
            ['PATCH', '/api/trucks/'.$context['truck_id'].'/deactivate', []],
            ['POST', '/api/trucks/'.$context['truck_id'].'/generate-qr', []],

            ['GET', '/api/users', []],
            ['POST', '/api/users', [
                'name' => 'User Test',
                'email' => 'user-test@example.com',
                'password' => 'password123',
                'role' => User::ROLE_COMPANY_OPERATOR,
                'location' => User::LOCATION_COMPANY,
            ]],
            ['GET', '/api/users/'.$context['user_id'], []],
            ['PUT', '/api/users/'.$context['user_id'], [
                'name' => 'Updated User Test',
            ]],
            ['DELETE', '/api/users/'.$context['user_id'], []],

            ['GET', '/api/trips', []],
            ['GET', '/api/trips/'.$context['trip_id'], []],
            ['GET', '/api/trips/active', []],
            ['GET', '/api/trips/history', []],
            ['GET', '/api/trips/'.$context['trip_id'].'/logs', []],
            ['GET', '/api/scan-logs?limit=20&page=1', []],

            ['GET', '/api/reports/summary', []],
            ['GET', '/api/reports/truck/'.$context['truck_id'], []],
            ['GET', '/api/reports/durations', []],
            ['GET', '/api/reports/delays', []],
            ['GET', '/api/reports/export', []],
        ];
    }

    /**
     * @return list<array{string,string,array<string,mixed>}>
     */
    private function operatorOnlyEndpoints(): array
    {
        return [
            ['POST', '/api/scan', [
                'truck_qr' => 'QR-TEST-001',
                'device_id' => 'dev-test-1',
            ]],
            ['GET', '/api/operator/last-scans', []],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function requestJson(string $method, string $uri, array $payload)
    {
        return match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'PUT' => $this->putJson($uri, $payload),
            'PATCH' => $this->patchJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri, $payload),
            default => throw new \InvalidArgumentException('Unsupported HTTP method '.$method),
        };
    }
}
