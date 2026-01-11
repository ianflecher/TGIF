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
        Schema::table('sales_orders', function (Blueprint $table) {
            // Modify the status enum to include return statuses
            $table->enum('status', [
                'draft',
                'confirmed', 
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'return_requested',  // New status for return request
                'return_approved',   // New status for approved return
                'return_rejected',   // New status for rejected return
                'returned'           // New status for completed return
            ])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Revert to original enum values
            $table->enum('status', [
                'draft',
                'confirmed', 
                'processing',
                'shipped',
                'delivered',
                'cancelled'
            ])->default('draft')->change();
        });
    }
};