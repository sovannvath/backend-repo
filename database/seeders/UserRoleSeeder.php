<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder  extends Seeder
{
    public function run()
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $staffRole = Role::firstOrCreate(['name' => 'Staff']);
        $warehouseRole = Role::firstOrCreate(['name' => 'Warehouse']);

        // Create admin user
        User::firstOrCreate(
            ['email' => 'sovannvath69@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('0885778248Vath'),
                'role_id' => $adminRole->id
            ]
        );

        // Create staff user
        User::firstOrCreate(
            ['email' => 'nysreynit08@gmail.com'],
            [
                'name' => 'Staff User',
                'password' => bcrypt('0885778248Vath'),
                'role_id' => $staffRole->id
            ]
        );

        // Create warehouse user
        User::firstOrCreate(
            ['email' => 'kukseng99@gmail.com'],
            [
                'name' => 'Warehouse User',
                'password' => bcrypt('0885778248Vath'),
                'role_id' => $warehouseRole->id
            ]
        );
    }
}