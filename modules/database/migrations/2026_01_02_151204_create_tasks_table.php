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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id('task_id');
            $table->unsignedBigInteger('phase_id')->nullable();
            $table->string('task_name', 255);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 255);
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->string('assigned_to', 255)->nullable();
            $table->timestamps();
            
            $table->foreign('phase_id')->references('phase_id')->on('project_phases')->onDelete('set null');
            $table->index('phase_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};