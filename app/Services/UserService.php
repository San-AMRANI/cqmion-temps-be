<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function search(array $filters = []): Collection
    {
        $query = User::query();
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (!empty($filters['search'])) {
            $query->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'LIKE', '%' . $filters['search'] . '%');
        }
        return $query->get();
    }

    public function getStats(): array
    {
        return [
            'total' => User::count(),
            'by_role' => User::selectRaw('role, COUNT(*) as count')->groupBy('role')->pluck('count', 'role'),
            'by_location' => User::selectRaw('location, COUNT(*) as count')->groupBy('location')->pluck('count', 'location')
        ];
    }

    public function getActivity(User $user): array
    {
        return [
            'scans' => \App\Models\ScanLog::where('user_id', $user->id)->count(),
            'latest_scan' => \App\Models\ScanLog::where('user_id', $user->id)->latest()->first()
        ];
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword)
        ]);
    }
}
