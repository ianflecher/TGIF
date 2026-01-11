<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('product_name')->after('product_id'); // Add this line
            $table->string('size')->nullable()->after('total_price');
            $table->string('flavor')->nullable()->after('size');
            $table->string('variety')->nullable()->after('flavor');
            $table->decimal('base_price', 10, 2)->nullable()->after('variety');
            $table->integer('price_adjustment_percent')->default(0)->after('base_price');
            $table->decimal('final_price', 10, 2)->nullable()->after('price_adjustment_percent');
        });
    }

    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_name', 
                'size', 
                'flavor', 
                'variety', 
                'base_price', 
                'price_adjustment_percent', 
                'final_price'
            ]);
        });
    }
};