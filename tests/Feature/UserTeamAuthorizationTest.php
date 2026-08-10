<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserTeamAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('company_catalog', function (Blueprint $table) {
            $table->integer('company_id')->primary();
        });
        Schema::create('product_group', function (Blueprint $table) {
            $table->integer('product_id')->primary();
        });
        Schema::create('source_of_the_budget', function (Blueprint $table) {
            $table->integer('Source_budget_id')->primary();
        });
        Schema::create('priority_level', function (Blueprint $table) {
            $table->integer('priority_id')->primary();
        });
        Schema::create('team_catalog', function (Blueprint $table) {
            $table->integer('team_id')->primary();
        });
        Schema::create('transactional_team', function (Blueprint $table) {
            $table->integer('user_id');
            $table->integer('team_id');
        });

        DB::table('company_catalog')->insert(['company_id' => 1]);
        DB::table('product_group')->insert(['product_id' => 1]);
        DB::table('source_of_the_budget')->insert(['Source_budget_id' => 1]);
        DB::table('priority_level')->insert(['priority_id' => 1]);
        DB::table('team_catalog')->insert([['team_id' => 10], ['team_id' => 20]]);
        DB::table('transactional_team')->insert(['user_id' => 7, 'team_id' => 10]);
    }

    public function test_user_cannot_create_a_sale_for_another_team(): void
    {
        $user = new User();
        $user->user_id = 7;
        $user->role_id = 3;
        $user->is_active = true;

        $response = $this->actingAs($user)->post('/user/sales', [
            'Product_detail' => 'Authorization test',
            'company_id' => 1,
            'product_value' => '1000',
            'Source_budget_id' => 1,
            'fiscalyear' => 2026,
            'Product_id' => 1,
            'team_id' => 20,
            'priority_id' => 1,
            'contact_start_date' => '2026-08-10',
        ]);

        $response->assertForbidden();
    }
}
