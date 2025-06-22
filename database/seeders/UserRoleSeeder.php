<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        // Create admin user
        User::firstOrCreate(
            ['email' => 'sovannvath69@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('0885778248Vath'),
                'role' => 'admin', // ✅ store role as string
            ]
        );

        // Create staff user
        User::firstOrCreate(
            ['email' => 'nysreynit08@gmail.com'],
            [
                'name' => 'Staff User',
                'password' => bcrypt('0885778248Vath'),
                'role' => 'staff',
            ]
        );

        // Create warehouse user
        User::firstOrCreate(
            ['email' => 'kukseng99@gmail.com'],
            [
                'name' => 'Warehouse User',
                'password' => bcrypt('0885778248Vath'),
                'role' => 'warehouse',
            ]
        );
    }
}
