<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepartmentDemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_idempotent_and_scoped_without_changing_admin(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'role' => 'admin']);
        $before = DB::table('users')->where('user_id', $admin->getKey())->first();
        $this->artisan('portals:provision-demo')->assertSuccessful();
        $password = DB::table('users')->where('username', 'project')->value('password');
        $this->artisan('portals:provision-demo')->assertSuccessful();
        $this->assertSame($password, DB::table('users')->where('username', 'project')->value('password'));
        $this->assertEquals($before, DB::table('users')->where('user_id', $admin->getKey())->first());
        $this->assertSame(12, User::count());
        foreach (config('portals') as $module => $portal) {
            $username = match ($module) { 'projects' => 'project', 'reports' => 'businessintelligence', default => $module };
            $user = User::where('username', $username)->firstOrFail();
            $access = app(AccessControl::class);
            $this->assertTrue($access->allows($user, $module));
            $this->assertFalse($access->isSuperAdmin($user));
            $this->assertFalse($access->allows($user, $module === 'finance' ? 'projects' : 'finance'));
        }
    }
}
