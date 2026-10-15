<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('password')->nullable();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('password'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'remember_token', 'email_verified_at', 'two_factor_secret',
            'two_factor_recovery_codes', 'two_factor_confirmed_at',
        ]));
    }
};
