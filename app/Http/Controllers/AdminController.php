<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Transactional;
use App\Models\User;
use App\Exports\BiddingReportExport;
use App\Exports\ContractReportExport;
use App\Reports\BiddingReportPdf;
use App\Reports\ContractReportPdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\ProjectTimelineValidator;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $yearFilter = $this->resolveDashboardYearFilter($request);
        $year = $yearFilter['year'];
        $selectedYear = $yearFilter['selected'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;

        $availableYears = DB::table('transactional')
            ->whereNull('deleted_at')
            ->whereNotNull('fiscalyear')
            ->distinct()
            ->orderByDesc('fiscalyear')
            ->pluck('fiscalyear');

        $base = $this->accurateDashboardBaseQuery($year, $quarter);
        $effectiveDate = $this->dashboardEffectiveDateSql();

        $estimateValue = (clone $base)->sum('t.product_value');
        $winValue = (clone $base)->where('current_step.orderlv', 5)->sum('t.product_value');
        $winCount = (clone $base)->where('current_step.orderlv', 5)->count('t.transac_id');
        $lostCount = (clone $base)->where('current_step.orderlv', 6)->count('t.transac_id');

        $monthlyWins = (clone $base)
            ->where('current_step.orderlv', 5)
            ->selectRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') as sale_month, SUM(t.product_value) as monthly_value")
            ->groupByRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m')")
            ->orderBy('sale_month')
            ->get();

        $runningWin = 0.0;
        $cumulativeWin = $monthlyWins->map(function ($row) use (&$runningWin) {
            $runningWin += (float) $row->monthly_value;
            return (object) [
                'sale_month' => $row->sale_month,
                'cumulative_win_value' => $runningWin,
            ];
        });

        $sumByTeam = (clone $base)
            ->join('team_catalog as tc', 't.team_id', '=', 'tc.team_id')
            ->where('current_step.orderlv', 5)
            ->selectRaw('tc.team_id, tc.team, SUM(t.product_value) as total_value')
            ->groupBy('tc.team_id', 'tc.team')
            ->orderByDesc('total_value')
            ->get();

        $sumByPerson = (clone $base)
            ->join('user as u', 't.user_id', '=', 'u.user_id')
            ->where('current_step.orderlv', 5)
            ->whereNull('u.deleted_at')
            ->selectRaw('u.user_id, u.nname, u.surename, SUM(t.product_value) as total_value')
            ->groupBy('u.user_id', 'u.nname', 'u.surename')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $saleStatus = (clone $base)
            ->selectRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') as sale_month, COALESCE(current_step.orderlv, 0) as orderlv, COALESCE(current_step.level, 'ยังไม่ระบุสถานะ') as level, COUNT(*) as count")
            ->groupByRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m'), COALESCE(current_step.orderlv, 0), COALESCE(current_step.level, 'ยังไม่ระบุสถานะ')")
            ->orderBy('sale_month')
            ->orderBy('orderlv')
            ->get();

        $saleStatusValue = (clone $base)
            ->selectRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') as sale_month, COALESCE(current_step.orderlv, 0) as orderlv, COALESCE(current_step.level, 'ยังไม่ระบุสถานะ') as level, SUM(t.product_value) as total_value")
            ->groupByRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m'), COALESCE(current_step.orderlv, 0), COALESCE(current_step.level, 'ยังไม่ระบุสถานะ')")
            ->orderBy('sale_month')
            ->orderBy('orderlv')
            ->get();

        $topProducts = (clone $base)
            ->join('product_group as pg', 't.Product_id', '=', 'pg.product_id')
            ->where('current_step.orderlv', 5)
            ->selectRaw('pg.product_id, pg.product, SUM(t.product_value) as total_value')
            ->groupBy('pg.product_id', 'pg.product')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $topCustomers = (clone $base)
            ->join('company_catalog as cc', 't.company_id', '=', 'cc.company_id')
            ->where('current_step.orderlv', 5)
            ->selectRaw('cc.company_id, cc.company, SUM(t.product_value) as total_value')
            ->groupBy('cc.company_id', 'cc.company')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $forecastByUser = (clone $base)
            ->selectRaw('t.user_id, SUM(t.product_value) as forecast_value')
            ->groupBy('t.user_id');
        $winByUser = (clone $base)
            ->where('current_step.orderlv', 5)
            ->selectRaw('t.user_id, SUM(t.product_value) as win_value')
            ->groupBy('t.user_id');
        $targetByUser = DB::table('user_forecast_target as uft')
            ->when($year !== null, fn ($query) => $query->where('uft.fiscal_year', $year))
            ->selectRaw('uft.user_id, SUM(uft.target_value) as target_value')
            ->groupBy('uft.user_id');

        $targetForecastWin = DB::table('user as u')
            ->leftJoinSub($targetByUser, 'tgt', 'tgt.user_id', '=', 'u.user_id')
            ->leftJoinSub($forecastByUser, 'fc', 'fc.user_id', '=', 'u.user_id')
            ->leftJoinSub($winByUser, 'wn', 'wn.user_id', '=', 'u.user_id')
            ->where('u.role_id', 3)
            ->whereNull('u.deleted_at')
            ->where(function ($query) {
                $query->whereNotNull('tgt.target_value')
                    ->orWhereNotNull('fc.forecast_value')
                    ->orWhereNotNull('wn.win_value');
            })
            ->selectRaw('u.user_id, u.nname, u.surename, COALESCE(tgt.target_value, 0) as target_value, COALESCE(fc.forecast_value, 0) as forecast_value, COALESCE(wn.win_value, 0) as win_value')
            ->orderByDesc('forecast_value')
            ->get();

        return view('admin.dashboard', compact(
            'estimateValue', 'winValue', 'winCount', 'lostCount', 'cumulativeWin',
            'sumByTeam', 'sumByPerson', 'saleStatus', 'saleStatusValue', 'topProducts',
            'topCustomers', 'targetForecastWin', 'availableYears', 'year', 'selectedYear', 'quarter'
        ));
    }

    /** @deprecated Kept temporarily for comparison with V2 calculations. */
    private function legacyDashboard(Request $request)
    {
        // Get filter parameters
        $yearFilter = $this->resolveDashboardYearFilter($request);
        $year = $yearFilter['year'];
        $selectedYear = $yearFilter['selected'];
        $quarter = $request->get('quarter');
        
        // Get available years from transactional activity date
        $availableYears = Cache::remember('dashboard:admin:availableYears', 120, function () {
            return DB::table('transactional')
                ->selectRaw('YEAR(contact_start_date) as year')
                ->whereNotNull('contact_start_date')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');
        });
        
        // Build base query with filters
        $query = DB::table('transactional');
        $this->applyYearFilterToQuery($query, $year);
        
        $this->applyQuarterFilterToQuery($query, $year, $quarter);
        
        // Get summary statistics
        $estimateValue = (clone $query)->sum('product_value');
        
        // Win value and count (latest step is WIN - level = 5)
        $winParams = [];
        $winWhere = "";
        $this->appendYearSqlFilter($winWhere, $winParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($winWhere, $winParams, $year, $quarter, 'ts_win', 'date');
        $winData = DB::select(" 
            SELECT 
                COALESCE(SUM(t.product_value), 0) as win_value,
                COUNT(*) as win_count
            FROM transactional t
            JOIN (
                SELECT ts.transac_id, ts.date
                FROM transactional_step ts
                JOIN step s ON s.level_id = ts.level_id
                WHERE s.level = 5
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            ) ts_win ON ts_win.transac_id = t.transac_id
            WHERE 1=1 {$winWhere}
        ", $winParams);
        
        // Lost count (latest step is LOST - level = 6)
        $lostParams = [];
        $lostWhere = "";
        $this->appendYearSqlFilter($lostWhere, $lostParams, $year, 'ts_lost', 'date');
        $this->appendQuarterSqlFilter($lostWhere, $lostParams, $year, $quarter, 'ts_lost', 'date');
        $lostCount = DB::select(" 
            SELECT COUNT(*) as lost_count
            FROM transactional t
            JOIN (
                SELECT ts.transac_id, ts.date
                FROM transactional_step ts
                JOIN step s ON s.level_id = ts.level_id
                WHERE s.level = 6
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            ) ts_lost ON ts_lost.transac_id = t.transac_id
            WHERE 1=1 {$lostWhere}
        ", $lostParams);
        
        $winValue = $winData[0]->win_value ?? 0;
        $winCount = $winData[0]->win_count ?? 0;
        $lostCount = $lostCount[0]->lost_count ?? 0;
        
        // Cumulative win by month (only transactions whose latest step is WIN - level = 5)
        $cumulativeWinParams = [];
        $cumulativeWinWhere = "";
        $this->appendYearSqlFilter($cumulativeWinWhere, $cumulativeWinParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($cumulativeWinWhere, $cumulativeWinParams, $year, $quarter, 'ts_win', 'date');
        $cumulativeWin = Cache::remember('dashboard:admin:cumulativeWin:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($cumulativeWinWhere, $cumulativeWinParams) {
            return DB::select("
                SELECT 
                    monthly.sale_month,
                    SUM(monthly.monthly_value) OVER (ORDER BY monthly.sale_month) as cumulative_win_value
                FROM (
                    SELECT 
                        DATE_FORMAT(ts_win.date, '%Y-%m') as sale_month,
                        SUM(t.product_value) as monthly_value
                    FROM transactional t
                    JOIN (
                        SELECT ts.transac_id, ts.date
                        FROM transactional_step ts
                        JOIN step s ON s.level_id = ts.level_id
                        WHERE s.level = 5
                        AND (ts.transacstep_id, ts.transac_id) IN (
                            SELECT MAX(ts2.transacstep_id), ts2.transac_id
                            FROM transactional_step ts2
                            GROUP BY ts2.transac_id
                        )
                    ) ts_win ON ts_win.transac_id = t.transac_id
                    WHERE 1=1 {$cumulativeWinWhere}
                    GROUP BY DATE_FORMAT(ts_win.date, '%Y-%m')
                ) monthly
                ORDER BY monthly.sale_month
            ", $cumulativeWinParams);
        });
        
        // Sum by team (only transactions whose latest step is WIN - level = 5)
        $sumByTeamParams = [];
        $sumByTeamWhere = "";
        $this->appendYearSqlFilter($sumByTeamWhere, $sumByTeamParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($sumByTeamWhere, $sumByTeamParams, $year, $quarter, 'ts_win', 'date');
        $sumByTeam = Cache::remember('dashboard:admin:sumByTeam:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($sumByTeamWhere, $sumByTeamParams) {
            return DB::select(" 
                SELECT 
                    tc.team_id,
                    tc.team,
                    SUM(t.product_value) as total_value
                FROM transactional t
                JOIN team_catalog tc ON t.team_id = tc.team_id
                JOIN (
                    SELECT ts.transac_id, ts.date
                    FROM transactional_step ts
                    JOIN step s ON s.level_id = ts.level_id
                    WHERE s.level = 5
                    AND (ts.transacstep_id, ts.transac_id) IN (
                        SELECT MAX(ts2.transacstep_id), ts2.transac_id
                        FROM transactional_step ts2
                        GROUP BY ts2.transac_id
                    )
                ) ts_win ON ts_win.transac_id = t.transac_id
                WHERE 1=1 {$sumByTeamWhere}
                GROUP BY tc.team_id, tc.team
                ORDER BY total_value DESC
            ", $sumByTeamParams);
        });
        
        // Sum by person (only transactions whose latest step is WIN - level = 5)
        $sumByPersonParams = [];
        $sumByPersonWhere = "";
        $this->appendYearSqlFilter($sumByPersonWhere, $sumByPersonParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($sumByPersonWhere, $sumByPersonParams, $year, $quarter, 'ts_win', 'date');
        $sumByPerson = Cache::remember('dashboard:admin:sumByPerson:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($sumByPersonWhere, $sumByPersonParams) {
            return DB::select(" 
                SELECT 
                    u.user_id,
                    u.nname,
                    u.surename,
                    SUM(t.product_value) as total_value
                FROM transactional t
                JOIN user u ON t.user_id = u.user_id
                JOIN (
                    SELECT ts.transac_id, ts.date
                    FROM transactional_step ts
                    JOIN step s ON s.level_id = ts.level_id
                    WHERE s.level = 5
                    AND (ts.transacstep_id, ts.transac_id) IN (
                        SELECT MAX(ts2.transacstep_id), ts2.transac_id
                        FROM transactional_step ts2
                        GROUP BY ts2.transac_id
                    )
                ) ts_win ON ts_win.transac_id = t.transac_id
                WHERE 1=1 {$sumByPersonWhere}
                GROUP BY u.user_id, u.nname, u.surename
                ORDER BY total_value DESC
                LIMIT 10
            ", $sumByPersonParams);
        });
        
        // Sale status count by month and step level
        $saleStatusParams = [];
        $saleStatusWhere = "";
        $this->appendYearSqlFilter($saleStatusWhere, $saleStatusParams, $year, 'ts', 'date');
        $this->appendQuarterSqlFilter($saleStatusWhere, $saleStatusParams, $year, $quarter, 'ts', 'date');
        $saleStatus = Cache::remember('dashboard:admin:saleStatus:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($saleStatusParams, $saleStatusWhere) {
            return DB::select(" 
                SELECT 
                    DATE_FORMAT(ts.date, '%Y-%m') as sale_month,
                    s.orderlv,
                    s.level,
                    COUNT(*) as count
                FROM transactional t
                JOIN transactional_step ts ON t.transac_id = ts.transac_id
                JOIN step s ON s.level_id = ts.level_id
                WHERE (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
                AND s.orderlv BETWEEN 1 AND 6
                {$saleStatusWhere}
                GROUP BY DATE_FORMAT(ts.date, '%Y-%m'), s.orderlv, s.level
                ORDER BY sale_month, s.orderlv
            ", $saleStatusParams);
        });
        
        // Sale status value by month and step level
        $saleStatusValueParams = [];
        $saleStatusValueWhere = "";
        $this->appendYearSqlFilter($saleStatusValueWhere, $saleStatusValueParams, $year, 'ts', 'date');
        $this->appendQuarterSqlFilter($saleStatusValueWhere, $saleStatusValueParams, $year, $quarter, 'ts', 'date');
        $saleStatusValue = Cache::remember('dashboard:admin:saleStatusValue:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($saleStatusValueParams, $saleStatusValueWhere) {
            return DB::select(" 
                SELECT 
                    DATE_FORMAT(ts.date, '%Y-%m') as sale_month,
                    s.orderlv,
                    s.level,
                    SUM(t.product_value) as total_value
                FROM transactional t
                JOIN transactional_step ts ON t.transac_id = ts.transac_id
                JOIN step s ON s.level_id = ts.level_id
                WHERE (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
                AND s.orderlv BETWEEN 1 AND 6
                {$saleStatusValueWhere}
                GROUP BY DATE_FORMAT(ts.date, '%Y-%m'), s.orderlv, s.level
                ORDER BY sale_month, s.orderlv
            ", $saleStatusValueParams);
        });
        
        // Top 10 products (only WIN - level = 5)
        $topProductsParams = [];
        $topProductsWhere = "";
        $this->appendYearSqlFilter($topProductsWhere, $topProductsParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($topProductsWhere, $topProductsParams, $year, $quarter, 'ts_win', 'date');
        $topProducts = Cache::remember('dashboard:admin:topProducts:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($topProductsWhere, $topProductsParams) {
            return DB::select(" 
                SELECT 
                    pg.product_id,
                    pg.product,
                    SUM(t.product_value) as total_value
                FROM transactional t
                JOIN product_group pg ON t.Product_id = pg.product_id
                JOIN (
                    SELECT ts.transac_id, ts.date
                    FROM transactional_step ts
                    JOIN step s ON s.level_id = ts.level_id
                    WHERE s.level = 5
                    AND (ts.transacstep_id, ts.transac_id) IN (
                        SELECT MAX(ts2.transacstep_id), ts2.transac_id
                        FROM transactional_step ts2
                        GROUP BY ts2.transac_id
                    )
                ) ts_win ON ts_win.transac_id = t.transac_id
                WHERE 1=1 {$topProductsWhere}
                GROUP BY pg.Product_id, pg.product
                ORDER BY total_value DESC
                LIMIT 10
            ", $topProductsParams);
        });
        
        // Top 10 customers (only WIN - level = 5)
        $topCustomersParams = [];
        $topCustomersWhere = "";
        $this->appendYearSqlFilter($topCustomersWhere, $topCustomersParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($topCustomersWhere, $topCustomersParams, $year, $quarter, 'ts_win', 'date');
        $topCustomers = Cache::remember('dashboard:admin:topCustomers:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($topCustomersWhere, $topCustomersParams) {
            return DB::select(" 
                SELECT 
                    cc.company_id,
                    cc.company,
                    SUM(t.product_value) as total_value
                FROM transactional t
                JOIN company_catalog cc ON t.company_id = cc.company_id
                JOIN (
                    SELECT ts.transac_id, ts.date
                    FROM transactional_step ts
                    JOIN step s ON s.level_id = ts.level_id
                    WHERE s.level = 5
                    AND (ts.transacstep_id, ts.transac_id) IN (
                        SELECT MAX(ts2.transacstep_id), ts2.transac_id
                        FROM transactional_step ts2
                        GROUP BY ts2.transac_id
                    )
                ) ts_win ON ts_win.transac_id = t.transac_id
                WHERE 1=1 {$topCustomersWhere}
                GROUP BY cc.company_id, cc.company
                ORDER BY total_value DESC
                LIMIT 10
            ", $topCustomersParams);
        });
        
        // Target/Forecast/Win comparison per user
        $tfwForecastWhere = "";
        $tfwWinWhere = "";
        $tfwTargetWhere = "";
        $forecastParams = [];
        $winParams = [];

        $this->appendYearSqlFilter($tfwForecastWhere, $forecastParams, $year, 'ts_latest', 'date');
        $this->appendQuarterSqlFilter($tfwForecastWhere, $forecastParams, $year, $quarter, 'ts_latest', 'date');
        $this->appendYearSqlFilter($tfwWinWhere, $winParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($tfwWinWhere, $winParams, $year, $quarter, 'ts_win', 'date');

        if ($year !== null) {
            $tfwTargetWhere .= " AND uft.fiscal_year = ?";
        }

        // Build params for target subquery
        $targetParams = [];
        if ($year !== null) {
            $targetParams[] = $year;
        }

        $targetForecastWin = Cache::remember('dashboard:admin:targetForecastWin:' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($tfwTargetWhere, $tfwForecastWhere, $tfwWinWhere, $targetParams, $forecastParams, $winParams) {
            return DB::select(" 
                SELECT 
                    u.user_id,
                    u.nname,
                    u.surename,
                    COALESCE(tgt.target_value, 0) as target_value,
                    COALESCE(fc.forecast_value, 0) as forecast_value,
                    COALESCE(wn.win_value, 0) as win_value
                FROM user u
                LEFT JOIN (
                    SELECT uft.user_id, SUM(uft.target_value) as target_value
                    FROM user_forecast_target uft
                    WHERE 1=1 {$tfwTargetWhere}
                    GROUP BY uft.user_id
                ) tgt ON tgt.user_id = u.user_id
                LEFT JOIN (
                    SELECT t.user_id, SUM(t.product_value) as forecast_value
                    FROM transactional t
                    JOIN (
                        SELECT ts.transac_id, ts.date
                        FROM transactional_step ts
                        WHERE (ts.transacstep_id, ts.transac_id) IN (
                            SELECT MAX(ts2.transacstep_id), ts2.transac_id
                            FROM transactional_step ts2
                            GROUP BY ts2.transac_id
                        )
                    ) ts_latest ON ts_latest.transac_id = t.transac_id
                    WHERE 1=1 {$tfwForecastWhere}
                    GROUP BY t.user_id
                ) fc ON fc.user_id = u.user_id
                LEFT JOIN (
                    SELECT t.user_id, SUM(t.product_value) as win_value
                    FROM transactional t
                    JOIN (
                        SELECT ts.transac_id, ts.date
                        FROM transactional_step ts
                        JOIN step s ON s.level_id = ts.level_id
                        WHERE s.level = 5
                        AND (ts.transacstep_id, ts.transac_id) IN (
                            SELECT MAX(ts2.transacstep_id), ts2.transac_id
                            FROM transactional_step ts2
                            GROUP BY ts2.transac_id
                        )
                    ) ts_win ON ts_win.transac_id = t.transac_id
                    WHERE 1=1 {$tfwWinWhere}
                    GROUP BY t.user_id
                ) wn ON wn.user_id = u.user_id
                WHERE (tgt.target_value IS NOT NULL OR fc.forecast_value IS NOT NULL OR wn.win_value IS NOT NULL)
                ORDER BY COALESCE(fc.forecast_value, 0) DESC
            ", array_merge($targetParams, $forecastParams, $winParams));
        });

        return view('admin.dashboard', compact(
            'estimateValue',
            'winValue',
            'winCount',
            'lostCount',
            'cumulativeWin',
            'sumByTeam',
            'sumByPerson',
            'saleStatus',
            'saleStatusValue',
            'topProducts',
            'topCustomers',
            'targetForecastWin',
            'availableYears',
            'year',
            'selectedYear',
            'quarter'
        ));
    }

    public function chartDetail(Request $request)
    {
        $type = (string) $request->query('type');
        $value = $request->query('value');
        $value2 = $request->query('value2');
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;

        $query = $this->accurateDashboardBaseQuery($year, $quarter)
            ->leftJoin('company_catalog as detail_company', 't.company_id', '=', 'detail_company.company_id')
            ->leftJoin('product_group as detail_product', 't.Product_id', '=', 'detail_product.product_id')
            ->leftJoin('user as detail_user', 't.user_id', '=', 'detail_user.user_id')
            ->leftJoin('team_catalog as detail_team', 't.team_id', '=', 'detail_team.team_id');
        $effectiveDate = $this->dashboardEffectiveDateSql();

        switch ($type) {
            case 'month':
                $query->where('current_step.orderlv', 5)
                    ->whereRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') <= ?", [$value]);
                break;
            case 'team':
                $query->where('current_step.orderlv', 5)->where('t.team_id', $value);
                break;
            case 'step':
                $query->whereRaw('COALESCE(current_step.orderlv, 0) = ?', [(int) $value]);
                if ($value2) {
                    $query->whereRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') = ?", [$value2]);
                }
                break;
            case 'user_forecast':
                $query->where('t.user_id', $value);
                break;
            case 'user_win':
                $query->where('current_step.orderlv', 5)->where('t.user_id', $value);
                break;
            case 'product':
                $query->where('current_step.orderlv', 5)->where('t.Product_id', $value);
                break;
            case 'company':
                $query->where('current_step.orderlv', 5)->where('t.company_id', $value);
                break;
            default:
                return response()->json([]);
        }

        $projects = $query
            ->selectRaw("t.transac_id, t.Product_detail, t.product_value, detail_company.company, detail_product.product as product_group, detail_user.nname, detail_user.surename, detail_team.team, COALESCE(current_step.level, 'ยังไม่ระบุสถานะ') as step_name, t.contact_start_date")
            ->orderByDesc('t.product_value')
            ->get();

        return response()->json($projects);
    }

    /** @deprecated Kept temporarily for comparison with V2 drill-down calculations. */
    private function legacyChartDetail(Request $request)
    {
        $type = $request->get('type');
        $value = $request->get('value');
        $value2 = $request->get('value2');
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = $request->get('quarter');

        $params = [];
        $where = "";
        
        // Use the correct date column for year/quarter filter based on chart type
        if ($type === 'step') {
            // Step-based charts: filter by ts.date (transactional_step date)
            $this->appendYearSqlFilter($where, $params, $year, 'ts', 'date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'ts', 'date');
        } else if ($type === 'month') {
            // Month-based WIN charts: skip year/quarter filter here
            // because month value already includes year (e.g. '2026-03')
            // and we filter by ts_w.date in the extraWhere
        } else if (in_array($type, ['team', 'product', 'company', 'user_win'])) {
            // WIN-based charts: filter by wintrans.win_date (WIN step date)
            $this->appendYearSqlFilter($where, $params, $year, 'wintrans', 'win_date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'wintrans', 'win_date');
        } else if ($type === 'user_forecast') {
            // Forecast drill-down: filter by ts_latest.date to match dashboard Forecast query
            $this->appendYearSqlFilter($where, $params, $year, 'ts_latest', 'date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'ts_latest', 'date');
        } else {
            // Other forecast-based charts: filter by t.contact_start_date
            $this->appendYearSqlFilter($where, $params, $year, 't');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 't');
        }

        // WIN subquery — used by most chart types (includes win_date for filtering)
        $winJoin = "
            JOIN (
                SELECT ts.transac_id, ts.date as win_date
                FROM transactional_step ts
                JOIN step s ON s.level_id = ts.level_id
                WHERE s.level = 5
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            ) wintrans ON wintrans.transac_id = t.transac_id
        ";

        // Step subquery — for step-based charts
        $stepJoin = "
            JOIN transactional_step ts ON t.transac_id = ts.transac_id
            JOIN step s ON s.level_id = ts.level_id
            AND (ts.transacstep_id, ts.transac_id) IN (
                SELECT MAX(ts2.transacstep_id), ts2.transac_id
                FROM transactional_step ts2
                GROUP BY ts2.transac_id
            )
        ";

        $extraJoin = "";
        $extraWhere = "";
        $extraParams = [];

        switch ($type) {
            case 'month': // ยอดขายรวม — win by month
                $extraJoin = $winJoin . "
                    JOIN transactional_step ts_w ON ts_w.transac_id = t.transac_id
                    JOIN step s_w ON s_w.level_id = ts_w.level_id AND s_w.level = 5
                    AND (ts_w.transacstep_id, ts_w.transac_id) IN (
                        SELECT MAX(ts2.transacstep_id), ts2.transac_id
                        FROM transactional_step ts2
                        GROUP BY ts2.transac_id
                    )";
                $extraWhere = " AND DATE_FORMAT(ts_w.date, '%Y-%m') = ?";
                $extraParams[] = $value;
                break;
            case 'team': // ยอดขายรายทีม — win by team
                $extraJoin = $winJoin;
                $extraWhere = " AND t.team_id = ?";
                $extraParams[] = $value;
                break;
            case 'step': // สถานะการขาย — by step orderlv + optional month
                $extraJoin = $stepJoin;
                $extraWhere = " AND s.orderlv = ?";
                $extraParams[] = $value;
                if ($value2) {
                    $extraWhere .= " AND DATE_FORMAT(ts.date, '%Y-%m') = ?";
                    $extraParams[] = $value2;
                }
                break;
            case 'user_forecast': // Target/Forecast/Win — all transactions by user (filter by latest step date)
                $extraJoin = "
                    JOIN (
                        SELECT ts.transac_id, ts.date
                        FROM transactional_step ts
                        WHERE (ts.transacstep_id, ts.transac_id) IN (
                            SELECT MAX(ts2.transacstep_id), ts2.transac_id
                            FROM transactional_step ts2
                            GROUP BY ts2.transac_id
                        )
                    ) ts_latest ON ts_latest.transac_id = t.transac_id
                ";
                $extraWhere = " AND t.user_id = ?";
                $extraParams[] = $value;
                break;
            case 'user_win': // Target/Forecast/Win — win only by user
                $extraJoin = $winJoin;
                $extraWhere = " AND t.user_id = ?";
                $extraParams[] = $value;
                break;
            case 'product': // TOP 10 product — win by product
                $extraJoin = $winJoin;
                $extraWhere = " AND t.Product_id = ?";
                $extraParams[] = $value;
                break;
            case 'company': // TOP 10 customer — win by company
                $extraJoin = $winJoin;
                $extraWhere = " AND t.company_id = ?";
                $extraParams[] = $value;
                break;
            default:
                return response()->json([]);
        }

        $allParams = array_merge($params, $extraParams);

        $projects = DB::select("
            SELECT 
                t.transac_id,
                t.Product_detail,
                t.product_value,
                c.company,
                pg.product as product_group,
                u.nname,
                u.surename,
                tc.team,
                COALESCE(latest_s.level, '-') as step_name,
                t.contact_start_date
            FROM transactional t
            {$extraJoin}
            LEFT JOIN company_catalog c ON t.company_id = c.company_id
            LEFT JOIN product_group pg ON t.Product_id = pg.product_id
            LEFT JOIN user u ON t.user_id = u.user_id
            LEFT JOIN team_catalog tc ON t.team_id = tc.team_id
            LEFT JOIN (
                SELECT ts3.transac_id, s3.level
                FROM transactional_step ts3
                JOIN step s3 ON s3.level_id = ts3.level_id
                WHERE (ts3.transacstep_id, ts3.transac_id) IN (
                    SELECT MAX(ts4.transacstep_id), ts4.transac_id
                    FROM transactional_step ts4
                    GROUP BY ts4.transac_id
                )
            ) latest_s ON latest_s.transac_id = t.transac_id
            WHERE 1=1 {$where} {$extraWhere}
            ORDER BY t.product_value DESC
            LIMIT 100
        ", $allParams);

        return response()->json($projects);
    }

    public function winProjectsByUser(Request $request, $userId)
    {
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;

        $projects = $this->accurateDashboardBaseQuery($year, $quarter)
            ->join('company_catalog as win_company', 't.company_id', '=', 'win_company.company_id')
            ->join('product_group as win_product', 't.Product_id', '=', 'win_product.product_id')
            ->where('current_step.orderlv', 5)
            ->where('t.user_id', $userId)
            ->selectRaw('t.transac_id, t.Product_detail, t.product_value, win_company.company, win_product.product as product_group, DATE_FORMAT(latest_step.date, "%Y-%m-%d") as win_date')
            ->orderByDesc('t.product_value')
            ->get();

        return response()->json($projects);
    }

    /** @deprecated Kept temporarily for comparison with V2 user drill-down calculations. */
    private function legacyWinProjectsByUser(Request $request, $userId)
    {
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = $request->get('quarter');

        $params = [$userId];
        $where = "";
        // Use ts_win.date for year/quarter filter to match dashboard sumByPerson query
        $this->appendYearSqlFilter($where, $params, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'ts_win', 'date');

        $projects = DB::select("
            SELECT 
                t.transac_id,
                t.Product_detail,
                t.product_value,
                c.company,
                pg.product as product_group,
                DATE_FORMAT(ts_win.date, '%Y-%m-%d') as win_date
            FROM transactional t
            JOIN (
                SELECT ts.transac_id, ts.date
                FROM transactional_step ts
                JOIN step s ON s.level_id = ts.level_id
                WHERE s.level = 5
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            ) ts_win ON ts_win.transac_id = t.transac_id
            LEFT JOIN company_catalog c ON t.company_id = c.company_id
            LEFT JOIN product_group pg ON t.Product_id = pg.product_id
            WHERE t.user_id = ? {$where}
            ORDER BY ts_win.date DESC
        ", $params);

        return response()->json($projects);
    }

    public function dashboardTable(Request $request)
    {
        // Get filter parameters
        $year = $request->get('year');
        $quarter = $request->get('quarter');
        $userId = $request->get('user_id');
        $biddingUserId = $request->get('bidding_user_id');
        $biddingDateFrom = $request->get('bidding_date_from');
        $biddingDateTo = $request->get('bidding_date_to');
        $contractUserId = $request->get('contract_user_id');
        $contractDateFrom = $request->get('contract_date_from');
        $contractDateTo = $request->get('contract_date_to');
        
        // Get available years
        $availableYears = Cache::remember('dashboard:admin:table:availableYears', 120, function () {
            return DB::table('transactional')
                ->select('fiscalyear')
                ->distinct()
                ->orderBy('fiscalyear', 'desc')
                ->pluck('fiscalyear');
        });

        $availableUsers = Cache::remember('dashboard:admin:table:availableUsers', 120, function () {
            return DB::table('user')
                ->select('user_id', 'nname', 'surename')
                ->where('role_id', 3)
                ->orderBy('nname')
                ->orderBy('surename')
                ->get();
        });
        
        return view('admin.dashboard_table', compact(
            'availableYears', 'availableUsers',
            'year', 'quarter', 'userId',
            'biddingUserId', 'biddingDateFrom', 'biddingDateTo',
            'contractUserId', 'contractDateFrom', 'contractDateTo'
        ));
    }

    public function dashboardTableData(Request $request)
    {
        $year = $request->get('year');
        $quarter = $request->get('quarter');
        $userId = $request->get('user_id');
        $biddingUserId = $request->get('bidding_user_id');
        $biddingDateFrom = $request->get('bidding_date_from');
        $biddingDateTo = $request->get('bidding_date_to');
        $contractUserId = $request->get('contract_user_id');
        $contractDateFrom = $request->get('contract_date_from');
        $contractDateTo = $request->get('contract_date_to');

        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        if ($length <= 0 || $length > 200) {
            $length = 25;
        }

        $base = DB::table('transactional as t')
            ->whereNull('t.deleted_at')
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->leftJoin('product_group as pg', 't.Product_id', '=', 'pg.product_id')
            ->leftJoin('team_catalog as tc', 't.team_id', '=', 'tc.team_id')
            ->leftJoin('priority_level as pl', 't.priority_id', '=', 'pl.priority_id')
            ->leftJoin('user as u', 't.user_id', '=', 'u.user_id')
            ->leftJoin('source_of_the_budget as sb', 't.Source_budget_id', '=', 'sb.Source_budget_id')
            ->leftJoin('step as s', 't.Step_id', '=', 's.level_id');

        if ($year) {
            $base->where('t.fiscalyear', $year);
        }
        $this->applyQuarterFilterToQuery($base, $year, $quarter, 't.contact_start_date');
        if ($userId) {
            $base->where('t.user_id', $userId);
        }
        if ($biddingUserId) {
            $base->where('t.user_id', $biddingUserId);
        }
        if ($biddingDateFrom) {
            $base->where('t.date_of_closing_of_sale', '>=', $biddingDateFrom);
        }
        if ($biddingDateTo) {
            $base->where('t.date_of_closing_of_sale', '<=', $biddingDateTo);
        }
        if ($contractUserId) {
            $base->where('t.user_id', $contractUserId);
        }
        if ($contractDateFrom) {
            $base->where('t.sales_can_be_close', '>=', $contractDateFrom);
        }
        if ($contractDateTo) {
            $base->where('t.sales_can_be_close', '<=', $contractDateTo);
        }

        $total = (clone $base)->count('t.transac_id');

        $searchValue = trim((string) data_get($request->input('search'), 'value', ''));
        if ($searchValue !== '') {
            $base->where(function ($q) use ($searchValue) {
                $like = '%' . $searchValue . '%';
                $q->where('t.Product_detail', 'like', $like)
                    ->orWhere('c.company', 'like', $like)
                    ->orWhere('pg.product', 'like', $like)
                    ->orWhere('s.level', 'like', $like)
                    ->orWhere('pl.priority', 'like', $like)
                    ->orWhere('tc.team', 'like', $like)
                    ->orWhere('u.nname', 'like', $like)
                    ->orWhere('u.surename', 'like', $like)
                    ->orWhere('t.remark', 'like', $like);
            });
        }

        $filtered = (clone $base)->count('t.transac_id');

        $orderMap = [
            0 => 't.Product_detail',
            1 => 'c.company',
            2 => 't.product_value',
            3 => 's.level',
            4 => 'pl.priority',
            5 => 't.fiscalyear',
            6 => 't.contact_start_date',
            7 => 't.date_of_closing_of_sale',
            8 => 't.sales_can_be_close',
            9 => 'pg.product',
            10 => 'u.nname',
            11 => 'tc.team',
            12 => 't.remark',
        ];

        $orderCol = (int) data_get($request->input('order'), '0.column', 6);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy = $orderMap[$orderCol] ?? 't.updated_at';

        $rows = $base
            ->select([
                't.transac_id',
                't.Product_detail',
                't.product_value',
                't.fiscalyear',
                't.contact_start_date',
                't.date_of_closing_of_sale',
                't.sales_can_be_close',
                't.remark',
                'c.company',
                'pg.product as product_name',
                'tc.team',
                'pl.priority',
                'sb.Source_budge as source_budget',
                'u.nname',
                'u.surename',
                's.level as step_level',
            ])
            ->orderBy($orderBy, $orderDir)
            ->orderBy('t.transac_id', 'desc')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function ($r) {
            $id = (int) $r->transac_id;
            return [
                'id' => $id,
                'project' => $r->Product_detail,
                'company' => $r->company ?? '-',
                'value' => (float) $r->product_value,
                'status' => $r->step_level ?? '-',
                'priority' => $r->priority ?? '-',
                'year' => $r->fiscalyear ? ((int) $r->fiscalyear + 543) : '-',
                'start' => $r->contact_start_date,
                'bidding' => $r->date_of_closing_of_sale,
                'contract' => $r->sales_can_be_close,
                'product' => $r->product_name ?? '-',
                'user' => trim(($r->nname ?? '') . ' ' . ($r->surename ?? '')),
                'team' => $r->team ?? '-',
                'source' => $r->source_budget ?? '-',
                'contact_person' => '-',
                'contact_phone' => '-',
                'contact_email' => '-',
                'contact_note' => '-',
                'remark' => $r->remark ?? '-',
                'action' => '<a href="' . route('admin.sales.edit', $id) . '" class="btn btn-sm btn-info" title="แก้ไข"><i class="fas fa-pencil-alt"></i></a> '
                    . '<a href="' . route('admin.sales.transfer', $id) . '" class="btn btn-sm btn-warning" title="โอนข้อมูล"><i class="fas fa-exchange-alt"></i></a> '
                    . '<button type="button" class="btn btn-sm btn-danger js-delete-sale" title="ลบ" data-delete-url="' . route('admin.sales.delete', $id) . '"><i class="fas fa-trash"></i></button>',
            ];
        })->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function editSales($id)
    {
        $transaction = Transactional::findOrFail($id);
        
        // Cache master data for 1 hour (3600 seconds)
        $companies = \Cache::remember('companies_list', 3600, function() {
            return DB::table('company_catalog')->orderBy('company')->get();
        });
        
        $products = \Cache::remember('products_list', 3600, function() {
            return DB::table('product_group')->orderBy('product')->get();
        });
        
        $priorities = \Cache::remember('priorities_list', 3600, function() {
            return DB::table('priority_level')->orderBy('priority')->get();
        });
        
        $sources = \Cache::remember('sources_list', 3600, function() {
            return DB::table('source_of_the_budget')->orderBy('Source_budge')->get();
        });
        
        $steps = \Cache::remember('steps_list', 3600, function() {
            return DB::table('step')->orderBy('level_id')->get();
        });
        
        $teams = \Cache::remember('teams_list', 3600, function() {
            return DB::table('team_catalog')->orderBy('team')->get();
        });
        
        $users = \Cache::remember('active_users_list', 600, function() {
            return User::where('role_id', 3)->orderBy('nname')->get();
        });
        
        // Get transaction steps (not cached as it's transaction-specific)
        $transactionSteps = DB::table('transactional_step')
            ->where('transac_id', $id)
            ->get()
            ->keyBy('level_id');
        
        return view('admin.sales.edit', compact(
            'transaction',
            'companies',
            'products',
            'priorities',
            'sources',
            'steps',
            'teams',
            'users',
            'transactionSteps'
        ));
    }

    public function updateSales(Request $request, $id)
    {
        $transaction = Transactional::findOrFail($id);
        
        // Validation
        $validator = Validator::make($request->all(), [
            'Product_detail' => 'required|max:255',
            'company_id' => 'required|integer|exists:company_catalog,company_id',
            'product_value' => 'required',
            'Source_budget_id' => 'required|integer|exists:source_of_the_budget,Source_budget_id',
            'fiscalyear' => 'required|integer',
            'Product_id' => 'required|integer|exists:product_group,product_id',
            'team_id' => 'required|integer|exists:team_catalog,team_id',
            'user_id' => 'required|integer|exists:user,user_id',
            'priority_id' => 'nullable|integer|exists:priority_level,priority_id',
            'contact_start_date' => 'required|date',
            'date_of_closing_of_sale' => 'nullable|date',
            'sales_can_be_close' => 'nullable|date',
            'step_date' => 'nullable|array',
            'step_date.*' => 'nullable|date',
        ]);
        ProjectTimelineValidator::attach($validator, $request->all());
        $validator->validate();

        abort_unless(
            DB::table('user')->where('user_id', $request->integer('user_id'))->where('role_id', 3)->exists()
            && DB::table('transactional_team')
                ->where('user_id', $request->integer('user_id'))
                ->where('team_id', $request->integer('team_id'))
                ->exists(),
            422,
            'The selected owner must be a user assigned to the selected team.'
        );

        try {
            // Remove comma from product_value
            $productValue = str_replace(',', '', $request->product_value);

            // Get the highest step level selected
            $stepId = ProjectTimelineValidator::latestSelectedStepId($request->all());

            // Update transactional record
            $transaction->update([
                'user_id' => $request->user_id,
                'company_id' => $request->company_id,
                'Product_id' => $request->Product_id,
                'team_id' => $request->team_id,
                'priority_id' => $request->priority_id,
                'Source_budget_id' => $request->Source_budget_id,
                'Product_detail' => $request->Product_detail,
                'product_value' => $productValue,
                'fiscalyear' => $request->fiscalyear,
                'contact_start_date' => $request->contact_start_date,
                'date_of_closing_of_sale' => $request->date_of_closing_of_sale,
                'sales_can_be_close' => $request->sales_can_be_close,
                'remark' => $request->remark ?? '',
                'contact_person' => $request->contact_person,
                'contact_phone' => $request->contact_phone,
                'contact_email' => $request->contact_email,
                'contact_note' => $request->contact_note,
                'Step_id' => $stepId ?? $transaction->Step_id,
            ]);

            // Delete existing steps and recreate
            DB::table('transactional_step')->where('transac_id', $id)->delete();

            // Save transactional steps if any
            if ($request->has('step') && is_array($request->step)) {
                foreach ($request->step as $levelId => $value) {
                    if ($value && isset($request->step_date[$levelId]) && $request->step_date[$levelId]) {
                        DB::table('transactional_step')->insert([
                            'transac_id' => $id,
                            'level_id' => $levelId,
                            'date' => $request->step_date[$levelId],
                        ]);
                    }
                }
            }

            // Redirect based on user role
            $user = auth()->user();
            if ($user->role_id == 2) {
                // Team Admin
                return redirect()->route('teamadmin.dashboard.table')->with('success', 'อัพเดทข้อมูลเรียบร้อยแล้ว');
            } else {
                // Admin
                return redirect()->route('admin.dashboard.table')->with('success', 'อัพเดทข้อมูลเรียบร้อยแล้ว');
            }
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function deleteSales($id)
    {
        try {
            $transaction = Transactional::find($id);
            if (!$transaction) {
                return redirect()->route('admin.dashboard.table')
                    ->with('error', 'รายการนี้ถูกลบไปแล้ว');
            }
            
            // Delete related steps first
            DB::table('transactional_step')->where('transac_id', $id)->delete();
            
            // Delete transaction
            $transaction->delete();

            return redirect()->route('admin.dashboard.table')->with('success', 'ลบข้อมูลเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function transferSales($id)
    {
        $transaction = Transactional::with(['user', 'company', 'team'])->findOrFail($id);
        
        $users = User::where('role_id', 3)
            ->where('user_id', '!=', $transaction->user_id)
            ->where('is_active', 1)
            ->orderBy('nname')
            ->get();
        
        $teams = DB::table('team_catalog')->orderBy('team')->get();
        
        return view('admin.sales.transfer', compact('transaction', 'users', 'teams'));
    }

    public function processTransfer(Request $request, $id)
    {
        $request->validate([
            'to_user_id' => 'required|integer|exists:user,user_id',
            'transfer_reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();
            
            $transaction = Transactional::findOrFail($id);
            $currentUserId = $transaction->user_id;
            $currentTeamId = $transaction->team_id;
            
            if ($currentUserId == $request->to_user_id) {
                return redirect()->back()->with('error', 'ไม่สามารถโอนข้อมูลให้ผู้ใช้งานคนเดิมได้');
            }
            
            $toUser = User::findOrFail($request->to_user_id);
            $newTeamId = $toUser->team_id ?? $currentTeamId;
            
            $transaction->user_id = $request->to_user_id;
            $transaction->team_id = $newTeamId;
            $transaction->save();
            
            \App\Models\TransactionalTransferHistory::create([
                'transac_id' => $id,
                'from_user_id' => $currentUserId,
                'to_user_id' => $request->to_user_id,
                'transferred_by' => auth()->user()->user_id,
                'transfer_reason' => $request->transfer_reason,
                'old_team_id' => $currentTeamId,
                'new_team_id' => $newTeamId,
                'transferred_at' => now(),
            ]);
            
            DB::commit();

            $this->flushAdminDashboardCache();
            
            return redirect()->route('admin.dashboard.table')->with('success', 'โอนข้อมูลเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function getTransferHistory($id)
    {
        $transaction = Transactional::with(['user', 'company'])->findOrFail($id);
        
        $transferHistory = \App\Models\TransactionalTransferHistory::with([
            'fromUser', 
            'toUser', 
            'transferredByUser',
            'oldTeam',
            'newTeam'
        ])
        ->where('transac_id', $id)
        ->orderBy('transferred_at', 'desc')
        ->get();
        
        return view('admin.sales.transfer_history', compact('transaction', 'transferHistory'));
    }

    public function profile()
    {
        $user = auth()->user();
        $roles = \App\Models\RoleCatalog::orderBy('role')->get();
        $positions = \App\Models\Position::orderBy('position')->get();
        $twoFactorEnabled = $user->two_factor_enabled;
        $profileStats = DB::table('transactional as t')
            ->leftJoin('step as s', 't.Step_id', '=', 's.level_id')
            ->whereNull('t.deleted_at')
            ->selectRaw('COUNT(t.transac_id) as project_count, SUM(CASE WHEN s.orderlv = 5 THEN 1 ELSE 0 END) as win_count')
            ->first();

        return view('admin.profile', compact('user', 'roles', 'positions', 'twoFactorEnabled', 'profileStats'));
    }

    public function updateProfile(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'nname' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $user->nname = $request->nname;
            $user->surename = $request->surname;

            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                
                // Additional security checks
                $mimeToExtension = [
                    'image/jpeg' => 'jpg',
                    'image/jpg'  => 'jpg',
                    'image/png'  => 'png',
                ];
                $mimeType = $avatar->getMimeType();
                if (!array_key_exists($mimeType, $mimeToExtension)) {
                    return redirect()->back()->with('error', 'ไฟล์ต้องเป็นรูปภาพ (JPEG, PNG) เท่านั้น');
                }

                // Check file size (max 2MB)
                if ($avatar->getSize() > 2048 * 1024) {
                    return redirect()->back()->with('error', 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 2MB)');
                }

                // Derive extension from MIME type (not from user-supplied filename)
                $extension = $mimeToExtension[$mimeType];
                $fileName = 'user_' . $user->user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                
                $uploadPath = public_path('uploads' . DIRECTORY_SEPARATOR . 'avatars');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $avatar->move($uploadPath, $fileName);
                $user->avatar_path = 'uploads/avatars/' . $fileName;
            }

            $user->save();

            return redirect()->route('admin.profile')->with('success', 'อัปเดตโปรไฟล์เรียบร้อยแล้ว');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function toggleTwoFactor(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();

        $request->validate(['enabled' => 'required|boolean']);
        $enabled = $request->boolean('enabled');

        if ($user->two_factor_enabled && !$enabled) {
            $request->validate(['current_password' => 'required|string']);

            if (!$user->verifyCurrentPassword($request->current_password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
                ], 422);
            }
        }
        
        $user->two_factor_enabled = $enabled;
        $user->save();
        
        $status = $user->two_factor_enabled ? 'เปิด' : 'ปิด';
        
        return response()->json([
            'success' => true,
            'enabled' => $user->two_factor_enabled,
            'message' => $status . 'การใช้งาน 2FA เรียบร้อยแล้ว'
        ]);
    }
    
    private function accurateDashboardBaseQuery(?int $year, ?int $quarter)
    {
        $latestIds = DB::table('transactional_step')
            ->selectRaw('transac_id, MAX(transacstep_id) as max_step_id')
            ->groupBy('transac_id');

        $latestSteps = DB::table('transactional_step as latest_history')
            ->joinSub($latestIds, 'latest_ids', function ($join) {
                $join->on('latest_ids.transac_id', '=', 'latest_history.transac_id')
                    ->on('latest_ids.max_step_id', '=', 'latest_history.transacstep_id');
            })
            ->select('latest_history.transac_id', 'latest_history.level_id', 'latest_history.date');

        $query = DB::table('transactional as t')
            ->leftJoinSub($latestSteps, 'latest_step', 'latest_step.transac_id', '=', 't.transac_id')
            ->leftJoin('step as current_step', 'current_step.level_id', '=', 'latest_step.level_id')
            ->whereNull('t.deleted_at');

        if ($year !== null) {
            $query->where('t.fiscalyear', $year);
        }

        if ($quarter !== null) {
            $query->whereRaw('QUARTER(' . $this->dashboardEffectiveDateSql() . ') = ?', [$quarter]);
        }

        return $query;
    }

    private function dashboardEffectiveDateSql(): string
    {
        return 'COALESCE(latest_step.date, t.contact_start_date)';
    }

    private function flushAdminDashboardCache(): void
    {
        $years = array_merge(['all'], DB::table('transactional')
            ->selectRaw('DISTINCT YEAR(contact_start_date) as y')
            ->whereNotNull('contact_start_date')
            ->pluck('y')
            ->toArray());

        $quarters = ['all', '1', '2', '3', '4'];

        $prefixes = [
            'cumulativeWin',
            'sumByTeam',
            'sumByPerson',
            'saleStatus',
            'saleStatusValue',
            'topProducts',
            'topCustomers',
            'targetForecastWin',
        ];

        foreach ($prefixes as $prefix) {
            foreach ($years as $y) {
                foreach ($quarters as $q) {
                    Cache::forget("dashboard:admin:{$prefix}:{$y}:{$q}");
                }
            }
        }
    }

    private function quarterDateRange($year, $quarter)
    {
        $y = (int) $year;
        $q = (int) $quarter;
        if ($y <= 0 || $q < 1 || $q > 4) {
            return null;
        }

        $startMonth = (($q - 1) * 3) + 1;
        $start = sprintf('%04d-%02d-01', $y, $startMonth);

        $endMonth = $startMonth + 3;
        $endYear = $y;
        if ($endMonth > 12) {
            $endMonth -= 12;
            $endYear++;
        }
        $end = sprintf('%04d-%02d-01', $endYear, $endMonth);

        return [$start, $end];
    }

    private function resolveDashboardYearFilter(Request $request)
    {
        $currentYear = (int) date('Y');
        $yearInput = $request->query('year');
        $hasYearParam = $request->query->has('year');

        if (!$hasYearParam) {
            return [
                'year' => $currentYear,
                'selected' => (string) $currentYear,
            ];
        }

        $normalized = strtolower(trim((string) $yearInput));
        if ($normalized === '' || $normalized === 'all') {
            return [
                'year' => null,
                'selected' => 'all',
            ];
        }

        $year = (int) $normalized;
        if ($year <= 0) {
            return [
                'year' => $currentYear,
                'selected' => (string) $currentYear,
            ];
        }

        return [
            'year' => $year,
            'selected' => (string) $year,
        ];
    }

    private function applyYearFilterToQuery($query, $year, $column = 'contact_start_date')
    {
        if ($year === null) {
            return;
        }

        $query->whereYear($column, $year);
    }

    private function appendYearSqlFilter(&$where, &$params, $year, $alias = 't', $column = 'contact_start_date')
    {
        if ($year === null) {
            return;
        }

        $where .= " AND YEAR({$alias}.{$column}) = ?";
        $params[] = $year;
    }

    private function applyQuarterFilterToQuery($query, $year, $quarter, $column = 'contact_start_date')
    {
        if (!$quarter) {
            return;
        }

        $range = $this->quarterDateRange($year, $quarter);
        if ($range) {
            $query->where($column, '>=', $range[0])
                ->where($column, '<', $range[1]);
            return;
        }

        $query->whereRaw("QUARTER({$column}) = ?", [$quarter]);
    }

    private function appendQuarterSqlFilter(&$where, &$params, $year, $quarter, $alias = 't', $column = 'contact_start_date')
    {
        if (!$quarter) {
            return;
        }

        $range = $this->quarterDateRange($year, $quarter);
        if ($range) {
            $where .= " AND {$alias}.{$column} >= ? AND {$alias}.{$column} < ?";
            $params[] = $range[0];
            $params[] = $range[1];
            return;
        }

        $where .= " AND QUARTER({$alias}.{$column}) = ?";
        $params[] = $quarter;
    }

    /**
     * Build WHERE clause for year and quarter filters
     */
    private function buildFilterWhere($year, $quarter, $alias = 't')
    {
        $where = [];
        $params = [];

        $yearSql = '';
        $yearParams = [];
        $this->appendYearSqlFilter($yearSql, $yearParams, $year, $alias);
        if ($yearSql !== '') {
            $where[] = ltrim(str_replace('AND', '', $yearSql));
            $params = array_merge($params, $yearParams);
        }

        $quarterSql = '';
        $quarterParams = [];
        $this->appendQuarterSqlFilter($quarterSql, $quarterParams, $year, $quarter, $alias);
        if ($quarterSql !== '') {
            $where[] = ltrim(str_replace('AND', '', $quarterSql));
            $params = array_merge($params, $quarterParams);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return ['where' => $whereClause, 'params' => $params];
    }

    // Reports Section
    public function reportsIndex()
    {
        return view('admin.reports.index');
    }

    public function reportBidding(Request $request)
    {
        $availableUsers = DB::table('user')
            ->select('user_id', 'nname', 'surename', 'is_active')
            ->where('role_id', 3)
            ->orderBy('nname')
            ->orderBy('surename')
            ->get();

        return view('admin.reports.bidding', compact('availableUsers'));
    }

    public function reportBiddingData(Request $request)
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:user,user_id',
            'date_from'   => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d',
            'export_type' => 'nullable|in:excel',
        ]);

        $userId     = $request->integer('user_id') ?: null;
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $exportType = $request->get('export_type');

        if ($exportType === 'excel') {
            return Excel::download(new BiddingReportExport($userId, $dateFrom, $dateTo), 'รายงานวันยื่น_Bidding_' . date('Y-m-d') . '.xlsx');
        }

        // Return data for DataTables
        $query = DB::table('transactional as t')
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->leftJoin('user as u', 't.user_id', '=', 'u.user_id')
            ->select([
                't.Product_detail as project_name',
                'c.company as company_name',
                't.product_value as value',
                't.date_of_closing_of_sale as bidding_date',
                DB::raw("CONCAT(u.nname, ' ', u.surename) as user_name")
            ])
            ->whereNotNull('t.date_of_closing_of_sale');

        if ($userId) {
            $query->where('t.user_id', $userId);
        }

        if ($dateFrom) {
            $query->where('t.date_of_closing_of_sale', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('t.date_of_closing_of_sale', '<=', $dateTo);
        }

        $data = $query->orderBy('t.date_of_closing_of_sale', 'desc')->get();

        return response()->json([
            'data' => $data->map(function ($item) {
                return [
                    'project_name' => $item->project_name ?? '-',
                    'company_name' => $item->company_name ?? '-',
                    'value' => number_format($item->value ?? 0, 2),
                    'bidding_date' => $item->bidding_date ? \Carbon\Carbon::parse($item->bidding_date)->format('d/m/Y') : '-',
                    'user_name' => $item->user_name ?? '-'
                ];
            })
        ]);
    }

    public function reportContract(Request $request)
    {
        $availableUsers = DB::table('user')
            ->select('user_id', 'nname', 'surename', 'is_active')
            ->where('role_id', 3)
            ->orderBy('nname')
            ->orderBy('surename')
            ->get();

        return view('admin.reports.contract', compact('availableUsers'));
    }

    public function reportContractData(Request $request)
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:user,user_id',
            'date_from'   => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d',
            'export_type' => 'nullable|in:excel',
        ]);

        $userId     = $request->integer('user_id') ?: null;
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $exportType = $request->get('export_type');

        if ($exportType === 'excel') {
            return Excel::download(new ContractReportExport($userId, $dateFrom, $dateTo), 'รายงานวันเซ็นสัญญา_' . date('Y-m-d') . '.xlsx');
        }

        // Return data for DataTables
        $query = DB::table('transactional as t')
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->leftJoin('user as u', 't.user_id', '=', 'u.user_id')
            ->select([
                't.Product_detail as project_name',
                'c.company as company_name',
                't.product_value as value',
                't.sales_can_be_close as contract_date',
                DB::raw("CONCAT(u.nname, ' ', u.surename) as user_name")
            ])
            ->whereNotNull('t.sales_can_be_close');

        if ($userId) {
            $query->where('t.user_id', $userId);
        }

        if ($dateFrom) {
            $query->where('t.sales_can_be_close', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('t.sales_can_be_close', '<=', $dateTo);
        }

        $data = $query->orderBy('t.sales_can_be_close', 'desc')->get();

        return response()->json([
            'data' => $data->map(function ($item) {
                return [
                    'project_name' => $item->project_name ?? '-',
                    'company_name' => $item->company_name ?? '-',
                    'value' => number_format($item->value ?? 0, 2),
                    'contract_date' => $item->contract_date ? \Carbon\Carbon::parse($item->contract_date)->format('d/m/Y') : '-',
                    'user_name' => $item->user_name ?? '-'
                ];
            })
        ]);
    }

    public function reportWindate(Request $request)
    {
        $availableUsers = DB::table('user')
            ->select('user_id', 'nname', 'surename', 'is_active')
            ->where('role_id', 3)
            ->orderBy('nname')
            ->orderBy('surename')
            ->get();

        return view('admin.reports.windate', compact('availableUsers'));
    }

    public function reportWindateData(Request $request)
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:user,user_id',
            'date_from'   => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d',
            'export_type' => 'nullable|in:excel',
        ]);

        $userId     = $request->integer('user_id') ?: null;
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $exportType = $request->get('export_type');

        if ($exportType === 'excel') {
            return Excel::download(new \App\Exports\WindateReportExport($userId, $dateFrom, $dateTo), 'รายงาน_Windate_' . date('Y-m-d') . '.xlsx');
        }

        $winSub = DB::table('transactional_step as ts')
            ->join('step as s', 's.level_id', '=', 'ts.level_id')
            ->where('s.level', 5)
            ->select('ts.transac_id', DB::raw('MAX(ts.transacstep_id) as max_step_id'))
            ->groupBy('ts.transac_id');

        $query = DB::table('transactional as t')
            ->joinSub($winSub, 'win_latest', 'win_latest.transac_id', '=', 't.transac_id')
            ->join('transactional_step as ts_win', 'ts_win.transacstep_id', '=', 'win_latest.max_step_id')
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->leftJoin('user as u', 't.user_id', '=', 'u.user_id')
            ->select([
                't.Product_detail as project_name',
                'c.company as company_name',
                't.product_value as value',
                'ts_win.date as win_date',
                DB::raw("CONCAT(u.nname, ' ', u.surename) as user_name")
            ])
            ->whereNull('t.deleted_at');

        if ($userId) {
            $query->where('t.user_id', $userId);
        }

        if ($dateFrom) {
            $query->where('ts_win.date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('ts_win.date', '<=', $dateTo);
        }

        $data = $query->orderBy('ts_win.date', 'desc')->get();

        return response()->json([
            'data' => $data->map(function ($item) {
                return [
                    'project_name' => $item->project_name ?? '-',
                    'company_name' => $item->company_name ?? '-',
                    'value' => number_format($item->value ?? 0, 2),
                    'win_date' => $item->win_date ? \Carbon\Carbon::parse($item->win_date)->format('d/m/Y') : '-',
                    'user_name' => $item->user_name ?? '-'
                ];
            })
        ]);
    }
}
