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
        Schema::table('job_applications', function (Blueprint $table) {
            // Add interview scheduling fields
            $table->dateTime('interview_date')->nullable()->after('status');
            $table->text('interview_notes')->nullable()->after('interview_date');
            $table->unsignedBigInteger('interviewer_id')->nullable()->after('interview_notes');
            $table->enum('interview_type', ['phone', 'video', 'in_person', 'technical', 'hr'])->default('in_person')->after('interviewer_id');
            $table->enum('interview_status', ['scheduled', 'completed', 'cancelled', 'no_show'])->nullable()->after('interview_type');
            
            // Add foreign key constraint for interviewer
            $table->foreign('interviewer_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropForeign(['interviewer_id']);
            $table->dropColumn([
                'interview_date',
                'interview_notes',
                'interviewer_id',
                'interview_type',
                'interview_status'
            ]);
        });
    }
};