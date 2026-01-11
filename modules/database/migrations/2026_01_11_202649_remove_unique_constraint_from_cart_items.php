<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['user_id', 'product_id']);
        });
    }

    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Re-add the unique constraint if rolling back
            $table->unique(['user_id', 'product_id']);
        });
    }
};