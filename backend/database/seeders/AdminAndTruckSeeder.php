<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminAndTruckSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@somasteel.local'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'location' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'operateur.somasteel@somasteel.local'],
            [
                'name' => 'Company Operator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_COMPANY_OPERATOR,
                'location' => User::LOCATION_COMPANY,
            ]
        );

        User::updateOrCreate(
            ['email' => 'operateur.port@somasteel.local'],
            [
                'name' => 'Port Operator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_PORT_OPERATOR,
                'location' => User::LOCATION_PORT,
            ]
        );
    }
}
