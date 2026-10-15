<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_admin_and_customer_pages_render_on_an_empty_database(): void
    {
        $failures = [];
        $checked = 0;
        foreach (['admin', 'customer', 'employee', 'applicant', 'supplier'] as $role) {
            $user = User::factory()->withoutTwoFactor()->create(['role' => in_array($role, ['applicant', 'supplier']) ? 'employee' : $role]);
            if ($role === 'employee') \Illuminate\Support\Facades\DB::table('employees')->insert(['user_id' => $user->getKey(), 'job_title' => 'Test employee', 'hire_date' => '2026-01-01', 'status' => 'active']);
            if ($role === 'employee') \Illuminate\Support\Facades\DB::table('users')->where('user_id', $user->getKey())->update(['module_permissions' => json_encode(['inventory' => ['read', 'write']])]);
            if ($role === 'supplier') {
                $id = \Illuminate\Support\Facades\DB::table('suppliers')->insertGetId(['name' => 'Test supplier', 'email' => 'smoke@example.test', 'status' => 'active']);
                $this->withSession(['supplier' => \Illuminate\Support\Facades\DB::table('suppliers')->where('supplier_id', $id)->first()]);
            }
            foreach (Route::getRoutes() as $route) {
                if (!in_array('GET', $route->methods()) || str_contains($route->uri(), '{') || !in_array('portal:'.$role, $route->gatherMiddleware())) continue;
                // Volt can replace the redirect binding while rendering a page.
                // Restore the normal HTTP binding between independent requests.
                app()->instance('redirect', new \Illuminate\Routing\Redirector(app('url')));
                app('redirect')->setSession(app('session.store'));
                $this->actingAs($user);
                $response = $this->get('/'.$route->uri());
                $checked++;
                if (!in_array($response->status(), [200, 302])) $failures[] = $role.' /'.$route->uri().' returned '.$response->status().' '.substr($response->exception?->getMessage() ?? '', 0, 300);
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
        $this->assertGreaterThan(45, $checked);
    }
}
