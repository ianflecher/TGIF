<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('fulfillment_mode')->default('delivery');
            $table->string('delivery_zone')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->text('pickup_location')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('checkout_token', 64)->nullable()->unique();
        });
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('status');
            $table->string('message');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('order_id')->on('sales_orders')->cascadeOnDelete();
            $table->foreign('actor_id')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['order_id', 'created_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind')->default('receipt');
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('currency', 3)->default('PHP');
            $table->string('provider')->default('manual');
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->foreign('parent_payment_id')->references('payment_id')->on('payments')->restrictOnDelete();
            $table->foreign('journal_id')->references('journal_id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['parent_payment_id']);
            $table->dropForeign(['journal_id']);
            $table->dropColumn(['kind', 'parent_payment_id', 'idempotency_key', 'currency', 'provider', 'journal_id']);
        });
        Schema::dropIfExists('order_events');
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn(['fulfillment_mode', 'delivery_zone', 'postal_code', 'delivery_fee', 'pickup_location', 'scheduled_for', 'checkout_token']));
        Schema::dropIfExists('commerce_settings');
    }
};
