<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DashboardOverview
{
    public function data(): array
    {
        $lowStock = fn () => DB::table('inventories')->whereRaw('quantity <= COALESCE(min_quantity, 10)');
        $progress = DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->whereColumn('project_phases.project_id', 'projects.project_id')->selectRaw('AVG(tasks.progress_percentage)');

        return [
            'metrics' => [
                'sales' => DB::table('sales_orders')->where('status', 'delivered')->sum('grand_total'),
                'orders' => DB::table('sales_orders')->whereIn('status', ['draft', 'confirmed', 'processing', 'shipped'])->count(),
                'projects' => DB::table('projects')->where('status', 'in_progress')->count(),
                'employees' => DB::table('employees')->where('status', 'active')->count(),
                'low_stock' => $lowStock()->count(),
                'tickets' => DB::table('tickets')->whereIn('status', ['open', 'in_progress'])->count(),
                'approvals' => DB::table('purchase_requisitions')->where('status', 'submitted')->count(),
            ],
            'orders' => DB::table('sales_orders')->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
                ->select('sales_orders.*', 'customers.first_name', 'customers.last_name')->latest('sales_orders.created_at')->limit(6)->get(),
            'projects' => DB::table('projects')->select('projects.*')->selectSub($progress, 'avg_progress')->latest('created_at')->limit(4)->get(),
            'lowStock' => $lowStock()->orderBy('quantity')->limit(4)->get(),
        ];
    }
}
