<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $t) {
            $t->id(); $t->foreignId('po_id')->constrained('purchase_orders', 'po_id')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers', 'supplier_id')->restrictOnDelete();
            $t->string('invoice_number', 100); $t->date('invoice_date'); $t->date('due_date');
            $t->string('status', 20)->default('submitted'); $t->decimal('total', 12, 2);
            $t->uuid('request_key')->unique(); $t->string('payload_hash', 64);
            $t->foreignId('created_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->text('review_note')->nullable();
            $t->foreignId('journal_id')->nullable()->constrained('journal_entries', 'journal_id')->restrictOnDelete();
            $t->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts', 'account_id')->restrictOnDelete();
            $t->foreignId('payable_account_id')->nullable()->constrained('chart_of_accounts', 'account_id')->restrictOnDelete();
            $t->timestamps(); $t->unique(['supplier_id', 'invoice_number']);
        });
        Schema::create('supplier_invoice_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers', 'supplier_id')->restrictOnDelete();
            $t->string('event', 80); $t->timestamps();
        });
        Schema::create('supplier_invoice_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $t->foreignId('po_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $t->unsignedInteger('quantity'); $t->decimal('unit_price', 10, 2); $t->decimal('total', 12, 2);
            $t->unique(['invoice_id', 'po_item_id']);
        });
        Schema::create('supplier_disbursements', function (Blueprint $t) {
            $t->id(); $t->foreignId('invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $t->decimal('amount', 12, 2); $t->string('status', 20)->default('pending');
            $t->uuid('request_key')->unique(); $t->string('payload_hash', 64);
            $t->foreignId('requested_by')->constrained('users', 'user_id')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->foreignId('recorded_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->timestamp('recorded_at')->nullable();
            $t->date('payment_date')->nullable(); $t->string('reference', 150)->nullable()->unique();
            $t->foreignId('cash_account_id')->nullable()->constrained('chart_of_accounts', 'account_id')->restrictOnDelete();
            $t->foreignId('journal_id')->nullable()->constrained('journal_entries', 'journal_id')->restrictOnDelete();
            $t->text('note')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('supplier_disbursements'); Schema::dropIfExists('supplier_invoice_lines'); Schema::dropIfExists('supplier_invoice_events'); Schema::dropIfExists('supplier_invoices');
    }
};


