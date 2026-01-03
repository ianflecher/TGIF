<?php
// database/migrations/xxxx_xx_xx_add_reorder_level_to_products_simple.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Just add the missing column
        if (!Schema::hasColumn('products', 'reorder_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('reorder_level')->default(10)->after('stock_quantity');
            });
            
            // Update existing records with default value
            DB::table('products')->update(['reorder_level' => 10]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'reorder_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('reorder_level');
            });
        }
    }
};