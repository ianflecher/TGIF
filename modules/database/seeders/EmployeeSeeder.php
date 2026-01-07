<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('employees')->truncate();
        DB::table('users')->where('role', 'employee')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create 5 employee users
        $users = [
            [
                'full_name' => 'John Smith',
                'username' => 'john.smith',
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'email' => 'john.smith@company.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Sarah Johnson',
                'username' => 'sarah.j',
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'email' => 'sarah.j@company.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Michael Brown',
                'username' => 'michael.b',
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'email' => 'michael.b@company.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Emily Davis',
                'username' => 'emily.d',
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'email' => 'emily.d@company.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'Robert Wilson',
                'username' => 'robert.w',
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'email' => 'robert.w@company.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        // Insert users and get their IDs
        $userIds = [];
        foreach ($users as $user) {
            $userId = DB::table('users')->insertGetId($user);
            $userIds[] = $userId;
        }

        // Create employee records for each user
        $employees = [
            [
                'user_id' => $userIds[0],
                'department_id' => 1,
                'job_title' => 'Software Developer',
                'hire_date' => '2022-01-15',
                'salary' => 75000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userIds[1],
                'department_id' => 2,
                'job_title' => 'Sales Manager',
                'hire_date' => '2021-03-10',
                'salary' => 85000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userIds[2],
                'department_id' => 3,
                'job_title' => 'HR Specialist',
                'hire_date' => '2020-06-22',
                'salary' => 65000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userIds[3],
                'department_id' => 1,
                'job_title' => 'Senior Developer',
                'hire_date' => '2019-11-05',
                'salary' => 95000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userIds[4],
                'department_id' => 4,
                'job_title' => 'Marketing Coordinator',
                'hire_date' => '2022-08-30',
                'salary' => 55000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('employees')->insert($employees);

        $this->command->info('Successfully seeded 5 employees with their user accounts.');
    }
}