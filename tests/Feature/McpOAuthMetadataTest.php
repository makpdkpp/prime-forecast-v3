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

    public function test_dynamic_registration_allows_only_openai_redirect_hosts(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT Demo',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/demo'],
        ])->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none');

        $this->postJson('/oauth/register', [
            'client_name' => 'Untrusted',
            'redirect_uris' => ['https://evil.example/callback'],
        ])->assertStatus(400);
    }
}
