<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name', 150);
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->enum('role', ['admin', 'manager', 'employee', 'customer', 'supplier'])->default('employee');
            $table->string('email', 150)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index('role');
        });

        // Departments table
        Schema::create('departments', function (Blueprint $table) {
            $table->id('department_id');
            $table->string('department_name', 100);
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            
            $table->foreign('manager_id')->references('user_id')->on('users')->onDelete('set null');
        });

        // Employees table
        Schema::create('employees', function (Blueprint $table) {
            $table->id('employee_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('job_title', 100);
            $table->date('hire_date');
            $table->decimal('salary', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'terminated', 'on_leave'])->default('active');
            $table->timestamps();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('department_id')->on('departments')->onDelete('set null');
            $table->index(['status', 'department_id']);
        });

        // Customers table
        Schema::create('customers', function (Blueprint $table) {
            $table->id('customer_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 100)->unique();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->date('date_registered')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->timestamps();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['first_name', 'last_name']);
            $table->index('email');
        });

        // Suppliers table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id('supplier_id');
            $table->string('name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->decimal('rating', 4, 2)->nullable()->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            
            $table->index('name');
            $table->index('status');
        });

        // HR Attendance table
        Schema::create('hr_attendance', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'on_leave'])->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });

        // HR Payroll table
        Schema::create('hr_payroll', function (Blueprint $table) {
            $table->id('payroll_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'period_start', 'period_end']);
            $table->index(['status', 'period_end']);
        });

        // Inventories table
        Schema::create('inventories', function (Blueprint $table) {
            $table->id('inventory_id');
            $table->string('sku', 100)->unique();
            $table->string('product_name', 150);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('min_quantity')->default(10);
            $table->integer('max_quantity')->default(100);
            $table->string('warehouse', 100)->nullable();
            $table->string('zone', 50)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->timestamps();
            
            $table->index(['sku', 'product_name']);
            $table->index(['category', 'warehouse']);
            $table->index('status');
        });

        // Stock Transactions table
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->unsignedBigInteger('inventory_id');
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer', 'damage']);
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
            
            $table->foreign('inventory_id')->references('inventory_id')->on('inventories')->onDelete('cascade');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['type', 'transaction_date']);
        });

        // Purchase Requisitions table
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id('requisition_id');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('department_id');
            $table->date('date_requested');
            $table->date('required_date')->nullable();
            $table->text('justification')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'converted_to_po'])->default('draft');
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->timestamps();
            
            $table->foreign('requested_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('department_id')->on('departments')->onDelete('cascade');
            $table->index(['status', 'date_requested']);
        });

        // Purchase Orders table
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id('po_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('requisition_id')->nullable();
            $table->string('po_number')->unique();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'confirmed', 'partially_received', 'completed', 'cancelled'])->default('draft');
            $table->text('terms')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->onDelete('cascade');
            $table->foreign('requisition_id')->references('requisition_id')->on('purchase_requisitions')->onDelete('set null');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['po_number', 'status']);
            $table->index(['order_date', 'expected_delivery_date']);
        });

        // Goods Receipts table
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id('receipt_id');
            $table->unsignedBigInteger('po_id');
            $table->unsignedBigInteger('inventory_id');
            $table->integer('quantity_received');
            $table->date('date_received');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->enum('quality_status', ['passed', 'failed', 'pending'])->default('pending');
            $table->timestamps();
            
            $table->foreign('po_id')->references('po_id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('inventory_id')->references('inventory_id')->on('inventories')->onDelete('cascade');
            $table->foreign('received_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['po_id', 'date_received']);
        });

        // Products table (E-commerce)
        Schema::create('products', function (Blueprint $table) {
            $table->id('product_id');
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('product_name', 150);
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('slug')->unique();
            $table->json('images')->nullable();
            $table->json('attributes')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('stock_quantity')->default(0);
            $table->integer('sold_count')->default(0);
            $table->timestamps();
            
            $table->foreign('inventory_id')->references('inventory_id')->on('inventories')->onDelete('set null');
            $table->index(['product_name', 'status']);
            $table->index('slug');
        });

        // Sales Orders table
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('order_number')->unique();
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'credit_card', 'bank_transfer', 'cheque', 'online'])->nullable();
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'refunded'])->default('pending');
            $table->enum('status', ['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('draft');
            $table->text('shipping_address')->nullable();
            $table->text('billing_address')->nullable();
            $table->unsignedBigInteger('sales_rep_id')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('sales_rep_id')->references('employee_id')->on('employees')->onDelete('set null');
            $table->index(['order_number', 'status']);
            $table->index(['order_date', 'customer_id']);
        });

        // Order Items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->decimal('price_per_unit', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();
            
            $table->foreign('order_id')->references('order_id')->on('sales_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('cascade');
            $table->index('order_id');
        });

        // E-commerce Orders table
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id('ecommerce_order_id');
            $table->string('platform', 100);
            $table->string('external_order_code', 100);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('linked_sales_order_id')->nullable();
            $table->date('order_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('order_status', ['new', 'processing', 'shipped', 'delivered', 'cancelled'])->default('new');
            $table->text('shipping_info')->nullable();
            $table->json('platform_data')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('linked_sales_order_id')->references('order_id')->on('sales_orders')->onDelete('set null');
            $table->unique(['platform', 'external_order_code']);
            $table->index(['platform', 'order_date']);
        });

        // Tickets table (Customer Service)
        Schema::create('tickets', function (Blueprint $table) {
            $table->id('ticket_id');
            $table->string('ticket_number')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('assigned_agent')->nullable();
            $table->unsignedBigInteger('related_order_id')->nullable();
            $table->string('subject', 255);
            $table->text('issue_description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled'])->default('open');
            $table->enum('category', ['billing', 'technical', 'sales', 'general', 'complaint'])->default('general');
            $table->json('attachments')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('assigned_agent')->references('employee_id')->on('employees')->onDelete('set null');
            $table->foreign('related_order_id')->references('order_id')->on('sales_orders')->onDelete('set null');
            $table->index(['ticket_number', 'status']);
            $table->index(['priority', 'created_at']);
        });

        // Ticket Notes table
        Schema::create('ticket_notes', function (Blueprint $table) {
            $table->id('note_id');
            $table->unsignedBigInteger('ticket_id');
            $table->text('note_text');
            $table->unsignedBigInteger('created_by');
            $table->boolean('internal')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
            
            $table->foreign('ticket_id')->references('ticket_id')->on('tickets')->onDelete('cascade');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['ticket_id', 'created_at']);
        });

        // Chart of Accounts table
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->string('account_code', 50)->unique();
            $table->string('account_name', 150);
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->unsignedBigInteger('parent_account_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('parent_account_id')->references('account_id')->on('chart_of_accounts')->onDelete('set null');
            $table->index(['account_code', 'account_type']);
        });

        // Journal Entries table
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id('journal_id');
            $table->date('entry_date');
            $table->string('journal_number')->unique();
            $table->text('description');
            $table->unsignedBigInteger('created_by');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->decimal('total_debit', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->timestamps();
            
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['journal_number', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Journal Details table
        Schema::create('journal_details', function (Blueprint $table) {
            $table->id('detail_id');
            $table->unsignedBigInteger('journal_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('journal_id')->references('journal_id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('account_id')->on('chart_of_accounts')->onDelete('cascade');
            $table->index('journal_id');
        });

        // Payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->string('payment_number')->unique();
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('method', ['cash', 'credit_card', 'bank_transfer', 'cheque', 'online'])->default('cash');
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();
            
            $table->foreign('received_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['payment_date', 'status']);
        });

        // Projects table
        Schema::create('projects', function (Blueprint $table) {
            $table->id('project_id');
            $table->string('project_name', 150);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('budget_total', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->enum('status', ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('planning');
            $table->unsignedBigInteger('project_manager_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->text('objectives')->nullable();
            $table->json('team_members')->nullable();
            $table->timestamps();
            
            $table->foreign('project_manager_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->foreign('client_id')->references('customer_id')->on('customers')->onDelete('set null');
            $table->index(['project_name', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Project Tasks table
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id('task_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_name', 150);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('progress', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'blocked'])->default('not_started');
            $table->unsignedBigInteger('dependency_task_id')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->integer('actual_hours')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('attachments')->nullable();
            $table->timestamps();
            
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            $table->foreign('assigned_to')->references('employee_id')->on('employees')->onDelete('set null');
            $table->foreign('dependency_task_id')->references('task_id')->on('project_tasks')->onDelete('set null');
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'priority']);
        });

        // Reports table
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->string('report_name', 100);
            $table->string('module_name', 100);
            $table->unsignedBigInteger('created_by');
            $table->string('report_type', 50);
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->json('data')->nullable();
            $table->enum('status', ['draft', 'generated', 'scheduled', 'archived'])->default('draft');
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['module_name', 'report_type']);
            $table->index(['created_at', 'status']);
        });

        // Additional pivot tables for many-to-many relationships
        
        // Purchase Order Items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_id');
            $table->unsignedBigInteger('inventory_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('po_id')->references('po_id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('inventory_id')->references('inventory_id')->on('inventories')->onDelete('cascade');
            $table->index('po_id');
        });

        // Requisition Items
        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requisition_id');
            $table->unsignedBigInteger('inventory_id');
            $table->integer('quantity');
            $table->string('purpose')->nullable();
            $table->timestamps();
            
            $table->foreign('requisition_id')->references('requisition_id')->on('purchase_requisitions')->onDelete('cascade');
            $table->foreign('inventory_id')->references('inventory_id')->on('inventories')->onDelete('cascade');
        });

        // Product Categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('product_categories')->onDelete('set null');
        });

        // Product Category Pivot
        Schema::create('product_category_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('cascade');
            
            $table->primary(['product_id', 'category_id']);
        });

        // Warehouse Locations
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code')->unique();
            $table->string('warehouse_name');
            $table->string('location_address');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('capacity', 12, 2)->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();
        });

        // Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // create, update, delete, view
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['table_name', 'record_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to avoid foreign key constraints
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('product_category_pivot');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('purchase_order_items');
        
        Schema::dropIfExists('reports');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('journal_details');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('ticket_notes');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('hr_payroll');
        Schema::dropIfExists('hr_attendance');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');
    }
};