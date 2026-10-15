<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_renders_with_empty_business_tables(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Workspace overview')->assertSee('No orders yet');
    }

    public function test_replenishment_count_and_list_use_the_same_threshold(): void
    {
        DB::table('inventories')->insert([
            ['sku' => 'AT-MIN', 'product_name' => 'At minimum', 'quantity' => 20, 'min_quantity' => 20],
            ['sku' => 'ABOVE-MIN', 'product_name' => 'Above minimum', 'quantity' => 8, 'min_quantity' => 5],
        ]);
        $data = app(DashboardOverview::class)->data();
        $this->assertSame(1, $data['metrics']['low_stock']);
        $this->assertSame(['AT-MIN'], $data['lowStock']->pluck('sku')->all());
    }

    public function test_project_progress_uses_phase_relationship_not_matching_ids(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $employee = DB::table('employees')->insertGetId(['user_id' => $user->getKey(), 'job_title' => 'Manager', 'hire_date' => '2026-01-01']);
        $project = DB::table('projects')->insertGetId(['project_id' => 10, 'project_name' => 'Test project', 'project_manager_id' => $employee, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $phase = DB::table('project_phases')->insertGetId(['phase_id' => 20, 'project_id' => $project, 'phase_name' => 'Phase', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        DB::table('tasks')->insert([
            ['phase_id' => $phase, 'task_name' => 'Task one', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'status' => 'in_progress', 'progress_percentage' => 20],
            ['phase_id' => $phase, 'task_name' => 'Task two', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'status' => 'in_progress', 'progress_percentage' => 80],
        ]);
        $this->assertEquals(50, app(DashboardOverview::class)->data()['projects']->first()->avg_progress);
    }
}
