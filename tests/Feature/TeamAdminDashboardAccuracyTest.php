<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TeamAdminDashboardAccuracyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_team_dashboard_uses_the_same_reconcilable_scope_as_admin(): void
    {
        $response = $this->dashboard(['year' => 2026]);

        $response->assertOk();
        $this->assertSame(4_170_000.0, (float) $response->viewData('estimateValue'));
        $this->assertSame(2_500_000.0, (float) $response->viewData('winValue'));
        $this->assertSame(6, (int) $response->viewData('winCount'));
        $this->assertSame(2, (int) $response->viewData('lostCount'));

        $this->assertSame(4_170_000.0, $this->sum(collect($response->viewData('saleStatusValue')), 'total_value'));
        $this->assertSame(16, (int) collect($response->viewData('saleStatus'))->sum('count'));
        $this->assertSame(2_500_000.0, $this->sum(collect($response->viewData('sumByTeam')), 'total_value'));
        $this->assertSame(2_500_000.0, $this->sum(collect($response->viewData('sumByPerson')), 'total_value'));
        $this->assertSame(4_170_000.0, $this->sum(collect($response->viewData('targetForecastWin')), 'forecast_value'));
        $this->assertSame(2_500_000.0, $this->sum(collect($response->viewData('targetForecastWin')), 'win_value'));
        $this->assertSame(1_500_000.0, $this->sum(collect($response->viewData('targetForecastWin')), 'target_value'));
        $this->assertSame(2_500_000.0, (float) collect($response->viewData('cumulativeWin'))->last()->cumulative_win_value);
    }

    public function test_team_dashboard_quarters_reconcile_status_forecast_and_win(): void
    {
        foreach ([1, 2, 3, 4] as $quarter) {
            $response = $this->dashboard(['year' => 2026, 'quarter' => $quarter]);
            $forecast = (float) $response->viewData('estimateValue');
            $win = (float) $response->viewData('winValue');

            $this->assertSame($forecast, $this->sum(collect($response->viewData('saleStatusValue')), 'total_value'));
            $this->assertSame($forecast, $this->sum(collect($response->viewData('targetForecastWin')), 'forecast_value'));
            $this->assertSame($win, $this->sum(collect($response->viewData('targetForecastWin')), 'win_value'));
            $this->assertSame(1_500_000.0, $this->sum(collect($response->viewData('targetForecastWin')), 'target_value'));
        }
    }

    public function test_team_dashboard_drill_downs_match_visible_chart_values(): void
    {
        $cases = [
            [['type' => 'month', 'value' => '2026-08'], 1_950_000.0],
            [['type' => 'team', 'value' => 1], 2_150_000.0],
            [['type' => 'user', 'value' => 4], 1_400_000.0],
            [['type' => 'product', 'value' => 2], 1_400_000.0],
            [['type' => 'company', 'value' => 2], 1_400_000.0],
        ];

        foreach ($cases as [$params, $expected]) {
            $response = $this->actingAs(User::query()->where('role_id', 2)->firstOrFail())
                ->getJson(route('teamadmin.dashboard.chartDetail', ['year' => 2026] + $params));

            $response->assertOk();
            $this->assertSame($expected, collect($response->json())->sum(fn ($row) => (float) $row['product_value']));
        }
    }

    private function dashboard(array $params)
    {
        return $this->actingAs(User::query()->where('role_id', 2)->firstOrFail())
            ->get(route('teamadmin.dashboard', $params));
    }

    private function sum(Collection $rows, string $field): float
    {
        return (float) $rows->sum(fn ($row) => (float) data_get($row, $field));
    }
}
