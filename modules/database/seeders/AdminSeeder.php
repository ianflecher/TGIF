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
        DB::table('users')->whereIn('role', ['admin'])->delete();

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
        ]);
        $this->command->info('👑 Admin: admin / password');
    }
}