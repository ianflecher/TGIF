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
        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->id('allocation_id');
            $table->foreignId('task_id')->constrained('tasks', 'task_id')->onDelete('cascade');
            $table->foreignId('resource_id')->constrained('resources', 'resource_id')->onDelete('cascade');
            $table->string('resource_type'); // e.g., 'employee', 'equipment', 'material'
            $table->string('resource_name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->date('allocation_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('hours_allocated')->nullable()->comment('For labor/resources with hourly rates');
            $table->enum('status', ['pending', 'allocated', 'in_use', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['task_id', 'resource_id']);
            $table->index('resource_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_allocations');
    }
};