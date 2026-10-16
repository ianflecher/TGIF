<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_credits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers', 'supplier_id')->restrictOnDelete();
            $t->string('number', 100); $t->date('credit_date'); $t->text('reason');
            $t->string('status', 20)->default('pending'); $t->decimal('amount', 12, 2);
            $t->uuid('request_key')->unique(); $t->string('payload_hash', 64);
            $t->foreignId('requested_by')->constrained('users', 'user_id')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable(); $t->text('review_note')->nullable();
            $t->foreignId('journal_id')->nullable()->constrained('journal_entries', 'journal_id')->restrictOnDelete();
            $t->timestamps(); $t->unique(['supplier_id', 'number']);
        });
        Schema::create('supplier_credit_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('credit_id')->constrained('supplier_credits')->restrictOnDelete();
            $t->foreignId('invoice_line_id')->constrained('supplier_invoice_lines')->restrictOnDelete();
            $t->decimal('amount', 12, 2); $t->unsignedInteger('return_quantity')->default(0);
            $t->unique(['credit_id', 'invoice_line_id']);
        });
        Schema::create('supplier_payment_corrections', function (Blueprint $t) {
            $t->id(); $t->foreignId('invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $t->foreignId('payment_id')->constrained('supplier_disbursements')->restrictOnDelete();
            $t->string('kind', 20); $t->string('status', 20)->default('pending');
            $t->decimal('amount', 12, 2); $t->date('correction_date'); $t->string('reference', 150)->unique();
            $t->text('reason'); $t->uuid('request_key')->unique(); $t->string('payload_hash', 64);
            $t->foreignId('requested_by')->constrained('users', 'user_id')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable(); $t->text('review_note')->nullable();
            $t->foreignId('journal_id')->nullable()->constrained('journal_entries', 'journal_id')->restrictOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_corrections');
        Schema::dropIfExists('supplier_credit_lines');
        Schema::dropIfExists('supplier_credits');
    }
};
