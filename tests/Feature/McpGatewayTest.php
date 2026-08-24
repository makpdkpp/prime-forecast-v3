<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class McpGatewayTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.prime_mcp.enabled', true);
        config()->set('services.prime_mcp.service_key', 'demo-service-key-for-tests');
        config()->set('services.prime_mcp.audit_enabled', false);
    }

    public function test_health_requires_service_key_and_reports_gateway_status(): void
    {
        $this->getJson('/api/mcp/v1/health')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_service_key');

        $this->withHeaders(['X-Prime-MCP-Key' => 'demo-service-key-for-tests'])
            ->getJson('/api/mcp/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_auth_context_requires_mcp_read_ability(): void
    {
        $user = User::query()->where('is_active', true)->whereIn('role_id', [1, 2, 3])->firstOrFail();
        $token = $user->createToken('mcp-test-no-ability', [])->plainTextToken;

        $this->withHeaders([
            'X-Prime-MCP-Key' => 'demo-service-key-for-tests',
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/mcp/v1/auth/context')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_authenticated_user_can_read_own_forecast_context(): void
    {
        $user = User::query()->where('is_active', true)->whereIn('role_id', [1, 2, 3])->firstOrFail();
        $token = $user->createToken('mcp-test-read', ['mcp:read'])->plainTextToken;

        $headers = [
            'X-Prime-MCP-Key' => 'demo-service-key-for-tests',
            'Authorization' => 'Bearer ' . $token,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/mcp/v1/auth/context')
            ->assertOk()
            ->assertJsonPath('data.user_id', (int) $user->user_id);

        $this->withHeaders($headers)
            ->getJson('/api/mcp/v1/forecast/me?year=2026&per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'summary', 'meta']);
    }
}
