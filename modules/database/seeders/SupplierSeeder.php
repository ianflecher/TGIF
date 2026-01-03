<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create just one supplier
        User::firstOrCreate(
            ['email' => 'supplier@tgif.com'],
            [
                'full_name' => 'TGIF Supplier',
                'username' => 'tgifsupplier',
                'password' => Hash::make('password'),
                'role' => 'supplier',
            ]
        );

        $this->command->info('Supplier account created/verified.');
    }
}