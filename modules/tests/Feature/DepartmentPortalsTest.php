<?php
namespace Tests\Feature;

use App\Models\User;
use App\Livewire\DepartmentLogin;
use App\Services\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DepartmentPortalsTest extends TestCase
{
    use RefreshDatabase;
    private function staff(string $module, array $abilities = ['read']): User
    {
        $user = User::factory()->withoutTwoFactor()->create(['role' => 'employee']);
        DB::table('employees')->insert(['user_id' => $user->getKey(), 'job_title' => 'Department staff', 'hire_date' => '2026-01-01', 'status' => 'active']);
        DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => json_encode([$module => $abilities])]);
        return $user->fresh();
    }
    public function test_all_departments_have_public_logins_and_protected_independent_homes(): void
    {
        $this->get('/portals')->assertOk()->assertSee('Choose your portal');
        foreach (config('portals') as $module => $portal) {
            $this->get('/'.$portal['path'].'/login')->assertOk()->assertSee($portal['label']);
            $this->get(route($portal['home']))->assertRedirect(route('portals.'.$module.'.login'));
        }
        $this->get('/project')->assertRedirect(route('projects.home'));
    }
    public function test_employee_can_sign_into_assigned_department_without_admin_role(): void
    {
        $user = $this->staff('projects', ['read', 'write']);
        Livewire::test(DepartmentLogin::class, ['department' => 'projects'])->set('username', $user->email)->set('password', 'password')->call('login')->assertHasNoErrors()->assertRedirect(route('projects.home'));
        $this->assertAuthenticatedAs($user);
        $this->get('/projects')->assertOk()->assertSee('Projects portal')->assertSee('data-portal="projects"', false)->assertDontSee('class="erp-sidebar"', false)->assertDontSee('href="'.route('finance.home').'"', false);
        $this->get('/admin')->assertForbidden(); $this->get('/finance')->assertForbidden(); $this->get('/hr')->assertForbidden(); $this->get('/admin/access')->assertForbidden();
        $this->get('/admin/projects')->assertRedirect(route('projects.home'));
        $this->get('/portals')->assertOk()->assertSee('href="'.route('projects.home').'"', false)->assertDontSee('href="'.route('finance.home').'"', false)->assertDontSee('href="'.route('applicant.login').'"', false);
    }
    public function test_unassigned_customers_applicants_and_inactive_staff_cannot_enter_departments(): void
    {
        $staff = $this->staff('projects');
        Livewire::test(DepartmentLogin::class, ['department' => 'finance'])->set('username', $staff->email)->set('password', 'password')->call('login')->assertHasErrors('username'); $this->assertGuest();
        DB::table('employees')->where('user_id', $staff->getKey())->update(['status' => 'inactive']);
        Livewire::test(DepartmentLogin::class, ['department' => 'projects'])->set('username', $staff->email)->set('password', 'password')->call('login')->assertHasErrors('username');
        $this->actingAs($staff)->get('/projects')->assertForbidden(); $this->get('/applicant')->assertOk();
        $customer = User::factory()->withoutTwoFactor()->create(['role' => 'customer']); DB::table('users')->where('user_id', $customer->getKey())->update(['module_permissions' => '{"projects":["read"]}']);
        $this->actingAs($customer)->get('/projects')->assertForbidden();
    }
    public function test_all_department_boundaries_apply_to_active_staff(): void
    {
        $user = $this->staff('projects'); $this->actingAs($user);
        foreach (config('portals') as $module => $portal) {
            DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => json_encode([$module => ['read']])]);
            $this->assertSame(200, $this->get(route($portal['home']))->getStatusCode(), $module.' department home must be accessible');
            $other = $module === 'finance' ? 'projects' : 'finance';
            $this->get(route(config('portals.'.$other.'.home')))->assertForbidden();
        }
    }
    public function test_revocation_blocks_mounted_livewire_components_and_suspended_employment(): void
    {
        $user = $this->staff('projects', ['read', 'write']); $this->actingAs($user);
        $component = Volt::test('projects.home');
        DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => '{}']);
        $component->call('loadProjectData')->assertForbidden(); $this->get('/projects')->assertForbidden();
        DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => '{"projects":["read","write"]}']);
        $component = Volt::test('projects.home'); DB::table('employees')->where('user_id', $user->getKey())->update(['status' => 'inactive']);
        $component->call('loadProjectData')->assertForbidden();
    }
    public function test_active_employees_and_applicants_have_separate_personal_portals(): void
    {
        $user = $this->staff('projects'); $this->actingAs($user)->get('/applicant')->assertForbidden(); Volt::test('applicant.index')->assertForbidden();
        Auth::logout();
        Volt::test('auth.applicantlogin')->set('email', $user->email)->set('password', 'password')->call('login')->assertHasErrors('email');
        $this->assertGuest(); $this->actingAs($user)->get('/employee/dashboard')->assertOk();
    }
    public function test_restricted_admin_uses_department_login_and_cannot_open_global_admin(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['role' => 'admin']); DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => '{"projects":["read"],"dashboard":["read"]}']);
        $this->actingAs($user)->get('/admin')->assertForbidden(); Auth::logout();
        Volt::test('auth.adminlogin')->set('username', $user->email)->set('password', 'password')->call('login')->assertHasErrors('username');
        Livewire::test(DepartmentLogin::class, ['department' => 'projects'])->set('username', $user->email)->set('password', 'password')->call('login')->assertRedirect(route('projects.home'));
        $this->post(route('department.logout'), ['department' => 'projects'])->assertRedirect(route('portals.projects.login')); $this->assertGuest();
    }
    public function test_super_admin_can_assign_staff_departments_but_staff_cannot_expand_access(): void
    {
        $root = User::factory()->withoutTwoFactor()->create(['role' => 'admin']); $user = $this->staff('projects');
        app(AccessControl::class)->configure($root, $user->getKey(), ['finance' => ['read', 'write', 'approve']]);
        $this->assertTrue(app(AccessControl::class)->allows($user, 'finance', 'approve')); $this->assertFalse(app(AccessControl::class)->allows($user, 'projects'));
        $this->actingAs($user); Livewire::test(\App\Livewire\AccessManagement::class)->assertForbidden();
        try { app(AccessControl::class)->configure($root, $user->getKey(), null); $this->fail('Staff must not receive full access'); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(422, $e->getStatusCode()); }
        $this->actingAs($root)->get('/admin')->assertOk(); foreach (config('portals') as $module => $portal) $this->assertSame(200, $this->get(route($portal['home']))->getStatusCode(), $module.' department home must be accessible');
    }
    public function test_department_login_preserves_two_factor_and_rechecks_permissions_after_challenge(): void
    {
        $user = $this->staff('projects'); $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['portal-recovery']))])->save();
        Livewire::test(DepartmentLogin::class, ['department' => 'projects'])->set('username', $user->email)->set('password', 'password')->call('login')->assertRedirect(route('two-factor.login')); $this->assertGuest();
        DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => '{}']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'portal-recovery'])->assertRedirect(route('projects.home'));
        $this->get('/projects')->assertForbidden();
    }
    public function test_project_portal_links_tasks_through_their_real_project_phase(): void
    {
        $u = $this->staff('projects'); $this->actingAs($u);
        $employee = DB::table('employees')->where('user_id', $u->getKey())->value('employee_id');
        $project = DB::table('projects')->insertGetId(['project_id' => 100, 'project_name' => 'Portal project', 'project_manager_id' => $employee, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $phase = DB::table('project_phases')->insertGetId(['phase_id' => 200, 'project_id' => $project, 'phase_name' => 'Real phase', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        DB::table('tasks')->insert(['phase_id' => $phase, 'task_name' => 'Correctly linked task', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'status' => 'in_progress', 'progress_percentage' => 60]);
        Volt::test('projects.home')->assertSet('projects.0.total_tasks', 1)->assertSet('projects.0.avg_progress', 60)->assertSet('recentTasks.0.project_name', 'Portal project');
    }
    public function test_store_staff_cannot_view_or_replace_finance_account_mappings(): void
    {
        $u = $this->staff('commerce', ['read', 'write']); $this->actingAs($u);
        $account = DB::table('chart_of_accounts')->insertGetId(['account_code' => 'PRIVATE-BANK', 'account_name' => 'Private Finance bank account', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);
        $component = Livewire::test(\App\Livewire\CommerceSettings::class)->assertDontSee('Payment ledger mapping')->assertDontSee('Private Finance bank account')->assertSet('cashAccount', '');
        $component->set('cashAccount', (string) $account)->call('save')->assertForbidden();
    }
    public function test_terminated_staff_do_not_become_applicants_and_supplier_login_clears_web_identity(): void
    {
        $u = $this->staff('projects'); DB::table('employees')->where('user_id', $u->getKey())->update(['status' => 'terminated']);
        $this->actingAs($u)->get('/applicant')->assertForbidden(); $this->get('/projects')->assertForbidden();
        DB::table('suppliers')->insert(['name' => 'Session supplier', 'email' => 'session-supplier@example.test', 'status' => 'active', 'password' => \Illuminate\Support\Facades\Hash::make('supplier-test-only')]);
        Volt::test('auth.supplier')->set('email', 'session-supplier@example.test')->set('password', 'supplier-test-only')->call('login')->assertRedirect(route('supplier.dashboard'));
        $this->assertGuest(); $this->get('/projects')->assertRedirect(route('portals.projects.login'));
    }
}
