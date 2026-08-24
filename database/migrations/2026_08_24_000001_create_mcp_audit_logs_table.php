<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->index();
            $table->string('trace_id', 100)->nullable()->index();
            $table->string('environment', 30);
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('role', 30)->nullable();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('tool_name', 120)->nullable();
            $table->string('endpoint', 255);
            $table->json('arguments')->nullable();
            $table->json('scope')->nullable();
            $table->string('decision', 20);
            $table->unsignedSmallInteger('http_status');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('items_returned')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
