<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id('budget_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('phase_id')->default(0);
            $table->unsignedBigInteger('task_id')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0.00);
            $table->decimal('actual_cost', 15, 2)->default(0.00);
            $table->decimal('variance', 15, 2)->default(0.00);
            $table->timestamps();
            
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            $table->index(['project_id', 'phase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};