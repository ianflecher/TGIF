<?php
// database/migrations/xxxx_xx_xx_add_supplier_id_to_product_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('products', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('product_id');
                
                // Add foreign key constraint
                $table->foreign('supplier_id')
                    ->references('supplier_id')
                    ->on('suppliers')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['supplier_id']);
            // Then drop the column
            $table->dropColumn('supplier_id');
        });
    }
};