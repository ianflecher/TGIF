<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProvisionDepartmentDemos extends Command
{
    protected $signature = 'portals:provision-demo';
    protected $description = 'Create or restrict local demo department accounts, preserving existing passwords';

    public function handle(): int
    {
        if (!app()->environment('local', 'testing')) {
            $this->error('Demo accounts are available only in local or testing environments.');
            return self::FAILURE;
        }

        DB::transaction(function () {
            foreach (config('portals') as $module => $portal) {
                $username = match ($module) { 'projects' => 'project', 'reports' => 'businessintelligence', default => $module };
                $email = $username.'@tgif.local';
                $user = DB::table('users')->where('username', $username)->first();
                if ($user && ($user->email !== $email || $user->deleted_at !== null)) {
                    throw new \RuntimeException('Account collision for '.$username.'. No accounts were changed.');
                }
                $values = ['role' => 'manager', 'module_permissions' => json_encode([$module => ['read', 'write', 'approve']]), 'updated_at' => now()];
                if ($user) {
                    $id = $user->user_id;
                    DB::table('users')->where('user_id', $id)->update($values);
                } else {
                    $id = DB::table('users')->insertGetId($values + ['username' => $username, 'email' => $email, 'full_name' => $portal['label'].' Manager', 'password' => Hash::make('password'), 'created_at' => now()]);
                }
                $employee = DB::table('employees')->where('user_id', $id)->first();
                if ($employee && $employee->status !== 'active') {
                    throw new \RuntimeException('Inactive demo employee '.$username.'. No accounts were changed.');
                }
                if (!$employee) {
                    DB::table('employees')->insert(['user_id' => $id, 'job_title' => $portal['label'].' Manager', 'hire_date' => now()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
        $this->info('Department demo accounts provisioned. Existing passwords preserved; new accounts use password. Main administrator unchanged.');
        return self::SUCCESS;
    }
}
