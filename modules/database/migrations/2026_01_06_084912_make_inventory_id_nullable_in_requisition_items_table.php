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
        Schema::table('requisition_items', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign(['inventory_id']);
            
            // Then change the column to nullable
            $table->integer('inventory_id')->nullable()->change();
            
            // Optionally re-add the foreign key constraint if you still want it
            // $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            // First drop the foreign key (if re-added in up())
            // $table->dropForeign(['inventory_id']);
            
            // Change column back to not nullable
            $table->integer('inventory_id')->nullable(false)->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('inventory_id')->references('id')->on('inventories');
        });
    }
};