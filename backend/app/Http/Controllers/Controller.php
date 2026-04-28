<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function successResponse(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => null,
        ], $status);
    }

    protected function errorResponse(string $message, mixed $errors = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
