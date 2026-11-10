<?php

namespace Tests\Feature;

use App\Mail\UserInvitation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminMcpCreatePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        config()->set('services.prime_mcp.sales_create_user_ids', []);

        Schema::create('user', function (Blueprint $table) {
            $table->increments('user_id');
            foreach (['email', 'password', 'nname', 'surename'] as $column) {
                $table->string($column);
            }
            $table->integer('role_id');
            $table->integer('position_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('reset_token')->nullable();
            $table->timestamp('token_expiry')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_code')->nullable();
            $table->timestamp('two_factor_expires_at')->nullable();
            $table->softDeletes();
        });
        $this->migration()->up();
        Schema::create('transactional_team', function (Blueprint $table) {
            $table->integer('team_id');
            $table->integer('user_id');
        });
        Schema::create('user_forecast_target', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('fiscal_year');
            $table->decimal('target_value');
            $table->timestamps();
        });
        foreach (['role_catalog' => 'role', 'position' => 'position', 'team_catalog' => 'team'] as $name => $column) {
            Schema::create($name, function (Blueprint $table) use ($column) {
                $table->increments($column.'_id');
                $table->string($column);
            });
        }
        DB::table('role_catalog')->insert([
            ['role_id' => 1, 'role' => 'Admin'], ['role_id' => 2, 'role' => 'Team Admin'], ['role_id' => 3, 'role' => 'Sales'],
        ]);
        DB::table('position')->insert(['position_id' => 1, 'position' => 'Sales']);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_09_000001_add_mcp_sales_create_enabled_to_user.php');
    }

    private function user(int $role = 3): User
    {
        return User::create([
            'email' => uniqid().'@example.test', 'password' => 'unused', 'nname' => 'Test',
            'surename' => 'User', 'role_id' => $role, 'position_id' => 1, 'is_active' => true,
        ]);
    }

    private function payload(User $user, mixed $enabled): array
    {
        return [
            'email' => $user->email, 'nname' => $user->nname, 'surename' => $user->surename,
            'role_id' => $user->role_id, 'position_id' => 1, 'mcp_sales_create_enabled' => $enabled,
        ];
    }

    public function test_admin_can_enable_and_disable_create_and_views_show_setting(): void
    {
        $admin = $this->user(1);
        $sales = $this->user();
        $this->actingAs($admin)->get('/admin/users/create')->assertOk()->assertSee('mcp_sales_create_enabled');
        $this->get('/admin/users/'.$sales->user_id.'/edit')->assertOk()->assertSee('mcp_sales_create_enabled');
        foreach ([1, 0] as $enabled) {
            $this->put('/admin/users/'.$sales->user_id, $this->payload($sales, $enabled))->assertRedirect(route('admin.users.index'));
            $this->assertSame((bool) $enabled, $sales->fresh()->mcp_sales_create_enabled);
        }
        $this->get('/admin/users')->assertOk()->assertSee('MCP Create');
    }

    public function test_admin_can_create_invited_user_with_create_permission(): void
    {
        Mail::fake();
        $admin = $this->user(1);
        $this->actingAs($admin)->post('/admin/users', [
            'email' => 'invited@example.test', 'nname' => 'New', 'surename' => 'Sales',
            'role_id' => 3, 'position_id' => 1, 'mcp_sales_create_enabled' => 1,
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('user', ['email' => 'invited@example.test', 'mcp_sales_create_enabled' => 1, 'is_active' => 0]);
        Mail::assertSent(UserInvitation::class);
    }

    public function test_non_admin_cannot_change_permission(): void
    {
        $sales = $this->user();
        foreach ([2, 3] as $role) {
            $this->actingAs($this->user($role))->put('/admin/users/'.$sales->user_id, $this->payload($sales, 1))->assertRedirect();
            $this->assertFalse($sales->fresh()->mcp_sales_create_enabled);
        }
    }

    public function test_invalid_setting_is_rejected_and_admin_role_cannot_receive_create(): void
    {
        $admin = $this->user(1);
        $sales = $this->user();
        $this->actingAs($admin)->putJson('/admin/users/'.$sales->user_id, $this->payload($sales, 'invalid'))
            ->assertUnprocessable()->assertJsonValidationErrors('mcp_sales_create_enabled');
        $this->put('/admin/users/'.$admin->user_id, $this->payload($admin, 1))->assertRedirect();
        $this->assertFalse($admin->fresh()->mcp_sales_create_enabled);
        $sales->update(['mcp_sales_create_enabled' => true]);
        $payload = $this->payload($sales, 1);
        $payload['role_id'] = 1;
        $this->put('/admin/users/'.$sales->user_id, $payload)->assertRedirect();
        $this->assertFalse($sales->fresh()->mcp_sales_create_enabled);
    }

    public function test_migration_imports_legacy_grants_only_for_supported_roles_and_can_roll_back(): void
    {
        $sales = $this->user();
        $teamAdmin = $this->user(2);
        $admin = $this->user(1);
        $other = $this->user();
        $this->migration()->down();
        config()->set('services.prime_mcp.sales_create_user_ids', [$sales->user_id, $teamAdmin->user_id, $admin->user_id]);
        $this->migration()->up();
        $this->assertTrue($sales->fresh()->mcp_sales_create_enabled);
        $this->assertTrue($teamAdmin->fresh()->mcp_sales_create_enabled);
        $this->assertFalse($admin->fresh()->mcp_sales_create_enabled);
        $this->assertFalse($other->fresh()->mcp_sales_create_enabled);
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('user', 'mcp_sales_create_enabled'));
    }
}
