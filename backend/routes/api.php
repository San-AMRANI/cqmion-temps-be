<?php

use App\Http\Controllers\AdminScanLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanFlowController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TruckController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/setup', function () {
    Artisan::call('migrate --force');
    Artisan::call('db:seed --force');
    return 'Database migrated and seeded!';
});
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:ADMIN')->group(function (): void {
        Route::apiResource('trucks', TruckController::class);
        Route::post('/trucks/{truck}/generate-qr', [TruckController::class, 'generateQr']);
        Route::patch('/trucks/{truck}/activate', [TruckController::class, 'activate']);
        Route::patch('/trucks/{truck}/deactivate', [TruckController::class, 'deactivate']);

        Route::get('/scan-flow', [ScanFlowController::class, 'show']);
        Route::put('/scan-flow', [ScanFlowController::class, 'update']);

        Route::apiResource('users', UserController::class);

        Route::get('/trips', [TripController::class, 'index']);
        Route::get('/trips/active', [TripController::class, 'active']);
        Route::get('/trips/history', [TripController::class, 'history']);
        Route::get('/trips/{trip}', [TripController::class, 'show']);
        Route::get('/trips/{trip}/logs', [TripController::class, 'logs']);
        Route::get('/scan-logs', [AdminScanLogController::class, 'index']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/truck/{truck}', [ReportController::class, 'truck']);
        Route::get('/reports/durations', [ReportController::class, 'durations']);
        Route::get('/reports/delays', [ReportController::class, 'delays']);
        Route::get('/reports/export', [ReportController::class, 'export']);
    });

    Route::middleware('role:COMPANY_OPERATOR,PORT_OPERATOR')->group(function (): void {
        Route::post('/scan', [ScanController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/operator/last-scans', [TripController::class, 'operatorLastScans']);
    });

    Route::middleware('role:ADMIN,COMPANY_OPERATOR,PORT_OPERATOR')->group(function (): void {
        Route::get('/trucks/{truck}/basic', [TruckController::class, 'basic']);
    });
});
