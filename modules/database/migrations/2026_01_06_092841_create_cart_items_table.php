<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            
            // Use unsignedBigInteger to match users.user_id
            $table->unsignedBigInteger('user_id');
            
            // Use unsignedBigInteger to match products.product_id
            $table->unsignedBigInteger('product_id');
            
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
            
            // Foreign key to users table using user_id
            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            // Foreign key to products table using product_id
            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->onDelete('cascade');
            
            // Ensure unique product per user in cart
            $table->unique(['user_id', 'product_id']);
            
            // Add indexes for better performance
            $table->index('user_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};