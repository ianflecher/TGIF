<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetSupplierPassword extends Command
{
    protected $signature = 'supplier:password {supplier : Supplier ID}';
    protected $description = 'Set a supplier password without exposing it in shell history';

    public function handle(): int
    {
        $supplier = DB::table('suppliers')->where('supplier_id', $this->argument('supplier'))->first();
        if (!$supplier) {
            $this->error('Supplier not found.');
            return self::FAILURE;
        }
        $password = $this->secret('New password (at least 12 characters)');
        if (!is_string($password) || strlen($password) < 12 || $password !== $this->secret('Confirm password')) {
            $this->error('Passwords must match and contain at least 12 characters.');
            return self::FAILURE;
        }
        DB::table('suppliers')->where('supplier_id', $supplier->supplier_id)->update([
            'password' => Hash::make($password), 'updated_at' => now(),
        ]);
        $this->info('Supplier password updated.');
        return self::SUCCESS;
    }
}
