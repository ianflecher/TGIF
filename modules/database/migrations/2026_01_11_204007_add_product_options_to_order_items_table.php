<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add product name for consistency
            if (!Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->after('product_id');
            }
            
            if (!Schema::hasColumn('order_items', 'size')) {
                $table->string('size')->nullable()->after('total');
            }
            
            if (!Schema::hasColumn('order_items', 'flavor')) {
                $table->string('flavor')->nullable()->after('size');
            }
            
            if (!Schema::hasColumn('order_items', 'variety')) {
                $table->string('variety')->nullable()->after('flavor');
            }
            
            if (!Schema::hasColumn('order_items', 'base_price')) {
                $table->decimal('base_price', 10, 2)->nullable()->after('variety');
            }
            
            if (!Schema::hasColumn('order_items', 'price_adjustment_percent')) {
                $table->integer('price_adjustment_percent')->default(0)->after('base_price');
            }
            
            if (!Schema::hasColumn('order_items', 'final_price')) {
                $table->decimal('final_price', 10, 2)->nullable()->after('price_adjustment_percent');
            }
            
            // Also consider adding unit_price to match cart_items
            if (!Schema::hasColumn('order_items', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->nullable()->after('price_per_unit');
            }
        });
    }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $columnsToDrop = [
                'product_name',
                'size',
                'flavor',
                'variety',
                'base_price',
                'price_adjustment_percent',
                'final_price',
                'unit_price'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};