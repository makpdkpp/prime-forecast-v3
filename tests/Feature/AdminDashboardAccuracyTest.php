<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardAccuracyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_annual_dashboard_metrics_reconcile_for_fiscal_year_2026(): void
    {
        $response = $this->dashboard(['year' => 2026]);

        $response->assertOk();
        $this->assertSame(4_830_000.0, (float) $response->viewData('estimateValue'));
        $this->assertSame(2_820_000.0, (float) $response->viewData('winValue'));
        $this->assertSame(7, (int) $response->viewData('winCount'));
        $this->assertSame(3, (int) $response->viewData('lostCount'));

        $statusValues = collect($response->viewData('saleStatusValue'));
        $statusCounts = collect($response->viewData('saleStatus'));
        $comparison = collect($response->viewData('targetForecastWin'));

        $this->assertSame(4_830_000.0, $this->sum($statusValues, 'total_value'));
        $this->assertSame(20, (int) $statusCounts->sum('count'));
        $this->assertSame(70_000.0, $this->sum($statusValues->where('orderlv', 0), 'total_value'));
        $this->assertSame(4_830_000.0, $this->sum($comparison, 'forecast_value'));
        $this->assertSame(2_820_000.0, $this->sum($comparison, 'win_value'));
        $this->assertSame(1_800_000.0, $this->sum($comparison, 'target_value'));

        $cumulative = collect($response->viewData('cumulativeWin'));
        $this->assertSame(2_820_000.0, (float) $cumulative->last()->cumulative_win_value);
        $this->assertSame(
            ['2026-03' => 400_000.0, '2026-04' => 1_000_000.0, '2026-07' => 1_500_000.0, '2026-08' => 1_950_000.0, '2026-11' => 2_500_000.0, '2026-12' => 2_820_000.0],
            $cumulative->mapWithKeys(fn ($row) => [$row->sale_month => (float) $row->cumulative_win_value])->all()
        );
    }

    public function test_win_breakdowns_all_equal_the_win_kpi(): void
    {
        $response = $this->dashboard(['year' => 2026]);
        $winValue = (float) $response->viewData('winValue');

        foreach (['sumByTeam', 'sumByPerson', 'topProducts', 'topCustomers'] as $key) {
            $this->assertSame($winValue, $this->sum(collect($response->viewData($key)), 'total_value'), $key);
        }

        $this->assertSame(
            ['Alpha' => 2_150_000.0, 'Beta' => 350_000.0, 'Gamma' => 320_000.0],
            collect($response->viewData('sumByTeam'))->mapWithKeys(fn ($row) => [$row->team => (float) $row->total_value])->all()
        );
        $this->assertSame(
            ['Bob' => 1_400_000.0, 'Alice' => 750_000.0, 'Cara' => 350_000.0, 'Dan' => 320_000.0],
            collect($response->viewData('sumByPerson'))->mapWithKeys(fn ($row) => [$row->nname => (float) $row->total_value])->all()
        );
    }

    public function test_quarter_filters_use_one_reconcilable_project_scope(): void
    {
        $expected = [
            1 => [1_300_000, 400_000, 1, 1],
            2 => [1_120_000, 600_000, 2, 1],
            3 => [1_170_000, 950_000, 2, 0],
            4 => [1_240_000, 870_000, 2, 1],
        ];

        foreach ($expected as $quarter => [$forecast, $win, $winCount, $lostCount]) {
            $response = $this->dashboard(['year' => 2026, 'quarter' => $quarter]);
            $statusValues = collect($response->viewData('saleStatusValue'));
            $comparison = collect($response->viewData('targetForecastWin'));

            $this->assertSame((float) $forecast, (float) $response->viewData('estimateValue'), "Q{$quarter} forecast KPI");
            $this->assertSame((float) $forecast, $this->sum($statusValues, 'total_value'), "Q{$quarter} status values");
            $this->assertSame((float) $forecast, $this->sum($comparison, 'forecast_value'), "Q{$quarter} comparison");
            $this->assertSame((float) $win, (float) $response->viewData('winValue'), "Q{$quarter} win");
            $this->assertSame($winCount, (int) $response->viewData('winCount'), "Q{$quarter} win count");
            $this->assertSame($lostCount, (int) $response->viewData('lostCount'), "Q{$quarter} lost count");
            $this->assertSame(1_800_000.0, $this->sum($comparison, 'target_value'), "Q{$quarter} annual target");
        }
    }

    public function test_fiscal_year_filter_is_not_contact_or_step_calendar_year(): void
    {
        $year2026 = $this->dashboard(['year' => 2026]);
        $year2025 = $this->dashboard(['year' => 2025]);

        $this->assertSame(4_830_000.0, (float) $year2026->viewData('estimateValue'));
        $this->assertSame(3_328_000.0, (float) $year2025->viewData('estimateValue'));
        $this->assertSame(1_190_000.0, (float) $year2025->viewData('winValue'));
        $this->assertSame(5, (int) $year2025->viewData('winCount'));
        $this->assertSame(2, (int) $year2025->viewData('lostCount'));
        $this->assertSame(3_328_000.0, $this->sum(collect($year2025->viewData('saleStatusValue')), 'total_value'));
        $this->assertSame(3_328_000.0, $this->sum(collect($year2025->viewData('targetForecastWin')), 'forecast_value'));
        $this->assertSame(1_400_000.0, $this->sum(collect($year2025->viewData('targetForecastWin')), 'target_value'));
        $this->assertSame([2026, 2025], collect($year2026->viewData('availableYears'))->map(fn ($year) => (int) $year)->all());
    }

    public function test_fiscal_year_2025_quarters_reconcile(): void
    {
        $expected = [
            1 => [1_358_000, 220_000, 1, 1],
            2 => [620_000, 160_000, 1, 0],
            3 => [660_000, 310_000, 1, 1],
            4 => [690_000, 500_000, 2, 0],
        ];

        foreach ($expected as $quarter => [$forecast, $win, $winCount, $lostCount]) {
            $response = $this->dashboard(['year' => 2025, 'quarter' => $quarter]);
            $this->assertSame((float) $forecast, (float) $response->viewData('estimateValue'), "FY2025 Q{$quarter} forecast");
            $this->assertSame((float) $forecast, $this->sum(collect($response->viewData('saleStatusValue')), 'total_value'));
            $this->assertSame((float) $win, (float) $response->viewData('winValue'));
            $this->assertSame($winCount, (int) $response->viewData('winCount'));
            $this->assertSame($lostCount, (int) $response->viewData('lostCount'));
        }
    }

    public function test_drill_down_totals_match_the_clicked_chart_values(): void
    {
        $cases = [
            [['type' => 'month', 'value' => '2026-08'], 1_950_000.0],
            [['type' => 'team', 'value' => 1], 2_150_000.0],
            [['type' => 'product', 'value' => 2], 1_400_000.0],
            [['type' => 'company', 'value' => 2], 1_400_000.0],
            [['type' => 'user_win', 'value' => 4], 1_400_000.0],
            [['type' => 'user_forecast', 'value' => 3], 1_340_000.0],
            [['type' => 'step', 'value' => 0, 'value2' => '2026-02'], 70_000.0],
        ];

        foreach ($cases as [$params, $expected]) {
            $response = $this->actingAs($this->admin())->getJson(route('admin.dashboard.chartDetail', ['year' => 2026] + $params));
            $response->assertOk();
            $this->assertSame($expected, collect($response->json())->sum(fn ($row) => (float) $row['product_value']));
        }
    }

    public function test_soft_deleted_projects_are_excluded_and_new_values_are_not_stale(): void
    {
        $before = $this->dashboard(['year' => 2026]);
        $this->assertSame(4_830_000.0, (float) $before->viewData('estimateValue'));

        DB::table('transactional')->insert([
            'user_id' => 3, 'company_id' => 1, 'Product_id' => 1, 'Product_detail' => 'Live cache test',
            'Step_id' => 1, 'Source_budget_id' => 1, 'fiscalyear' => 2026,
            'present' => 0, 'budgeted' => 0, 'tor' => 0, 'bidding' => 0, 'win' => 0, 'lost' => 0,
            'team_id' => 1, 'contact_start_date' => '2026-01-20', 'priority_id' => 1,
            'product_value' => 10_000, 'remark' => 'Cache verification',
        ]);

        $after = $this->dashboard(['year' => 2026]);
        $this->assertSame(4_840_000.0, (float) $after->viewData('estimateValue'));
        $this->assertSame(4_840_000.0, $this->sum(collect($after->viewData('saleStatusValue')), 'total_value'));
    }

    private function dashboard(array $params)
    {
        return $this->actingAs($this->admin())->get(route('admin.dashboard', $params));
    }

    private function admin(): User
    {
        return User::query()->where('role_id', 1)->firstOrFail();
    }

    private function sum(Collection $rows, string $field): float
    {
        return (float) $rows->sum(fn ($row) => (float) data_get($row, $field));
    }
}
