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
        Schema::create('transactional_transfer_history', function (Blueprint $table) {
            $table->id('transfer_id');
            $table->unsignedInteger('transac_id');
            $table->unsignedInteger('from_user_id');
            $table->unsignedInteger('to_user_id');
            $table->unsignedInteger('transferred_by');
            $table->text('transfer_reason')->nullable();
            $table->unsignedInteger('old_team_id')->nullable();
            $table->unsignedInteger('new_team_id')->nullable();
            $table->timestamp('transferred_at')->useCurrent();
            
            $table->foreign('transac_id')->references('transac_id')->on('transactional')->onDelete('cascade');
            $table->foreign('from_user_id')->references('user_id')->on('user')->onDelete('cascade');
            $table->foreign('to_user_id')->references('user_id')->on('user')->onDelete('cascade');
            $table->foreign('transferred_by')->references('user_id')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactional_transfer_history');
    }
};
