<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemainingUxSmokeTest extends TestCase
{
    public function test_profile_pages_render_for_every_role(): void
    {
        $baseUser = $this->teamMember();

        foreach ([
            1 => 'admin.profile',
            2 => 'teamadmin.profile',
            3 => 'user.profile',
        ] as $roleId => $routeName) {
            $user = clone $baseUser;
            $user->role_id = $roleId;
            $user->is_active = true;

            $this->actingAs($user)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('โปรไฟล์และความปลอดภัย')
                ->assertSee('การยืนยันตัวตนสองขั้นตอน');
        }
    }

    public function test_admin_executive_dashboard_and_pipeline_render(): void
    {
        $admin = clone $this->teamMember();
        $admin->role_id = 1;
        $admin->is_active = true;

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('ภาพรวมธุรกิจ')
            ->assertSee('Business overview');

        $this->actingAs($admin)
            ->get(route('admin.dashboard.table'))
            ->assertOk()
            ->assertSee('Sales Pipeline')
            ->assertSee('Sales operations');
    }

    public function test_team_admin_executive_dashboard_and_pipeline_render(): void
    {
        $teamAdmin = clone $this->teamMember();
        $teamAdmin->role_id = 2;
        $teamAdmin->is_active = true;

        $this->actingAs($teamAdmin)
            ->get(route('teamadmin.dashboard'))
            ->assertOk()
            ->assertSee('ภาพรวมทีมขาย')
            ->assertSee('Team performance');

        $this->actingAs($teamAdmin)
            ->get(route('teamadmin.dashboard.table'))
            ->assertOk()
            ->assertSee('Team Pipeline')
            ->assertSee('Team operations');
    }

    private function teamMember(): User
    {
        $userId = DB::table('user as u')
            ->join('transactional_team as tt', 'u.user_id', '=', 'tt.user_id')
            ->where('u.is_active', true)
            ->value('u.user_id');

        return User::query()->findOrFail($userId);
    }
}
