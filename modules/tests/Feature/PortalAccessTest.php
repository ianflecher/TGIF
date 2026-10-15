<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Database\Seeders\AdminSeeder;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_internal_modules(): void
    {
        foreach (['/admin', '/admin/hr', '/finance/accounts', '/inventory/stock', '/projects/projects', '/gantt-tasks/1'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->get('/employee/dashboard')->assertRedirect(route('employee.login'));
        $this->get('/supplier')->assertRedirect(route('supplier.login'));
    }

    public function test_customers_cannot_open_admin_or_employee_modules(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        $this->get('/admin/hr')->assertForbidden();
        $this->get('/finance/accounts')->assertForbidden();
        $this->get('/employee/payroll')->assertForbidden();
    }

    public function test_inactive_applicants_cannot_open_employee_modules(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'employee']));
        $this->get('/employee/payroll')->assertForbidden();
    }

    public function test_missing_project_returns_404_for_admin(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        $this->get('/gantt-tasks/999')->assertNotFound();
    }

    public function test_supplier_shared_password_is_rejected_and_assigned_password_works(): void
    {
        DB::table('suppliers')->insert([
            'name' => 'Test Supplier', 'email' => 'supplier@example.com',
            'status' => 'active', 'password' => Hash::make('a-unique-supplier-password'),
        ]);
        Volt::test('auth.supplier')
            ->set('email', 'supplier@example.com')->set('password', 'supplier123')
            ->call('login')->assertHasErrors('password');
        $this->assertNull(session('supplier'));

        Volt::test('auth.supplier')
            ->set('email', 'supplier@example.com')->set('password', 'a-unique-supplier-password')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('supplier.dashboard'));
        $this->assertSame('Test Supplier', session('supplier')->name);
        $this->assertFalse(property_exists(session('supplier'), 'password'));
    }

    public function test_demo_seeder_preserves_existing_employees_and_passwords(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['username' => 'admin', 'role' => 'admin']);
        DB::table('employees')->insert([
            'user_id' => $user->getKey(), 'job_title' => 'Existing Administrator',
            'hire_date' => '2026-01-01', 'status' => 'active',
        ]);
        $originalPassword = $user->password;
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->assertDatabaseHas('employees', ['user_id' => $user->getKey(), 'job_title' => 'Existing Administrator']);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame(11, DB::table('users')->where('role', 'admin')->count());
    }
}
