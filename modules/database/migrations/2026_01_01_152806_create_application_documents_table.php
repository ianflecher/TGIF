<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('user_id');
            $table->string('filename');
            $table->string('filepath');
            $table->string('filetype');
            $table->integer('filesize');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            
            $table->foreign('application_id')->references('application_id')->on('job_applications')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};