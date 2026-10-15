<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->json('module_permissions')->nullable());
        Schema::table('application_documents', function (Blueprint $t) {
            $t->string('disk')->default('public'); // Preserve the location of legacy records.
            $t->string('sha256', 64)->nullable();
            $t->softDeletes();
        });
        Schema::create('financial_periods', function (Blueprint $t) {
            $t->id(); $t->date('starts_on'); $t->date('ends_on'); $t->boolean('closed')->default(false);
            $t->foreignId('created_by')->constrained('users', 'user_id')->restrictOnDelete();
            $t->timestamps(); $t->unique(['starts_on', 'ends_on']);
        });
        Schema::table('journal_entries', function (Blueprint $t) {
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->timestamp('posted_at')->nullable();
            $t->foreignId('reversal_of')->nullable()->unique()->constrained('journal_entries', 'journal_id')->restrictOnDelete();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->decimal('unit_cost_snapshot', 12, 2)->nullable());
        Schema::create('warehouse_receipt_batches', function (Blueprint $t) {
            $t->id(); $t->uuid('request_key')->unique(); $t->string('payload_hash', 64);
            $t->foreignId('po_id')->constrained('purchase_orders', 'po_id')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users', 'user_id')->restrictOnDelete(); $t->timestamps();
        });
        Schema::table('goods_receipts', fn (Blueprint $t) => $t->foreignId('receipt_batch_id')->nullable()->constrained('warehouse_receipt_batches')->restrictOnDelete());
        Schema::create('order_adjustments', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('sales_orders', 'order_id')->restrictOnDelete();
            $t->uuid('request_key')->unique(); $t->string('kind'); $t->text('reason');
            $t->integer('credit_cents'); $t->integer('refund_due_cents');
            $t->integer('fee_credit_cents')->default(0);
            $t->foreignId('actor_id')->constrained('users', 'user_id')->restrictOnDelete();
            $t->foreignId('journal_id')->nullable()->constrained('journal_entries', 'journal_id')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('order_adjustment_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('adjustment_id')->constrained('order_adjustments')->restrictOnDelete();
            $t->foreignId('order_item_id')->constrained('order_items', 'order_item_id')->restrictOnDelete();
            $t->integer('quantity'); $t->integer('restocked_quantity'); $t->integer('credit_cents'); $t->timestamps();
            $t->unique(['adjustment_id', 'order_item_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('order_adjustment_items'); Schema::dropIfExists('order_adjustments');
        Schema::table('goods_receipts', function (Blueprint $t) { $t->dropForeign(['receipt_batch_id']); $t->dropColumn('receipt_batch_id'); });
        Schema::dropIfExists('warehouse_receipt_batches');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('unit_cost_snapshot'));
        Schema::table('journal_entries', function (Blueprint $t) {
            $t->dropForeign(['reversal_of']); $t->dropUnique(['reversal_of']); $t->dropForeign(['approved_by']);
            $t->dropColumn(['submitted_at', 'approved_by', 'approved_at', 'posted_at', 'reversal_of']);
        });
        Schema::dropIfExists('financial_periods');
        Schema::table('application_documents', fn (Blueprint $t) => $t->dropColumn(['disk', 'sha256', 'deleted_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('module_permissions'));
    }
};
