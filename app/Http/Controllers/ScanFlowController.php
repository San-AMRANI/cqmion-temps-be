<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScanFlowResource;
use App\Models\ScanFlow;
use App\Models\Trip;
use App\Services\ScanFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScanFlowController extends Controller
{
    public function __construct(private readonly ScanFlowService $scanFlowService)
    {
    }

    public function show(): JsonResponse
    {
        $flow = ScanFlow::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $flow) {
            $flow = new ScanFlow([
                'steps' => $this->scanFlowService->getActiveSteps(),
                'is_active' => true,
            ]);
        }

        return $this->successResponse(new ScanFlowResource($flow));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => [
                'required',
                'string',
                Rule::in(array_keys(ScanFlowService::STATUS_TO_ACTION)),
            ],
        ]);

        $steps = array_values($validated['steps']);

        if (count($steps) !== count(array_unique($steps))) {
            throw ValidationException::withMessages([
                'steps' => ['Steps must be unique.'],
            ]);
        }

        if (end($steps) !== Trip::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'steps' => ['The last step must be COMPLETED.'],
            ]);
        }

        $flow = ScanFlow::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $flow) {
            $flow = ScanFlow::create([
                'steps' => $steps,
                'is_active' => true,
            ]);
        } else {
            $flow->update([
                'steps' => $steps,
            ]);
        }

        return $this->successResponse(new ScanFlowResource($flow), 'Scan flow updated successfully');
    }
}
