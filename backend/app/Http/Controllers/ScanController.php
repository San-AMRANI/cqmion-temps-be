<?php

namespace App\Http\Controllers;

use App\Exceptions\ScanException;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function __construct(private readonly ScanService $scanService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => ['required', 'string', 'max:2048'],
            'device_time' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            $response = $this->scanService->process(
                $validated['qr_code'],
                $user,
                $validated['device_id'] ?? null,
            );

            return $this->successResponse($response, 'Scan successful');
        } catch (ScanException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                ['scan' => $exception->getMessage()],
                $exception->statusCode()
            );
        }
    }
}
