<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing admin users and their employee records
        DB::table('users')->whereIn('role', ['admin'])->delete();
        DB::table('employees')->delete();

        // Insert admin users
        $adminUsers = [
            [
                'full_name' => 'System Administrator',
                'username' => 'admin',
                'email' => 'admin@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'System Administrator',
                    'hire_date' => '2024-01-01',
                    'salary' => 80000.00,
                ]
            ],
            [
                'full_name' => 'Inventory Manager',
                'username' => 'inventory',
                'email' => 'inventory@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Inventory Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 65000.00,
                ]
            ],
            [
                'full_name' => 'Customer Service Manager',
                'username' => 'customerservice',
                'email' => 'customerservice@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Customer Service Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 60000.00,
                ]
            ],
            [
                'full_name' => 'Procurement Manager',
                'username' => 'procurement',
                'email' => 'procurement@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Procurement Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 70000.00,
                ]
            ],
            [
                'full_name' => 'Supply Chain Manager',
                'username' => 'supplychain',
                'email' => 'supplychain@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Supply Chain Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 75000.00,
                ]
            ],
            [
                'full_name' => 'Finance Manager',
                'username' => 'finance',
                'email' => 'finance@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Finance Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 85000.00,
                ]
            ],
            [
                'full_name' => 'E-Commerce Manager',
                'username' => 'ecommerce',
                'email' => 'ecommerce@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'E-Commerce Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 70000.00,
                ]
            ],
            [
                'full_name' => 'Business Intelligence Manager',
                'username' => 'businessintelligence',
                'email' => 'businessintelligence@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Business Intelligence Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 80000.00,
                ]
            ],
            [
                'full_name' => 'Sales Manager',
                'username' => 'sales',
                'email' => 'sales@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Sales Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 70000.00,
                ]
            ],
            [
                'full_name' => 'Project Manager',
                'username' => 'project',
                'email' => 'project@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'Project Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 75000.00,
                ]
            ],
            [
                'full_name' => 'HR Manager',
                'username' => 'hr',
                'email' => 'hr@tgif.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'employee_data' => [
                    'job_title' => 'HR Manager',
                    'hire_date' => '2024-01-01',
                    'salary' => 65000.00,
                ]
            ],
        ];

        foreach ($adminUsers as $admin) {
            // Insert into users table
            $userId = DB::table('users')->insertGetId([
                'full_name' => $admin['full_name'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'password' => $admin['password'],
                'role' => $admin['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into employees table (department_id can be null)
            DB::table('employees')->insert([
                'user_id' => $userId,
                'department_id' => null, // Set to null since it's nullable
                'job_title' => $admin['employee_data']['job_title'],
                'hire_date' => $admin['employee_data']['hire_date'],
                'salary' => $admin['employee_data']['salary'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
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
        $this->command->info('✅ Created 11 admin employees in the employees table (department_id set to null)');
    }
}