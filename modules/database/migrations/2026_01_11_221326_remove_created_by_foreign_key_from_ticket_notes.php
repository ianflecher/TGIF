<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up()
{
    Schema::table('ticket_notes', function (Blueprint $table) {
        // Drop the foreign key constraint
        $table->dropForeign(['created_by']);
        
        // Make sure the column is nullable
        $table->unsignedBigInteger('created_by')->nullable()->change();
    });
}

public function down()
{
    Schema::table('ticket_notes', function (Blueprint $table) {
        $table->unsignedBigInteger('created_by')->nullable(false)->change();
        
        // Re-add the foreign key
        $table->foreign('created_by')
              ->references('user_id')
              ->on('users')
              ->onDelete('set null');
    });
}
};
