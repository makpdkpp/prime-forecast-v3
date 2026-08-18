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
        Schema::create('user_forecast_target', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('fiscal_year', 10);
            $table->decimal('target_value', 15, 2)->default(0);
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade');
            
            // Unique constraint to prevent duplicate targets for same user and year
            $table->unique(['user_id', 'fiscal_year'], 'unique_user_year');
            
            // Index for faster queries
            $table->index('fiscal_year', 'idx_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_forecast_target');
    }
};
