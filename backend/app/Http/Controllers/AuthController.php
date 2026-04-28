<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration ? Carbon::now()->addMinutes((int) $expiration) : null;
        $token = $user->createToken($credentials['device_name'] ?? 'api-token', ['*'], $expiresAt)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => $user,
            'expires_at' => $expiresAt,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->successResponse(null, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse($request->user());
    }
}
