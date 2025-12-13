<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);

        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            // Create Admin for each organization
            User::create([
                'name' => 'Admin ' . $organization->name,
                'email' => 'admin_' . strtolower(str_replace(' ', '', $organization->name)) . '@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'organization_id' => $organization->id,
            ]);

            // Create Regular User for each organization
            User::create([
                'name' => 'User ' . $organization->name,
                'email' => 'user_' . strtolower(str_replace(' ', '', $organization->name)) . '@example.com',
                'password' => Hash::make('password'),
                'role' => 'user',
                'organization_id' => $organization->id,
            ]);
        }
    }
}
