<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->timestamp('supplier_dispatched_at')->nullable());
        Schema::create('supplier_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('purchase_orders', 'po_id')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', 'supplier_id')->restrictOnDelete();
            $table->string('event');
            $table->string('previous_status');
            $table->string('status');
            $table->timestamps();
            $table->index(['supplier_id', 'po_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_events');
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropColumn('supplier_dispatched_at'));
    }
};
