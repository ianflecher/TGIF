<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing admin users
        DB::table('users')->whereIn('role', ['admin', 'manager', 'employee'])->delete();

        // Insert admin users
        DB::table('users')->insert([
            [
                'full_name' => 'System Administrator',
                'username' => 'admin',
                'email' => 'admin@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Manager User',
                'username' => 'manager',
                'email' => 'manager@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Employee User',
                'username' => 'employee',
                'email' => 'employee@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'John Customer',
                'username' => 'customer',
                'email' => 'customer@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $this->command->info('✅ Users created successfully!');
        $this->command->info('👑 Admin: admin / password');
        $this->command->info('👨‍💼 Manager: manager / password');
        $this->command->info('👩‍💼 Employee: employee / password');
        $this->command->info('👤 Customer: customer / password');
    }
}