<?php

namespace Tests\Feature;

use App\Models\Transactional;
use App\Models\User;
use Tests\TestCase;

class SalesUxSmokeTest extends TestCase
{
    public function test_sales_ux_pages_render_with_existing_data(): void
    {
        $user = User::query()
            ->where('role_id', 3)
            ->where('is_active', true)
            ->whereHas('forecastTargets')
            ->first()
            ?? User::query()->where('role_id', 3)->where('is_active', true)->firstOrFail();

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('MY PERFORMANCE')
            ->assertSee('Target / Forecast / Win');

        $this->actingAs($user)
            ->get(route('user.dashboard.table'))
            ->assertOk()
            ->assertSee('MY PIPELINE')
            ->assertSee('โครงการของฉัน');

        $this->actingAs($user)
            ->get(route('user.sales.create'))
            ->assertOk()
            ->assertSee('เพิ่มโครงการใหม่')
            ->assertSee('ข้อมูลโครงการ');

        $transaction = Transactional::query()->where('user_id', $user->getKey())->first();

        if ($transaction) {
            $this->actingAs($user)
                ->get(route('user.sales.edit', $transaction->getKey()))
                ->assertOk()
                ->assertSee('แก้ไขโครงการ')
                ->assertSee($transaction->Product_detail);
        }
    }

    public function test_sales_project_table_endpoint_returns_server_side_payload(): void
    {
        $user = User::query()->where('role_id', 3)->where('is_active', true)->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('user.dashboard.table.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_shared_sales_edit_form_posts_to_the_current_role_route(): void
    {
        $teamAdmin = User::query()->where('user_id', 2)->firstOrFail();
        $admin = User::query()->where('user_id', 1)->firstOrFail();

        $this->actingAs($teamAdmin)
            ->get(route('teamadmin.sales.edit', 1))
            ->assertOk()
            ->assertSee(route('teamadmin.sales.update', 1), false)
            ->assertDontSee('action="'.route('admin.sales.update', 1).'"', false);

        $this->actingAs($admin)
            ->get(route('admin.sales.edit', 1))
            ->assertOk()
            ->assertSee(route('admin.sales.update', 1), false);
    }
}
