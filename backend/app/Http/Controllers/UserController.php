<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 15)));

        $query = User::query()->latest('id');

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->filled('location')) {
            $query->where('location', $request->string('location')->toString());
        }

        return $this->successResponse($query->paginate($limit));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:ADMIN,COMPANY_OPERATOR,PORT_OPERATOR'],
            'location' => ['nullable', 'in:COMPANY,PORT'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return $this->successResponse($user, 'User created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        return $this->successResponse($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'in:ADMIN,COMPANY_OPERATOR,PORT_OPERATOR'],
            'location' => ['nullable', 'in:COMPANY,PORT'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return $this->successResponse($user->fresh(), 'User updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->successResponse(null, 'User deleted successfully');
    }
}
