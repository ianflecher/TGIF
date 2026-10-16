<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use App\Livewire\Storefront;
use App\Livewire\StorefrontCartPage;



// ====================
// PUBLIC ROUTES
// ====================
Route::get('/application-documents/{document}', \App\Http\Controllers\ApplicantDocumentController::class)->whereNumber('document')->middleware('auth')->name('application.documents.download');

Route::view('/portals', 'portals')->name('portals.index');
foreach (config('portals') as $department => $portal) {
    Route::get('/'.$portal['path'].'/login', \App\Livewire\DepartmentLogin::class)->defaults('department', $department)->name('portals.'.$department.'.login');
}
Route::get('/project', fn () => redirect()->route('projects.home'));
foreach (['projects' => 'projects', 'finance' => 'finance', 'inventory' => 'inventory', 'procurement' => 'procurement', 'hr' => 'hr', 'sales' => 'sales', 'ecommerce' => 'ecommerce', 'supplychain' => 'supplychain', 'customerservice' => 'customerservice', 'reporting' => 'reports', 'storefront-settings' => 'commerce'] as $old => $department) {
    Route::get('/admin/'.$old, fn () => redirect()->route(config('portals.'.$department.'.home')))->middleware('portal:admin')->name($department.'.legacy-home');
}
Route::post('/department/logout', \App\Livewire\Actions\LogoutDepartment::class)->name('department.logout');

// Landing Page
Route::get('/welcome', fn () => redirect()->route('landing'))->name('home');
Route::get('/', Storefront::class)->name('landing');

// Authentication Routes
Volt::route('/login', 'auth.login')->name('login');
Volt::route('/admin/login', 'auth.adminlogin')->name('admin.login');
Volt::route('/employee/login', 'auth.employeelogin')->name('employee.login');
Volt::route('/applicant/login', 'auth.applicantlogin')->name('applicant.login');
Volt::route('/supplier/login', 'auth.supplier')->name('supplier.login');
Volt::route('/register', 'auth.register')->name('register');

Route::get('/home', fn () => redirect()->route(app(\App\Services\AccessControl::class)->home(auth()->user())))->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Volt::route('/settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('/settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('/settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('/settings/two-factor', 'settings.two-factor')->middleware('password.confirm')->name('two-factor.show');
});

// Public Product Pages
Route::get('/products', Storefront::class)->defaults('showHero', false)->name('products.index');
Route::get('/products/{product}', function (int $product) {
    abort_unless(DB::table('products')->where('product_id', $product)->where('status', 'published')->exists(), 404);
    return redirect()->route('products.index', ['product' => $product]);
})->name('products.show');

// ====================
// CUSTOMER ROUTES
// ====================

// Customer Dashboard
Volt::route('/dashboard', 'customer.dashboard')->middleware('portal:customer')->name('customer.dashboard');

// Customer Profile
Volt::route('/profile', 'customer.account.profile')->middleware('portal:customer')->name('customer.account.profile');
Route::get('/profile/edit', fn () => redirect()->route('customer.account.profile'))->middleware('portal:customer')->name('customer.profile.edit');

// Customer Orders
Route::get('/orders', \App\Livewire\CustomerOrders::class)->middleware('portal:customer')->name('customer.orders.index');
Route::get('/orders/{order}', \App\Livewire\CustomerOrder::class)->middleware('portal:customer')->name('customer.orders.show');
Route::get('/orders/{order}/receipts/{payment}', \App\Http\Controllers\OrderReceiptController::class)->middleware('portal:customer')->name('customer.orders.receipt');
Route::get('/admin/orders/{order}/receipts/{payment}', \App\Http\Controllers\OrderReceiptController::class)->middleware('portal:admin')->name('admin.orders.receipt');
Route::get('/finance/payments', \App\Livewire\OrderPaymentDesk::class)->middleware('portal:admin')->name('finance.payments');
Route::get('/finance/supplier-payables', \App\Livewire\SupplierInvoiceDesk::class)->middleware('portal:admin')->name('finance.supplier-payables');
Route::get('/supplier/invoices', \App\Livewire\SupplierInvoicePortal::class)->middleware('portal:supplier')->name('supplier.invoices');
Route::get('/finance/controls', \App\Livewire\AccountingControls::class)->middleware('portal:admin')->name('finance.controls');
Route::get('/admin/access', \App\Livewire\AccessManagement::class)->middleware('portal:admin')->name('access.management');
Route::get('/ecommerce/adjustments', \App\Livewire\OrderAdjustmentDesk::class)->middleware('portal:admin')->name('ecommerce.adjustments');
Route::get('/commerce', \App\Livewire\CommerceSettings::class)->middleware('portal:admin')->name('commerce.settings');

// Customer Cart & Checkout
Route::get('/cart', StorefrontCartPage::class)->middleware('portal:customer')->name('customer.cart.index');
Volt::route('/checkout', 'customer.checkout')->middleware('portal:customer')->name('customer.checkout');

// Customer Support
Volt::route('/support', 'customer.support.index')->middleware('portal:customer')->name('customer.support.index');
Route::get('/support/tickets', fn () => redirect()->route('customer.support.index'))->middleware('portal:customer')->name('customer.support.tickets');
Route::get('/support/tickets/create', fn () => redirect()->route('customer.support.index'))->middleware('portal:customer')->name('customer.support.ticket-create');

// Note: Removed the duplicate products route
Route::get('customer/products', Storefront::class)->defaults('showHero', false)->middleware('portal:customer')->name('customer.products.index');

// ====================
// ADMIN ROUTES
// ====================

// Admin Dashboard
Volt::route('/admin', 'dashboard')->middleware('portal:admin')->name('admin.dashboard');

// Admin Products
Route::get('/admin/products', fn () => redirect()->route('ecommerce.products'))->middleware('portal:admin')->name('admin.products.index');
Route::get('/admin/products/create', fn () => redirect()->route('admin.dashboard'))->middleware('portal:admin')->name('admin.products.create');
Route::get('/admin/products/{product}/edit', fn () => redirect()->route('admin.dashboard'))->middleware('portal:admin')->name('admin.products.edit');

// Admin Orders
Route::get('/admin/orders', fn () => redirect()->route('ecommerce.orders'))->middleware('portal:admin')->name('admin.orders.index');
Route::get('/admin/orders/{order}', fn () => redirect()->route('admin.dashboard'))->middleware('portal:admin')->name('admin.orders.show');

// Admin Customers
Route::get('/admin/customers', fn () => redirect()->route('admin.dashboard'))->middleware('portal:admin')->name('admin.customers.index');
Route::get('/admin/customers/{customer}', fn () => redirect()->route('admin.dashboard'))->middleware('portal:admin')->name('admin.customers.show');

// Admin Reports
Route::get('/admin/reports', fn () => redirect()->route('reports.home'))->middleware('portal:admin')->name('admin.reports.index');

// Admin Settings
Route::get('/admin/settings', fn () => redirect()->route('commerce.settings'))->middleware('portal:admin')->name('admin.settings.index');

Volt::route('/projects', 'projects.home')->middleware('portal:admin')->name('projects.home');

Volt::route('/sales', 'sales.home')->middleware('portal:admin')->name('sales.home');
Volt::route('/sales/ticket', 'sales.ticket')->middleware('portal:admin')->name('sales.ticket');

Volt::route('/inventory', 'inventory.home')->middleware('portal:admin')->name('inventory.home');
Volt::route('/inventory/tracking', 'inventory.tracking')->middleware('portal:admin')->name('inventory.tracking');
Volt::route('/inventory/stock', 'inventory.stock')->middleware('portal:admin')->name('inventory.stock');
Volt::route('/inventory/receiving', 'employee.warehouse')->middleware('portal:admin')->name('inventory.receiving');

Volt::route('/customerservice', 'customerservice.home')->middleware('portal:admin')->name('customerservice.home');

Volt::route('/hr', 'hr.home')->middleware('portal:admin')->name('hr.home');
Volt::route('/hr/applications', 'hr.applications')->middleware('portal:admin')->name('hr.applications');
Volt::route('/hr/attendance', 'hr.attendance')->middleware('portal:admin')->name('hr.attendance');
Volt::route('/hr/leave', 'hr.leave')->middleware('portal:admin')->name('hr.leave');
Volt::route('/hr/payroll', 'hr.payroll')->middleware('portal:admin')->name('hr.payroll');


Volt::route('/procurement', 'procurement.home')->middleware('portal:admin')->name('procurement.home');


Volt::route('/supplychain', 'supplychain.home')->middleware('portal:admin')->name('supplychain.home');
Volt::route('/supplychain/logistic', 'supplychain.logistic')->middleware('portal:admin')->name('supplychain.logistic');
Volt::route('/supplychain/tracking', 'supplychain.tracking')->middleware('portal:admin')->name('supplychain.tracking');

Volt::route('/finance', 'finance.home')->middleware('portal:admin')->name('finance.home');
Volt::route('/finance/accounts', 'finance.accounts')->middleware('portal:admin')->name('finance.accounts');
Volt::route('/finance/invoice', 'finance.invoice')->middleware('portal:admin')->name('finance.invoice');
Volt::route('/finance/approval', 'finance.approval')->middleware('portal:admin')->name('finance.approval');

Volt::route('/ecommerce', 'ecommerce.home')->middleware('portal:admin')->name('ecommerce.home');

Volt::route('/reports', 'reports.home')->middleware('portal:admin')->name('reports.home');
Volt::route('/reports/summary', 'reports.summary')->middleware('portal:admin')->name('reports.summary');

Volt::route('/employee/dashboard', 'employee.index')->middleware('portal:employee')->name('employee.dashboard');


Volt::route('/employee/tasks', 'employee.tasks')->middleware('portal:employee')->name('employee.tasks');
Volt::route('/employee/attendance', 'employee.attendance')->middleware('portal:employee')->name('employee.attendance');
Route::get('/employee/schedule', fn () => redirect()->route('employee.tasks'))->middleware('portal:employee')->name('employee.schedule');
Volt::route('/employee/payroll', 'employee.payroll')->middleware('portal:employee')->name('employee.payroll');
Volt::route('/employee/leave', 'employee.leave')->middleware('portal:employee')->name('employee.leave');
Volt::route('/employee/support', 'employee.support')->middleware('portal:employee')->name('employee.support');
Volt::route('/employee/warehouse', 'employee.warehouse')->middleware('portal:employee')->name('employee.warehouse');

Volt::route('/applicant', 'applicant.index')->middleware('portal:applicant')->name('applicant.index');

Volt::route('/projects/budget', 'projects.budget')->middleware('portal:admin')->name('projects.budget');

Volt::route('/projects/projects', 'projects.projects')->middleware('portal:admin')->name('projects.projects');

Volt::route('/projects/resources', 'projects.resources')->middleware('portal:admin')->name('projects.resources');

Volt::route('/projects/members/{project_id}', 'projects.members')->middleware('portal:admin')->name('projects.members');

Volt::route('/projects/phase/{project_id}', 'projects.phase')->middleware('portal:admin')->name('projects.phase');

Volt::route('/projects/task/{phase_id}', 'projects.tasks')->middleware('portal:admin')->name('projects.task');

Volt::route('/projects/addbudget/{phase_id}', 'projects.addbudget')->middleware('portal:admin')->name('projects.addbudget');

Volt::route('/projects/viewresources', 'projects.viewresources')->middleware('portal:admin')->name('projects.viewresources');

Volt::route('/projects/cost/{task}', 'projects.cost')->middleware('portal:admin')->name('projects.cost');


Volt::route('/ecommerce/orders', 'ecommerce.orders')->middleware('portal:admin')->name('ecommerce.orders');

Volt::route('/ecommerce/products', 'ecommerce.products')->middleware('portal:admin')->name('ecommerce.products');

Volt::route('/supplier', 'supplier.dashboard')->middleware('portal:supplier')->name('supplier.dashboard');

Volt::route('/supplier/list', 'supplier.list')->middleware('portal:admin')->name('supplier.list');

Volt::route('/procurement/create', 'procurement.create')->middleware('portal:admin')->name('procurement.create');

Volt::route('/procurement/status', 'procurement.status')->middleware('portal:admin')->name('procurement.status');

Volt::route('/procurement/createpo', 'procurement.createpo')->middleware('portal:admin')->name('procurement.createpo');



// ====================
// FALLBACK ROUTE
// ====================
use Carbon\Carbon;

Route::get('/gantt-tasks/{projectId}', function ($projectId) {
    $project = DB::table('projects')->where('project_id', $projectId)->first();
    abort_if(!$project, 404);

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
                : ($tasks->min(fn($t) => Carbon::createFromFormat('d-m-Y', $t['start_date']))?->format('d-m-Y') ?? null);

            $phaseEnd = $phase->end_date 
                ? Carbon::parse($phase->end_date)->format('d-m-Y') 
                : ($tasks->max(fn($t) => Carbon::createFromFormat('d-m-Y', $t['end_date']))?->format('d-m-Y') ?? null);

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
        : ($phases->min(fn($p) => $p['start_date'] ? Carbon::createFromFormat('d-m-Y', $p['start_date']) : null)?->format('d-m-Y') ?? null);

    $projectEnd = $project->end_date 
        ? Carbon::parse($project->end_date)->format('d-m-Y') 
        : ($phases->max(fn($p) => $p['end_date'] ? Carbon::createFromFormat('d-m-Y', $p['end_date']) : null)?->format('d-m-Y') ?? null);

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

    return response()->json($ganttData);
})->middleware('portal:admin')->name('projects.gantt');

// In routes/web.php
use App\Livewire\Actions\LogoutApplicant;
use App\Livewire\Actions\LogoutAdmin;
use App\Livewire\Actions\LogoutEmployee;
use App\Livewire\Actions\LogoutSupplier;

// Use standard Laravel route for POST
Route::post('/applicant/logout', LogoutApplicant::class)->name('applicant.logout');
Route::post('/admin/logout', LogoutAdmin::class)->name('admin.logout');
Route::post('/employee/logout', LogoutEmployee::class)->name('employee.logout');
Route::post('/supplier/logout', LogoutSupplier::class)->name('supplier.logout');

Route::fallback(function () {
    return redirect()->route('landing');
});
