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
        // Create resources table first
        Schema::create('resources', function (Blueprint $table) {
            $table->id('resource_id');
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('resource_name');
            $table->string('type');
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('availability_quantity', 10, 2)->default(0.00);
            $table->string('status');
            $table->timestamps();
            
            // Indexes
            $table->index('inventory_id');
            $table->index('type');
            $table->index('status');
        });

        // Then create resource_allocations table with foreign key to resources
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
        // Drop in reverse order (child tables first)
        Schema::dropIfExists('resource_allocations');
        Schema::dropIfExists('resources');
    }
};