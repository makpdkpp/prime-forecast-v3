<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();

        config()->set('services.prime_mcp.enabled', true);
        config()->set('services.prime_mcp.service_key', 'demo-service-key-for-tests');
        config()->set('services.prime_mcp.audit_enabled', false);
    }

    public function test_health_requires_service_key_and_reports_gateway_status(): void
    {
        $this->getJson('/api/mcp/v1/health')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_service_key');

        $this->withServiceKey()->getJson('/api/mcp/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_health_reports_not_ready_when_audit_storage_is_missing(): void
    {
        Schema::drop('mcp_audit_logs');

        $this->withServiceKey()->getJson('/api/mcp/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('audit_log', 'unavailable');
    }

    public function test_auth_context_requires_mcp_read_ability_and_expiring_token(): void
    {
        $user = $this->createUser(3, [10]);

        $withoutAbility = $user->createToken('missing-ability', [], now()->addHour())->plainTextToken;
        $this->withMcpToken($withoutAbility)->postJson('/api/mcp/v1/auth/context')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $withoutExpiry = $user->createToken('missing-expiry', ['mcp:read'])->plainTextToken;
        $this->withMcpToken($withoutExpiry)->postJson('/api/mcp/v1/auth/context')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_auth_context_returns_node_compatible_permissions_and_expiry_for_every_role(): void
    {
        $cases = [
            3 => ['sales', ['forecast.self.read']],
            2 => ['team_admin', ['forecast.self.read', 'forecast.team.read']],
            1 => ['admin', ['forecast.self.read', 'forecast.team.read', 'forecast.company.read']],
        ];

        foreach ($cases as $roleId => [$role, $permissions]) {
            $user = $this->createUser($roleId, $roleId === 2 ? [10] : []);
            $token = $user->createToken('mcp-read-'.$role, ['mcp:read'], now()->addHour())->plainTextToken;
            $this->app['auth']->forgetGuards();

            $this->withMcpToken($token)->postJson('/api/mcp/v1/auth/context')
                ->assertOk()
                ->assertJsonPath('data.user_id', (int) $user->user_id)
                ->assertJsonPath('data.role', $role)
                ->assertJsonPath('data.permissions', $permissions)
                ->assertJsonPath('data.team_ids', $roleId === 2 ? [10] : [])
                ->assertJsonStructure(['data' => ['token_expires_at']]);
        }
    }

    public function test_sales_can_read_only_their_own_forecast(): void
    {
        $sales = $this->createUser(3, [10]);
        $otherSales = $this->createUser(3, [20]);
        $token = $sales->createToken('sales-read', ['mcp:read'], now()->addHour())->plainTextToken;

        $this->withMcpToken($token)->getJson('/api/mcp/v1/forecast/me')->assertOk();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/teams/10/forecasts')->assertForbidden();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/sales/'.$otherSales->user_id.'/forecast')->assertForbidden();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/forecast/company')->assertForbidden();
    }

    public function test_team_admin_cannot_escape_assigned_team_scope(): void
    {
        $teamAdmin = $this->createUser(2, [10]);
        $ownSales = $this->createUser(3, [10]);
        $otherSales = $this->createUser(3, [20]);
        $token = $teamAdmin->createToken('team-read', ['mcp:read'], now()->addHour())->plainTextToken;

        $this->withMcpToken($token)->getJson('/api/mcp/v1/teams/10/forecasts')->assertOk();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/teams/20/forecasts')->assertForbidden();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/sales/'.$ownSales->user_id.'/forecast')->assertOk();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/sales/'.$otherSales->user_id.'/forecast')->assertForbidden();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/forecast/company')->assertForbidden();
    }

    public function test_admin_can_read_company_team_and_sales_scopes(): void
    {
        $admin = $this->createUser(1);
        $sales = $this->createUser(3, [20]);
        $token = $admin->createToken('admin-read', ['mcp:read'], now()->addHour())->plainTextToken;

        $this->withMcpToken($token)->getJson('/api/mcp/v1/forecast/company')->assertOk();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/teams/20/forecasts')->assertOk();
        $this->withMcpToken($token)->getJson('/api/mcp/v1/sales/'.$sales->user_id.'/forecast')->assertOk();
    }

    public function test_forecast_date_range_is_validated_and_applied(): void
    {
        $sales = $this->createUser(3, [10]);
        $token = $sales->createToken('date-read', ['mcp:read'], now()->addHour())->plainTextToken;
        $this->createForecast($sales->user_id, 10, '2026-01-15', 1000);
        $includedId = $this->createForecast($sales->user_id, 10, '2026-04-15', 2000);

        $this->withMcpToken($token)
            ->getJson('/api/mcp/v1/forecast/me?date_from=2026-04-01&date_to=2026-04-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $includedId)
            ->assertJsonPath('data.0.sales_id', (int) $sales->user_id)
            ->assertJsonPath('data.0.sales_name', 'MCP Tester')
            ->assertJsonPath('summary.project_count', 1);

        $this->withMcpToken($token)
            ->getJson('/api/mcp/v1/forecast/me?date_from=2026-05-01&date_to=2026-04-01')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_node_audit_contract_uses_service_key_without_user_bearer_token(): void
    {
        $sales = $this->createUser(3, [10]);

        $this->withServiceKey()->postJson('/api/mcp/v1/audit-events', [
            'request_id' => '0296f777-c90f-42be-9f67-6a8597d71265',
            'occurred_at' => now()->toIso8601String(),
            'phase' => 'read-only',
            'actor_user_id' => $sales->user_id,
            'actor_role' => 'sales',
            'team_ids' => [10],
            'tool' => 'get_my_forecast',
            'allowed' => true,
            'outcome' => 'success',
            'http_status' => 200,
            'argument_keys' => ['date_from', 'date_to'],
            'duration_ms' => 12,
        ])->assertStatus(202)->assertJsonPath('data.accepted', true);

        $this->assertDatabaseHas('mcp_audit_logs', [
            'request_id' => '0296f777-c90f-42be-9f67-6a8597d71265',
            'user_id' => $sales->user_id,
            'tool_name' => 'get_my_forecast',
            'decision' => 'allowed',
        ]);
    }

    public function test_node_audit_preserves_gateway_http_status_and_unknown_tool_name(): void
    {
        $sales = $this->createUser(3, [10]);

        $this->withServiceKey()->postJson('/api/mcp/v1/audit-events', [
            'request_id' => '22436954-78cb-4fd5-9c37-2ba543356598',
            'occurred_at' => now()->toIso8601String(),
            'phase' => 'read-only',
            'actor_user_id' => $sales->user_id,
            'actor_role' => 'sales',
            'team_ids' => [10],
            'tool' => 'attempted_unknown_tool',
            'allowed' => false,
            'outcome' => 'permission_denied',
            'http_status' => 418,
            'argument_keys' => [],
            'duration_ms' => 1,
        ])->assertStatus(202);

        $this->assertDatabaseHas('mcp_audit_logs', [
            'request_id' => '22436954-78cb-4fd5-9c37-2ba543356598',
            'tool_name' => 'attempted_unknown_tool',
            'decision' => 'denied',
            'http_status' => 418,
        ]);
    }

    public function test_denied_forecast_request_is_recorded_by_gateway_audit(): void
    {
        config()->set('services.prime_mcp.audit_enabled', true);
        $sales = $this->createUser(3, [10]);
        $token = $sales->createToken('denied-audit', ['mcp:read'], now()->addHour())->plainTextToken;

        $this->withMcpToken($token)
            ->getJson('/api/mcp/v1/forecast/company')
            ->assertForbidden();

        $this->assertDatabaseHas('mcp_audit_logs', [
            'user_id' => $sales->user_id,
            'endpoint' => '/api/mcp/v1/forecast/company',
            'decision' => 'denied',
            'http_status' => 403,
        ]);
    }

    private function withServiceKey(): static
    {
        return $this->withHeaders(['X-Prime-MCP-Key' => 'demo-service-key-for-tests']);
    }

    private function withMcpToken(string $token): static
    {
        return $this->withHeaders([
            'X-Prime-MCP-Key' => 'demo-service-key-for-tests',
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    private function createUser(int $roleId, array $teamIds = []): User
    {
        $id = DB::table('user')->insertGetId([
            'email' => uniqid('mcp-', true).'@example.test',
            'password' => 'test-password-hash',
            'role_id' => $roleId,
            'nname' => 'MCP',
            'surename' => 'Tester',
            'is_active' => true,
        ]);
        foreach ($teamIds as $teamId) {
            DB::table('transactional_team')->insert(['user_id' => $id, 'team_id' => $teamId]);
        }

        return User::query()->findOrFail($id);
    }

    private function createForecast(int $userId, int $teamId, string $date, int $value): int
    {
        return DB::table('transactional')->insertGetId([
            'user_id' => $userId,
            'team_id' => $teamId,
            'company_id' => 1,
            'Product_detail' => 'MCP test project',
            'product_value' => $value,
            'fiscalyear' => 2026,
            'contact_start_date' => $date,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->unsignedInteger('role_id');
            $table->string('nname');
            $table->string('surename');
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
        Schema::create('team_catalog', function (Blueprint $table) {
            $table->increments('team_id');
            $table->string('team');
        });
        DB::table('team_catalog')->insert([
            ['team_id' => 10, 'team' => 'Team A'],
            ['team_id' => 20, 'team' => 'Team B'],
        ]);
        Schema::create('transactional_team', function (Blueprint $table) {
            $table->increments('transacteam_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('team_id');
        });
        Schema::create('company_catalog', function (Blueprint $table) {
            $table->increments('company_id');
            $table->string('company');
        });
        DB::table('company_catalog')->insert(['company_id' => 1, 'company' => 'Test Company']);
        Schema::create('step', function (Blueprint $table) {
            $table->increments('level_id');
            $table->string('level');
            $table->integer('orderlv');
        });
        Schema::create('transactional', function (Blueprint $table) {
            $table->increments('transac_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('company_id');
            $table->string('Product_detail');
            $table->double('product_value');
            $table->integer('fiscalyear');
            $table->date('contact_start_date')->nullable();
            $table->softDeletes();
        });
        Schema::create('transactional_step', function (Blueprint $table) {
            $table->increments('transacstep_id');
            $table->unsignedInteger('transac_id');
            $table->unsignedInteger('level_id');
            $table->date('date')->nullable();
        });
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id');
            $table->string('trace_id')->nullable();
            $table->string('environment');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('tool_name')->nullable();
            $table->string('endpoint');
            $table->json('arguments')->nullable();
            $table->json('scope')->nullable();
            $table->string('decision');
            $table->unsignedSmallInteger('http_status');
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('items_returned')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
