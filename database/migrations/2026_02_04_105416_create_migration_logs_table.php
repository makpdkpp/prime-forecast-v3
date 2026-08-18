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
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('action', 50);
            $table->string('migration_name', 255)->nullable();
            $table->text('output')->nullable();
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_logs');
    }
};
