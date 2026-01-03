<?php
// database/migrations/xxxx_xx_xx_add_product_id_to_requisition_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('requisition_items', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('inventory_id');
                
                // Add foreign key constraint
                $table->foreign('product_id')
                    ->references('product_id')
                    ->on('products')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
        
        // If you want to copy data from inventory_id to product_id
        // This assumes inventory_id is related to product_id somehow
        // You might need to adjust this based on your data structure
        try {
            DB::statement('UPDATE requisition_items ri 
                          INNER JOIN inventories i ON ri.inventory_id = i.inventory_id
                          SET ri.product_id = i.product_id 
                          WHERE ri.product_id IS NULL');
        } catch (\Exception $e) {
            // If the update fails, it's okay - we'll handle it in the application
        }
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['product_id']);
            // Then drop the column
            $table->dropColumn('product_id');
        });
    }
};