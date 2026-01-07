<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InventoryController;   // ← add this
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\ItemLocationController;
use App\Models\Inventory;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\InventoryHistory;
use App\Http\Controllers\PurchaseOrderController;

// ====================
// PUBLIC ROUTES
// ====================

// Landing Page
Volt::route('/', 'landingpage')->name('landing');

// Authentication Routes
Volt::route('/login', 'auth.login')->name('login');
Volt::route('/admin/login', 'auth.adminlogin')->name('admin.login');
Volt::route('/employee/login', 'auth.employeelogin')->name('employee.login');
Volt::route('/applicant/login', 'auth.applicantlogin')->name('applicant.login');
Volt::route('/supplier/login', 'auth.supplier')->name('supplier.login');
Volt::route('/register', 'auth.register')->name('register');
Volt::route('/forgot-password', 'auth.forgot-password')->name('password.request');
Volt::route('/reset-password/{token}', 'auth.reset-password')->name('password.reset');

// Public Product Pages
Volt::route('/products', 'products.index')->name('products.index');
Volt::route('/products/{product}', 'products.show')->name('products.show');

// ====================
// CUSTOMER ROUTES
// ====================

// Customer Dashboard
Volt::route('/dashboard', 'customer.dashboard')->name('customer.dashboard');

// Customer Profile
Volt::route('/profile', 'customer.account.profile')->name('customer.account.profile');
Volt::route('/profile/edit', 'customer.profile-edit')->name('customer.profile.edit');

// Customer Orders
Volt::route('/orders', 'customer.orders.index')->name('customer.orders.index');
Volt::route('/orders/{order}', 'customer.orders.show')->name('customer.orders.show');

// Customer Cart & Checkout
Volt::route('/cart', 'customer.cart.index')->name('customer.cart.index');
Volt::route('/checkout', 'customer.checkout')->name('customer.checkout');

// Customer Support
Volt::route('/support', 'customer.support.index')->name('customer.support.index');
Volt::route('/support/tickets', 'customer.support.tickets')->name('customer.support.tickets');
Volt::route('/support/tickets/create', 'customer.support.ticket-create')->name('customer.support.ticket-create');

// Note: Removed the duplicate products route
Volt::route('customer/products', 'customer.products.index')->name('customer.products.index');

// ====================
// ADMIN ROUTES
// ====================

// Admin Dashboard
Volt::route('/admin', 'dashboard')->name('admin.dashboard');

// Admin Products
Volt::route('/admin/products', 'admin.products.index')->name('admin.products.index');
Volt::route('/admin/products/create', 'admin.products.create')->name('admin.products.create');
Volt::route('/admin/products/{product}/edit', 'admin.products.edit')->name('admin.products.edit');

// Admin Orders
Volt::route('/admin/orders', 'admin.orders.index')->name('admin.orders.index');
Volt::route('/admin/orders/{order}', 'admin.orders.show')->name('admin.orders.show');

// Admin Customers
Volt::route('/admin/customers', 'admin.customers.index')->name('admin.customers.index');
Volt::route('/admin/customers/{customer}', 'admin.customers.show')->name('admin.customers.show');

// Admin Inventory
Volt::route('/admin/inventory', 'admin.inventory.index')->name('admin.inventory.index');

// Admin Reports
Volt::route('/admin/reports', 'admin.reports.index')->name('admin.reports.index');

// Admin Settings
Volt::route('/admin/settings', 'admin.settings.index')->name('admin.settings.index');

Volt::route('/admin/projects', 'projects.home')->name('projects.home');

Volt::route('/admin/sales', 'sales.home')->name('sales.home');

Volt::route('/admin/inventory', 'inventory.home')->name('inventory.home');

Volt::route('/admin/customerservice', 'customerservice.home')->name('customerservice.home');

Volt::route('/admin/hr', 'hr.home')->name('hr.home');
Volt::route('/hr/applications', 'hr.applications')->name('hr.applications');
Volt::route('/hr/attendance', 'hr.attendance')->name('hr.attendance');
Volt::route('/hr/leave', 'hr.leave')->name('hr.leave');
Volt::route('/hr/payroll', 'hr.payroll')->name('hr.payroll');


Volt::route('/admin/procurement', 'procurement.home')->name('procurement.home');


Volt::route('/admin/supplychain', 'supplychain.home')->name('supplychain.home');

Volt::route('/admin/finance', 'finance.home')->name('finance.home');

Volt::route('/admin/ecommerce', 'ecommerce.home')->name('ecommerce.home');

Volt::route('/admin/reports', 'reports.home')->name('reports.home');

Volt::route('/employee/dashboard', 'employee.index')->name('employee.dashboard');


Volt::route('/employee/tasks', 'employee.tasks')->name('employee.tasks');
Volt::route('/employee/attendance', 'employee.attendance')->name('employee.attendance');
Volt::route('/employee/schedule', 'employee.schedule')->name('employee.schedule');
Volt::route('/employee/payroll', 'employee.payroll')->name('employee.payroll');
Volt::route('/employee/leave', 'employee.leave')->name('employee.leave');

Volt::route('/applicant', 'applicant.index')->name('applicant.index');

Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');

Volt::route('/projects/projects', 'projects.projects')->name('projects.projects');

Volt::route('/projects/resources', 'projects.resources')->name('projects.resources');

Volt::route('/projects/members/{project_id}', 'projects.members')->name('projects.members');

Volt::route('/projects/phase/{project_id}', 'projects.phase')->name('projects.phase');

Volt::route('/projects/task/{phase_id}', 'projects.tasks')->name('projects.task');

Volt::route('/projects/addbudget/{phase_id}', 'projects.addbudget')->name('projects.addbudget');

Volt::route('/projects/viewresources', 'projects.viewresources')->name('projects.viewresources');

Volt::route('/projects/cost/{task}', 'projects.cost')->name('projects.cost');


Volt::route('/ecommerce/orders', 'ecommerce.orders')->name('ecommerce.orders');

Volt::route('/ecommerce/products', 'ecommerce.products')->name('ecommerce.products');

Volt::route('/supplier', 'supplier.dashboard')->name('supplier.dashboard');

Volt::route('/supplier/list', 'supplier.list')->name('supplier.list');

Volt::route('/procurement/create', 'procurement.create')->name('procurement.create');

Volt::route('/procurement/status', 'procurement.status')->name('procurement.status');

Volt::route('/procurement/createpo', 'procurement.createpo')->name('procurement.createpo');



// ====================
// FALLBACK ROUTE
// ====================
use Carbon\Carbon;

Route::get('/gantt-tasks/{projectId}', function ($projectId) {
    $project = DB::table('projects')->where('project_id', $projectId)->first();

    $phases = DB::table('project_phases')
        ->where('project_id', $projectId)
        ->get()
        ->map(function($phase) {
            $tasks = DB::table('tasks')
                ->where('phase_id', $phase->phase_id)
                ->get()
                ->map(function($t){
                    return [
                        'id' => 'task_'.$t->task_id,
                        'text' => $t->task_name,
                        'start_date' => Carbon::parse($t->start_date)->format('d-m-Y'),
                        'end_date' => Carbon::parse($t->end_date)->format('d-m-Y'),
                        'progress' => $t->progress_percentage / 100,
                        'parent' => 'phase_'.$t->phase_id
                    ];
                });

            // Use phase DB dates if available, otherwise compute from tasks
            $phaseStart = $phase->start_date 
                ? Carbon::parse($phase->start_date)->format('d-m-Y') 
                : ($tasks->min(fn($t) => Carbon::createFromFormat('d-m-Y', $t['start_date']))->format('d-m-Y') ?? null);

            $phaseEnd = $phase->end_date 
                ? Carbon::parse($phase->end_date)->format('d-m-Y') 
                : ($tasks->max(fn($t) => Carbon::createFromFormat('d-m-Y', $t['end_date']))->format('d-m-Y') ?? null);

            return [
                'id' => 'phase_'.$phase->phase_id,
                'text' => $phase->phase_name,
                'start_date' => $phaseStart,
                'end_date' => $phaseEnd,
                'open' => true,
                'parent' => 'project_'.$phase->project_id,
                'tasks' => $tasks
            ];
        });

    // Use project DB dates if available, otherwise compute from phases
    $projectStart = $project->start_date 
        ? Carbon::parse($project->start_date)->format('d-m-Y') 
        : ($phases->min(fn($p) => Carbon::createFromFormat('d-m-Y', $p['start_date']))->format('d-m-Y') ?? null);

    $projectEnd = $project->end_date 
        ? Carbon::parse($project->end_date)->format('d-m-Y') 
        : ($phases->max(fn($p) => Carbon::createFromFormat('d-m-Y', $p['end_date']))->format('d-m-Y') ?? null);

    $ganttData = [
        [
            'id' => 'project_'.$project->project_id,
            'text' => $project->project_name,
            'start_date' => $projectStart,
            'end_date' => $projectEnd,
            'open' => true
        ]
    ];

    // Flatten phases and tasks
    foreach($phases as $phase) {
        $ganttData[] = [
            'id' => $phase['id'],
            'text' => $phase['text'],
            'start_date' => $phase['start_date'],
            'end_date' => $phase['end_date'],
            'open' => true,
            'parent' => $phase['parent']
        ];

        foreach($phase['tasks'] as $task) {
            $ganttData[] = $task;
        }
    }

    \Log::info('Gantt data: ' . json_encode($ganttData));
    return response()->json($ganttData);
});

Route::fallback(function () {
    return redirect()->route('landing');
});