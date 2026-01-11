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
        // Create logistics_drivers table
        Schema::create('logistics_drivers', function (Blueprint $table) {
            $table->bigIncrements('driver_id');
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('license_number', 255)->unique();
            $table->enum('license_type', ['commercial', 'non_commercial', 'learner'])->default('commercial');
            $table->date('license_expiry');
            $table->integer('years_experience')->default(0);
            $table->string('current_location', 255)->nullable();
            $table->json('vehicle_types_allowed')->nullable();
            $table->enum('status', ['available', 'in_transit', 'on_break', 'off_duty', 'sick_leave'])->default('available');
            $table->decimal('rating', 3, 2)->default(5.00)->nullable();
            $table->timestamps();

            // Check if employees table exists before adding foreign key
            if (Schema::hasTable('employees')) {
                $table->foreign('employee_id')
                    ->references('employee_id')
                    ->on('employees')
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_drivers');
    }
};