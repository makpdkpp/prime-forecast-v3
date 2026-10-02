<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\McpSalesCreateAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class McpSalesDraftTest extends McpGatewayTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.prime_mcp.sales_create_enabled', true);
        config()->set('services.prime_mcp.audit_enabled', true);
        foreach (['product_group' => ['product_id', 'product'], 'priority_level' => ['priority_id', 'priority'], 'source_of_the_budget' => ['Source_budget_id', 'Source_budge']] as $table => [$id, $name]) {
            Schema::create($table, function (Blueprint $table) use ($id, $name) {
                $table->increments($id);
                $table->string($name);
            });
            DB::table($table)->insert([$id => 1, $name => 'Demo choice']);
        }
        Schema::table('transactional', function (Blueprint $table) {
            foreach (['Product_id', 'priority_id', 'Source_budget_id', 'Step_id', 'present', 'budgeted', 'tor', 'bidding', 'win', 'lost'] as $column) {
                $table->integer($column)->nullable();
            }
            foreach (['remark', 'contact_person', 'contact_phone', 'contact_email', 'contact_note', 'date_of_closing_of_sale', 'sales_can_be_close'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        DB::table('step')->insert([
            ['level_id' => 1, 'level' => 'Present', 'orderlv' => 1],
            ['level_id' => 4, 'level' => 'Budgeted', 'orderlv' => 2],
            ['level_id' => 3, 'level' => 'Bidding', 'orderlv' => 4],
        ]);
        (require database_path('migrations/2026_10_02_000001_create_mcp_sales_drafts_table.php'))->up();
    }

    private function actor(array $abilities = ['mcp:read', 'mcp:sales:create'], int $role = 3): array
    {
        $id = DB::table('user')->insertGetId(['email' => Str::uuid().'@example.test', 'password' => 'unused', 'role_id' => $role, 'is_active' => true, 'nname' => 'ทดสอบ', 'surename' => 'เซลล์']);
        DB::table('transactional_team')->insert(['user_id' => $id, 'team_id' => 10]);
        config()->set('services.prime_mcp.sales_create_user_ids', array_merge(config('services.prime_mcp.sales_create_user_ids', []), [$id]));
        $user = User::findOrFail($id);
        $token = $user->createToken('write-test', $abilities, now()->addHour());

        return [$user, ['X-Prime-MCP-Key' => 'demo-service-key-for-tests', 'Authorization' => 'Bearer '.$token->plainTextToken]];
    }

    private function project(array $overrides = []): array
    {
        return array_replace([
            'Product_detail' => 'โครงการทดสอบ MCP', 'company_id' => 1, 'Product_id' => 1,
            'Source_budget_id' => 1, 'priority_id' => 1, 'team_id' => 10, 'product_value' => 1200.50,
            'fiscalyear' => 2026, 'contact_start_date' => '2026-10-01',
            'steps' => [['level_id' => 3, 'date' => '2026-10-03'], ['level_id' => 4, 'date' => '2026-10-02']],
            'contact_email' => 'private@example.test',
        ], $overrides);
    }

    private function prepare(array $headers, array $project = [], ?string $key = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders($headers)->postJson('/api/mcp/v1/sales-drafts', [
            'idempotency_key' => $key ?? (string) Str::uuid(), 'project' => $this->project($project),
        ]);
    }

    public function test_prepare_does_not_create_project_and_browser_confirm_is_idempotent(): void
    {
        [$user, $headers] = $this->actor();
        $key = (string) Str::uuid();
        $draft = $this->prepare($headers, [], $key)->assertOk()->assertJsonPath('data.status', 'pending_confirmation')->json('data.draft_id');
        $this->prepare($headers, [], $key)->assertOk()->assertJsonPath('data.draft_id', $draft);
        $this->assertDatabaseCount('transactional', 0);
        $this->assertStringNotContainsString('private@example.test', DB::table('mcp_sales_drafts')->value('payload'));
        $this->assertStringNotContainsString('private@example.test', json_encode(DB::table('mcp_audit_logs')->get()));
        $this->flushHeaders()->actingAs($user, 'web')->get('/mcp/sales-drafts/'.$draft)->assertOk()->assertSee('โครงการทดสอบ MCP');
        $this->post('/mcp/sales-drafts/'.$draft, ['confirm' => '1'])->assertRedirect();
        $this->post('/mcp/sales-drafts/'.$draft, ['confirm' => '1'])->assertRedirect();
        $this->assertDatabaseCount('transactional', 1);
        $this->assertDatabaseHas('transactional', ['user_id' => $user->user_id, 'team_id' => 10, 'Step_id' => 3, 'product_value' => 1200.50]);
        $this->assertDatabaseCount('transactional_step', 2);
        $this->assertSame(3, DB::table('transactional_step')->orderByDesc('transacstep_id')->value('level_id'));
        $this->assertDatabaseHas('mcp_audit_logs', ['endpoint' => '/mcp/sales-drafts/project_created']);
    }

    public function test_read_token_and_wildcard_token_cannot_create(): void
    {
        foreach ([['mcp:read'], ['*']] as $abilities) {
            [, $headers] = $this->actor($abilities);
            $this->prepare($headers)->assertForbidden();
        }
        $this->assertDatabaseCount('mcp_sales_drafts', 0);
    }

    public function test_create_is_disabled_outside_allowlist_and_in_production(): void
    {
        [, $headers] = $this->actor();
        config()->set('services.prime_mcp.sales_create_user_ids', []);
        $this->prepare($headers)->assertForbidden();
        [$user, $headers] = $this->actor();
        config()->set('services.prime_mcp.sales_create_enabled', false);
        $this->prepare($headers)->assertForbidden();
        config()->set('services.prime_mcp.sales_create_enabled', true);
        $this->app->instance('env', 'production');
        $this->prepare($headers)->assertForbidden();
    }

    public function test_owner_override_cross_team_and_invalid_data_are_rejected(): void
    {
        [, $headers] = $this->actor();
        $this->prepare($headers, ['user_id' => 999])->assertUnprocessable();
        $this->prepare($headers, ['team_id' => 20])->assertForbidden();
        $this->prepare($headers, ['product_value' => -1])->assertUnprocessable();
        $this->prepare($headers, ['company_id' => 999])->assertUnprocessable();
        $this->prepare($headers, ['steps' => [['level_id' => 1, 'date' => '2026-09-30']]])->assertUnprocessable();
        $this->prepare($headers, ['steps' => [['level_id' => 4, 'date' => '2026-10-04'], ['level_id' => 3, 'date' => '2026-10-02']]])->assertUnprocessable();
        $this->assertDatabaseCount('mcp_sales_drafts', 0);
    }

    public function test_idempotency_key_cannot_change_payload_and_other_user_cannot_access_draft(): void
    {
        [, $headers] = $this->actor();
        $key = (string) Str::uuid();
        $id = $this->prepare($headers, [], $key)->assertOk()->json('data.draft_id');
        $this->prepare($headers, ['product_value' => 2000], $key)->assertStatus(409);
        [$other, $otherHeaders] = $this->actor();
        $this->app['auth']->forgetGuards();
        $this->withHeaders($otherHeaders)->getJson('/api/mcp/v1/sales-drafts/'.$id)->assertNotFound();
        $this->flushHeaders()->actingAs($other, 'web')->post('/mcp/sales-drafts/'.$id, ['confirm' => 1])->assertNotFound();
        $this->assertDatabaseCount('transactional', 0);
    }

    public function test_expiry_revocation_and_team_removal_are_checked_at_confirmation(): void
    {
        foreach (['expired', 'revoked', 'team_removed'] as $case) {
            [$user, $headers] = $this->actor();
            $id = $this->prepare($headers)->assertOk()->json('data.draft_id');
            if ($case === 'expired') {
                DB::table('mcp_sales_drafts')->where('id', $id)->update(['expires_at' => now()->subMinute()]);
            }
            if ($case === 'revoked') {
                $user->tokens()->delete();
            }
            if ($case === 'team_removed') {
                DB::table('transactional_team')->where('user_id', $user->user_id)->delete();
            }
            $this->flushHeaders()->actingAs($user, 'web')->post('/mcp/sales-drafts/'.$id, ['confirm' => 1])->assertStatus($case === 'expired' ? 410 : 403);
        }
        $this->assertDatabaseCount('transactional', 0);
    }

    public function test_audit_failure_rolls_back_project_and_confirmation_requires_checkbox(): void
    {
        [$user, $headers] = $this->actor();
        $id = $this->prepare($headers)->assertOk()->json('data.draft_id');
        $this->flushHeaders()->actingAs($user, 'web')->postJson('/mcp/sales-drafts/'.$id, [])->assertUnprocessable();
        Schema::drop('mcp_audit_logs');
        $this->postJson('/mcp/sales-drafts/'.$id, ['confirm' => 1])->assertStatus(500);
        $this->assertDatabaseCount('transactional', 0);
        $this->assertDatabaseCount('transactional_step', 0);
        $this->assertNull(DB::table('mcp_sales_drafts')->where('id', $id)->value('project_id'));
    }

    public function test_team_admin_creates_own_project_but_admin_is_not_a_sales_writer(): void
    {
        [$user, $headers] = $this->actor(['mcp:read', McpSalesCreateAccess::ABILITY], 2);
        $id = $this->prepare($headers)->assertOk()->json('data.draft_id');
        $this->flushHeaders()->actingAs($user, 'web')->post('/mcp/sales-drafts/'.$id, ['confirm' => 1])->assertRedirect();
        $this->assertDatabaseHas('transactional', ['user_id' => $user->user_id]);
        [, $headers] = $this->actor(['mcp:read', McpSalesCreateAccess::ABILITY], 1);
        $this->prepare($headers)->assertForbidden();
    }

    public function test_browser_confirmation_requires_csrf_and_does_not_accept_bearer_as_web_login(): void
    {
        [$user, $headers] = $this->actor();
        $id = $this->prepare($headers)->assertOk()->json('data.draft_id');
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/mcp/sales-drafts/'.$id)->assertUnauthorized();
        // Enable real CSRF verification (Laravel bypasses it under testing env).
        $this->app->instance('env', 'staging');
        $this->flushHeaders()->actingAs($user, 'web')->postJson('/mcp/sales-drafts/'.$id, ['confirm' => 1])->assertStatus(419);
        $this->assertDatabaseCount('transactional', 0);
    }
}
