<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_oauth_clients', function (Blueprint $table) {
            $table->string('client_id', 255)->primary();
            $table->string('client_name', 150);
            $table->json('redirect_uris');
            $table->timestamps();
        });
        Schema::create('mcp_oauth_authorization_codes', function (Blueprint $table) {
            $table->string('code_hash', 64)->primary();
            $table->string('client_id', 255);
            $table->unsignedBigInteger('user_id');
            $table->string('redirect_uri', 2048);
            $table->string('code_challenge', 128);
            $table->string('scope', 255)->default('mcp:read');
            $table->string('resource', 2048)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'user_id']);
        });
        Schema::create('mcp_oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('token_hash', 64)->primary();
            $table->string('client_id', 255);
            $table->unsignedBigInteger('user_id');
            $table->string('scope', 255)->default('mcp:read');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_refresh_tokens');
        Schema::dropIfExists('mcp_oauth_authorization_codes');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
