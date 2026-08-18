<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardAccuracySeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin.v3@test.local';
    public const TEST_PASSWORD = 'PrimeTest123!';

    public function run(): void
    {
        $now = now();

        DB::table('industry_group')->insert([
            ['Industry_id' => 1, 'Industry' => 'Government', 'created_at' => $now, 'updated_at' => $now],
            ['Industry_id' => 2, 'Industry' => 'Private', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('role_catalog')->insert([
            ['role_id' => 1, 'role' => 'Admin', 'created_at' => $now, 'updated_at' => $now],
            ['role_id' => 2, 'role' => 'Team Admin', 'created_at' => $now, 'updated_at' => $now],
            ['role_id' => 3, 'role' => 'Sale', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('position')->insert([
            ['position_id' => 1, 'position' => 'Administrator', 'created_at' => $now, 'updated_at' => $now],
            ['position_id' => 2, 'position' => 'Sales Manager', 'created_at' => $now, 'updated_at' => $now],
            ['position_id' => 3, 'position' => 'Sales Executive', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('team_catalog')->insert([
            ['team_id' => 1, 'team' => 'Alpha', 'created_at' => $now, 'updated_at' => $now],
            ['team_id' => 2, 'team' => 'Beta', 'created_at' => $now, 'updated_at' => $now],
            ['team_id' => 3, 'team' => 'Gamma', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('company_catalog')->insert([
            ['company_id' => 1, 'company' => 'Government A', 'Industry_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['company_id' => 2, 'company' => 'Government B', 'Industry_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['company_id' => 3, 'company' => 'Private C', 'Industry_id' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['company_id' => 4, 'company' => 'University D', 'Industry_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('product_group')->insert([
            ['product_id' => 1, 'product' => 'Cyber Security', 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => 2, 'product' => 'Cloud', 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => 3, 'product' => 'Hardware', 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => 4, 'product' => 'MA', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('priority_level')->insert([
            ['priority_id' => 1, 'priority' => 'High', 'created_at' => $now, 'updated_at' => $now],
            ['priority_id' => 2, 'priority' => 'Medium', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('source_of_the_budget')->insert([
            ['Source_budget_id' => 1, 'Source_budge' => 'Government Budget', 'created_at' => $now, 'updated_at' => $now],
            ['Source_budget_id' => 2, 'Source_budge' => 'Private Budget', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('step')->insert([
            ['level_id' => 1, 'level' => '1.นำเสนอ Solution', 'orderlv' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['level_id' => 2, 'level' => '2.ตั้งงบประมาณ', 'orderlv' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['level_id' => 4, 'level' => '3.ร่าง TOR', 'orderlv' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['level_id' => 3, 'level' => '4.Bidding / เสนอราคา', 'orderlv' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['level_id' => 5, 'level' => '5.WIN', 'orderlv' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['level_id' => 6, 'level' => '6.LOST', 'orderlv' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $password = Hash::make(self::TEST_PASSWORD);
        DB::table('user')->insert([
            $this->user(1, self::ADMIN_EMAIL, 'Admin', 'V3', 1, 1, $password),
            $this->user(2, 'teamadmin.v3@test.local', 'Team', 'Admin', 2, 2, $password),
            $this->user(3, 'alice.v3@test.local', 'Alice', 'Alpha', 3, 3, $password),
            $this->user(4, 'bob.v3@test.local', 'Bob', 'Alpha', 3, 3, $password),
            $this->user(5, 'cara.v3@test.local', 'Cara', 'Beta', 3, 3, $password),
            $this->user(6, 'dan.v3@test.local', 'Dan', 'Gamma', 3, 3, $password),
        ]);

        DB::table('transactional_team')->insert([
            ['user_id' => 2, 'team_id' => 1], ['user_id' => 2, 'team_id' => 2],
            ['user_id' => 3, 'team_id' => 1], ['user_id' => 4, 'team_id' => 1],
            ['user_id' => 5, 'team_id' => 2], ['user_id' => 6, 'team_id' => 3],
        ]);

        foreach ([
            [3, 2025, 400000], [4, 2025, 300000], [5, 2025, 500000], [6, 2025, 200000],
            [3, 2026, 500000], [4, 2026, 400000], [5, 2026, 600000], [6, 2026, 300000],
        ] as [$userId, $year, $target]) {
            DB::table('user_forecast_target')->insert([
                'user_id' => $userId, 'fiscal_year' => $year, 'target_value' => $target,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $projects = [
            [1, 3, 1, 1, 1, 100000, 2026, '2026-01-10', 1, '2026-01-15'],
            [2, 3, 1, 2, 2, 200000, 2026, '2026-02-01', 4, '2026-02-10'],
            [3, 4, 1, 3, 1, 300000, 2026, '2026-03-01', 3, '2026-03-05'],
            [4, 4, 1, 1, 3, 400000, 2026, '2026-03-10', 5, '2026-03-20'],
            [5, 5, 2, 4, 2, 150000, 2026, '2026-03-12', 6, '2026-03-25'],
            [6, 3, 1, 1, 1, 250000, 2026, '2026-04-01', 5, '2026-04-10'],
            [7, 5, 2, 2, 2, 350000, 2026, '2026-04-05', 5, '2026-04-20'],
            [8, 5, 2, 3, 3, 120000, 2026, '2026-05-01', 2, '2026-05-05'],
            [9, 6, 3, 4, 4, 180000, 2026, '2026-05-10', 6, '2026-05-15'],
            [10, 4, 1, 1, 1, 220000, 2026, '2026-06-01', 3, '2026-06-10'],
            [11, 3, 1, 2, 2, 500000, 2026, '2026-07-01', 5, '2026-07-07'],
            [12, 4, 1, 3, 3, 450000, 2026, '2026-08-01', 5, '2026-08-08'],
            [13, 5, 2, 4, 4, 130000, 2026, '2026-08-10', 4, '2026-08-15'],
            [14, 6, 3, 1, 1, 90000, 2026, '2026-09-01', 1, '2026-09-05'],
            [15, 3, 1, 1, 1, 210000, 2026, '2026-10-01', 6, '2026-10-10'],
            [16, 4, 1, 2, 2, 550000, 2026, '2026-11-01', 5, '2026-11-11'],
            [17, 5, 2, 3, 3, 160000, 2026, '2026-11-10', 3, '2026-11-20'],
            [18, 6, 3, 4, 4, 320000, 2026, '2026-12-01', 5, '2026-12-12'],
            [19, 6, 3, 4, 4, 70000, 2026, '2026-02-15', null, null],
            [20, 3, 1, 1, 1, 999000, 2026, '2026-06-01', 5, '2026-06-15', '2026-06-30 00:00:00'],
            [21, 3, 1, 2, 2, 80000, 2026, '2025-12-15', 1, '2026-01-05'],
            [22, 4, 1, 3, 3, 888000, 2025, '2026-01-02', 1, '2026-01-10'],
            [23, 3, 1, 1, 1, 120000, 2025, '2025-01-10', 1, '2025-01-15'],
            [24, 4, 1, 2, 2, 220000, 2025, '2025-02-10', 5, '2025-02-20'],
            [25, 5, 2, 3, 3, 130000, 2025, '2025-03-10', 6, '2025-03-18'],
            [26, 3, 1, 1, 2, 180000, 2025, '2025-04-05', 2, '2025-04-12'],
            [27, 4, 1, 2, 3, 280000, 2025, '2025-05-08', 3, '2025-05-20'],
            [28, 6, 3, 4, 4, 160000, 2025, '2025-06-10', 5, '2025-06-25'],
            [29, 3, 1, 1, 1, 310000, 2025, '2025-07-01', 5, '2025-07-15'],
            [30, 5, 2, 2, 2, 210000, 2025, '2025-08-05', 4, '2025-08-18'],
            [31, 6, 3, 3, 3, 140000, 2025, '2025-09-02', 6, '2025-09-19'],
            [32, 4, 1, 4, 4, 260000, 2025, '2025-10-03', 5, '2025-10-17'],
            [33, 5, 2, 3, 1, 190000, 2025, '2025-11-04', 3, '2025-11-20'],
            [34, 6, 3, 2, 2, 240000, 2025, '2025-12-01', 5, '2025-12-15'],
        ];

        foreach ($projects as $project) {
            $this->project(...$project);
        }
    }

    private function user(int $id, string $email, string $name, string $surname, int $role, int $position, string $password): array
    {
        return [
            'user_id' => $id, 'email' => $email, 'password' => $password, 'role_id' => $role,
            'nname' => $name, 'surename' => $surname, 'position_id' => $position,
            'avatar_path' => null, 'is_active' => true, 'two_factor_enabled' => false,
            'reset_token' => null, 'token_expiry' => null,
        ];
    }

    private function project(
        int $id,
        int $userId,
        int $teamId,
        int $companyId,
        int $productId,
        float $value,
        int $fiscalYear,
        string $contactDate,
        ?int $stepId,
        ?string $stepDate,
        ?string $deletedAt = null,
    ): void {
        DB::table('transactional')->insert([
            'transac_id' => $id, 'user_id' => $userId, 'company_id' => $companyId,
            'Product_id' => $productId, 'Product_detail' => sprintf('Fixture Project %02d', $id),
            'Step_id' => $stepId ?? 1, 'Source_budget_id' => 1, 'fiscalyear' => $fiscalYear,
            'present' => 0, 'budgeted' => 0, 'tor' => 0, 'bidding' => 0, 'win' => 0, 'lost' => 0,
            'team_id' => $teamId, 'contact_start_date' => $contactDate,
            'priority_id' => 1, 'product_value' => $value, 'remark' => 'Deterministic dashboard fixture',
            'created_at' => $contactDate, 'updated_at' => $contactDate, 'deleted_at' => $deletedAt,
        ]);

        if ($stepId !== null && $stepDate !== null) {
            DB::table('transactional_step')->insert([
                'transac_id' => $id, 'level_id' => $stepId, 'date' => $stepDate,
            ]);
        }
    }
}
