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
            [
                'full_name' => 'Inventory Manager',
                'username' => 'inventory',
                'email' => 'inventory@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Customer Service Manager',
                'username' => 'customerservice',
                'email' => 'customerservice@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Procurement Manager',
                'username' => 'procurement',
                'email' => 'procurement@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Supply Chain Manager',
                'username' => 'supplychain',
                'email' => 'supplychain@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Finance Manager',
                'username' => 'finance',
                'email' => 'finance@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'E-Commerce Manager',
                'username' => 'ecommerce',
                'email' => 'ecommerce@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Business Intelligence Manager',
                'username' => 'businessintelligence',
                'email' => 'businessintelligence@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Sales Manager',
                'username' => 'sales',
                'email' => 'sales@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Project Manager',
                'username' => 'project',
                'email' => 'project@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'HR Manager',
                'username' => 'hr',
                'email' => 'hr@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        
        $this->command->info('👑 Admin: admin / password');
        $this->command->info('📦 Inventory: inventory / password');
        $this->command->info('🎫 Customer Service: customerservice / password');
        $this->command->info('🛒 Procurement: procurement / password');
        $this->command->info('🚚 Supply Chain: supplychain / password');
        $this->command->info('💰 Finance: finance / password');
        $this->command->info('🛍️ E-Commerce: ecommerce / password');
        $this->command->info('📊 Business Intelligence: businessintelligence / password');
        $this->command->info('📈 Sales: sales / password');
        $this->command->info('📋 Project: project / password');
        $this->command->info('👥 HR: hr / password');
    }
}