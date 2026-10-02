<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_sales_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('token_id');
            $table->uuid('idempotency_key');
            $table->text('payload'); // Encrypted, including customer contact details.
            $table->char('payload_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedInteger('project_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_sales_drafts');
    }
};
