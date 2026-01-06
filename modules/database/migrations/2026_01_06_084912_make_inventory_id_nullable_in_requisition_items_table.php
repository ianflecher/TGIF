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
            // Make inventory_id nullable
            $table->integer('inventory_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            // Make inventory_id not nullable again
            $table->integer('inventory_id')->nullable(false)->change();
        });
    }
};