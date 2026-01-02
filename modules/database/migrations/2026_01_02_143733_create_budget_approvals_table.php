<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First check if budgets table exists
        if (!Schema::hasTable('budgets')) {
            // Create budgets table if it doesn't exist
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

        // Create budget_approvals table
        if (!Schema::hasTable('budget_approvals')) {
            Schema::create('budget_approvals', function (Blueprint $table) {
                $table->id('approval_id');
                $table->unsignedBigInteger('budget_id');
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
                $table->text('remarks')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                
                // Add foreign key constraints
                $table->foreign('budget_id')->references('budget_id')->on('budgets')->onDelete('cascade');
                $table->foreign('requested_by')->references('user_id')->on('users')->onDelete('cascade');
                $table->foreign('reviewed_by')->references('user_id')->on('users')->onDelete('set null');
                
                $table->index(['budget_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_approvals');
        // Only drop budgets table if we created it in this migration
        if (Schema::hasTable('budgets') && !Schema::hasColumn('budgets', 'budget_name')) {
            Schema::dropIfExists('budgets');
        }
    }
};