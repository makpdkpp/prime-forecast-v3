<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserDashboardAccuracyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_dashboard_reconciles_forecast_win_target_and_product_totals(): void
    {
        $response = $this->dashboard(['year' => 2026]);

        $response->assertOk();
        $comparison = $response->viewData('winForecastData');
        $stepRows = collect($response->viewData('saleStepData'));
        $stepTotal = $stepRows->sum(function ($row) {
            return (float) $row->present_value
                + (float) $row->budgeted_value
                + (float) $row->tor_value
                + (float) $row->bidding_value
                + (float) $row->win_value
                + (float) $row->lost_value;
        });

        $this->assertSame(1_340_000.0, (float) $comparison->Forecast);
        $this->assertSame(750_000.0, (float) $comparison->Win);
        $this->assertSame(500_000.0, (float) $comparison->Target);
        $this->assertSame(1_340_000.0, (float) $stepTotal);
        $this->assertSame(
            1_340_000.0,
            (float) collect($response->viewData('sumValuePercentData'))->sum(fn ($row) => (float) $row->sum_value)
        );
        $this->assertSame(6, (int) $response->viewData('projectCount'));
    }

    public function test_sales_dashboard_quarters_use_latest_activity_date(): void
    {
        $expected = [
            1 => [380_000.0, 0.0],
            2 => [250_000.0, 250_000.0],
            3 => [500_000.0, 500_000.0],
            4 => [210_000.0, 0.0],
        ];

        foreach ($expected as $quarter => [$forecast, $win]) {
            $response = $this->dashboard(['year' => 2026, 'quarter' => $quarter]);
            $comparison = $response->viewData('winForecastData');

            $this->assertSame($forecast, (float) $comparison->Forecast, "Q{$quarter} forecast");
            $this->assertSame($win, (float) $comparison->Win, "Q{$quarter} win");
            $this->assertSame(500_000.0, (float) $comparison->Target, "Q{$quarter} annual target");
            $this->assertSame(
                $forecast,
                (float) collect($response->viewData('sumValuePercentData'))->sum(fn ($row) => (float) $row->sum_value),
                "Q{$quarter} product total"
            );
        }
    }

    public function test_sales_dashboard_drill_downs_reconcile_with_visible_values(): void
    {
        $user = User::query()->where('user_id', 3)->firstOrFail();
        $cases = [
            [['type' => 'user_forecast', 'value' => 3], 1_340_000.0],
            [['type' => 'user_win', 'value' => 3], 750_000.0],
            [['type' => 'step', 'value' => 1, 'value2' => '2026-01'], 180_000.0],
            [['type' => 'step', 'value' => 3, 'value2' => '2026-02'], 200_000.0],
            [['type' => 'product', 'value' => 1], 560_000.0],
        ];

        foreach ($cases as [$params, $expected]) {
            $response = $this->actingAs($user)
                ->getJson(route('user.dashboard.chartDetail', ['year' => 2026] + $params));

            $response->assertOk();
            $this->assertSame($expected, collect($response->json())->sum(fn ($row) => (float) $row['product_value']));
        }

        $wins = $this->actingAs($user)->getJson(route('user.dashboard.winProjects', ['year' => 2026]));
        $wins->assertOk();
        $this->assertSame(750_000.0, collect($wins->json())->sum(fn ($row) => (float) $row['product_value']));
    }

    private function dashboard(array $params)
    {
        return $this->actingAs(User::query()->where('user_id', 3)->firstOrFail())
            ->get(route('user.dashboard', $params));
    }
}
