<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;


Volt::route('/', 'dashboard')->name('dashboard');

// Project Management Module
Volt::route('/projects', 'projects.home')->name('projects.home');
Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');
Volt::route('/projects/progress', 'projects.progress')->name('projects.progress');

// Inventory & Warehouse Module
Volt::route('/inventory', 'inventory.home')->name('inventory.home');
Volt::route('/inventory/stock', 'inventory.stock')->name('inventory.stock');
Volt::route('/inventory/warehouse', 'inventory.warehouse')->name('inventory.warehouse');
Volt::route('/inventory/tracking', 'inventory.tracking')->name('inventory.tracking');

// Customer Service Module
Volt::route('/customer-service', 'customerservice.home')->name('customerservice.home');
Volt::route('/customer-service/tickets', 'customerservice.tickets')->name('customerservice.tickets');
Volt::route('/customer-service/chat', 'customerservice.chat')->name('customerservice.chat');
Volt::route('/customer-service/feedback', 'customerservice.feedback')->name('customerservice.feedback');

// Procurement Module
Volt::route('/procurement', 'procurement.home')->name('procurement.home');
Volt::route('/procurement/purchase-orders', 'procurement.purchase-orders')->name('procurement.purchase-orders');
Volt::route('/procurement/vendors', 'procurement.vendors')->name('procurement.vendors');
Volt::route('/procurement/contracts', 'procurement.contracts')->name('procurement.contracts');

// Supply Chain Module
Volt::route('/supply-chain', 'supplychain.home')->name('supplychain.home');
Volt::route('/supply-chain/logistics', 'supplychain.logistics')->name('supplychain.logistics');
Volt::route('/supply-chain/suppliers', 'supplychain.suppliers')->name('supplychain.suppliers');
Volt::route('/supply-chain/transport', 'supplychain.transport')->name('supplychain.transport');

// Finance & Accounting Module
Volt::route('/finance', 'finance.home')->name('finance.home');
Volt::route('/finance/accounts', 'finance.accounts')->name('finance.accounts');
Volt::route('/finance/invoicing', 'finance.invoicing')->name('finance.invoicing');
Volt::route('/finance/payroll', 'finance.payroll')->name('finance.payroll');
Volt::route('/finance/reports', 'finance.reports')->name('finance.reports');

// E-Commerce Module
Volt::route('/e-commerce', 'ecommerce.home')->name('ecommerce.home');
Volt::route('/e-commerce/products', 'ecommerce.products')->name('ecommerce.products');
Volt::route('/e-commerce/orders', 'ecommerce.orders')->name('ecommerce.orders');
Volt::route('/e-commerce/storefront', 'ecommerce.storefront')->name('ecommerce.storefront');

// Reports & Business Intelligence Module
Volt::route('/reports', 'reports.home')->name('reports.home');
Volt::route('/reports/analytics', 'reports.analytics')->name('reports.analytics');
Volt::route('/reports/dashboards', 'reports.dashboards')->name('reports.dashboards');
Volt::route('/reports/exports', 'reports.exports')->name('reports.exports');

// Sales & CRM Module
Volt::route('/sales', 'sales.home')->name('sales.home');
Volt::route('/sales/leads', 'sales.leads')->name('sales.leads');
Volt::route('/sales/contacts', 'sales.contacts')->name('sales.contacts');
Volt::route('/sales/deals', 'sales.deals')->name('sales.deals');
Volt::route('/sales/pipeline', 'sales.pipeline')->name('sales.pipeline');

// Human Resources Module
Volt::route('/hr', 'hr.home')->name('hr.home');
Volt::route('/hr/employees', 'hr.employees')->name('hr.employees');
Volt::route('/hr/recruitment', 'hr.recruitment')->name('hr.recruitment');
Volt::route('/hr/attendance', 'hr.attendance')->name('hr.attendance');
Volt::route('/hr/payroll', 'hr.payroll')->name('hr.payroll');

// You might also want to create a fallback route for 404 pages
Route::fallback(function () {
    return redirect()->route('dashboard');
});