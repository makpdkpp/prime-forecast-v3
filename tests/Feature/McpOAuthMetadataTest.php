<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpOAuthMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
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
            $table->string('scope', 255);
            $table->string('resource', 2048)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mcp_oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('token_hash', 64)->primary();
            $table->string('client_id', 255);
            $table->unsignedBigInteger('user_id');
            $table->string('scope', 255);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('user', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->unsignedInteger('role_id');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        config()->set('services.prime_mcp.oauth_enabled', true);
        config()->set('services.prime_mcp.oauth_resource', 'https://mcp-demo.primes.co.th');
        config()->set('services.prime_mcp.oauth_issuer', 'https://demo.primes.co.th');
    }

    public function test_protected_resource_metadata_points_to_demo_authorization_server(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('resource', 'https://mcp-demo.primes.co.th')
            ->assertJsonPath('authorization_servers.0', 'https://demo.primes.co.th');
    }

    public function test_authorization_server_metadata_advertises_pkce_and_read_scope(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://demo.primes.co.th')
            ->assertJsonPath('authorization_response_iss_parameter_supported', true)
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
            ->assertJsonPath('scopes_supported.0', 'mcp:read');
    }

    public function test_dynamic_registration_supports_chatgpt_and_codex_loopback_redirects(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT Demo',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/demo'],
        ])->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none');

        $codexResponse = $this->postJson('/oauth/register', [
            'client_name' => 'Codex Prime Forecast',
            'redirect_uris' => ['http://127.0.0.1:49152/callback/prime-forecast-demo'],
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ])->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('redirect_uris.0', 'http://127.0.0.1:49152/callback/prime-forecast-demo');

        $this->assertLessThan(1048576, strlen($codexResponse->getContent()));

        $rejectedResponse = $this->postJson('/oauth/register', [
            'client_name' => 'Untrusted',
            'redirect_uris' => ['https://evil.example/callback'],
        ])->assertStatus(400)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->assertLessThan(1048576, strlen($rejectedResponse->getContent()));
    }

    public function test_dynamic_registration_rejects_non_loopback_http_redirects(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Untrusted local-looking host',
            'redirect_uris' => ['http://127.0.0.1.evil.example:49152/callback'],
        ])->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_token_response_uses_integer_expiry_and_no_store_headers(): void
    {
        $userId = DB::table('user')->insertGetId([
            'email' => 'oauth-user@example.test',
            'password' => 'unused-test-hash',
            'role_id' => 3,
            'is_active' => true,
        ]);
        $verifier = str_repeat('v', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $plainCode = 'oauth-test-code';
        DB::table('mcp_oauth_authorization_codes')->insert([
            'code_hash' => hash('sha256', $plainCode),
            'client_id' => 'mcp_test_client',
            'user_id' => $userId,
            'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'code_challenge' => $challenge,
            'scope' => 'mcp:read',
            'resource' => 'https://mcp-demo.primes.co.th',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $plainCode,
            'client_id' => 'mcp_test_client',
            'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'code_verifier' => $verifier,
            'resource' => 'https://mcp-demo.primes.co.th',
        ]);

        $response->assertOk()
            ->assertJsonPath('resource', 'https://mcp-demo.primes.co.th')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
        $this->assertIsInt($response->json('expires_in'));
        $this->assertGreaterThan(0, $response->json('expires_in'));
    }
}
