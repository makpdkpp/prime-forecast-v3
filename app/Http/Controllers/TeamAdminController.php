<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\ProjectTimelineValidator;
use Illuminate\Support\Facades\Validator;

class TeamAdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $yearFilter = $this->resolveDashboardYearFilter($request);
        $year = $yearFilter['year'];
        $selectedYear = $yearFilter['selected'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;

        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->map(fn ($teamId) => (int) $teamId)
            ->all();

        $availableYears = DB::table('transactional')
            ->whereNull('deleted_at')
            ->whereIn('team_id', $userTeams ?: [-1])
            ->whereNotNull('fiscalyear')
            ->distinct()
            ->orderByDesc('fiscalyear')
            ->pluck('fiscalyear');

        $base = $this->accurateTeamDashboardBaseQuery($userTeams, $year, $quarter);
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
            ->whereExists(function ($query) use ($userTeams) {
                $query->selectRaw('1')
                    ->from('transactional_team as tt')
                    ->whereColumn('tt.user_id', 'u.user_id')
                    ->whereIn('tt.team_id', $userTeams ?: [-1]);
            })
            ->where(function ($query) {
                $query->whereNotNull('tgt.target_value')
                    ->orWhereNotNull('fc.forecast_value')
                    ->orWhereNotNull('wn.win_value');
            })
            ->selectRaw('u.user_id, u.nname, u.surename, COALESCE(tgt.target_value, 0) as target_value, COALESCE(fc.forecast_value, 0) as forecast_value, COALESCE(wn.win_value, 0) as win_value')
            ->orderByDesc('forecast_value')
            ->get();

        return view('teamadmin.dashboard', compact(
            'estimateValue', 'winValue', 'winCount', 'lostCount', 'cumulativeWin',
            'sumByTeam', 'sumByPerson', 'saleStatus', 'saleStatusValue', 'topProducts',
            'topCustomers', 'targetForecastWin', 'availableYears', 'year', 'selectedYear', 'quarter'
        ));
    }

    /** @deprecated Kept temporarily until the shared dashboard rollout is accepted. */
    private function legacyDashboard(Request $request)
    {
        $user = Auth::user();
        
        // Get filter parameters (same as AdminController)
        $yearFilter = $this->resolveDashboardYearFilter($request);
        $year = $yearFilter['year'];
        $selectedYear = $yearFilter['selected'];
        $quarter = $request->get('quarter');
        
        // Get available years from transactional_step activity date
        $availableYears = Cache::remember('dashboard:teamadmin:availableYears', 120, function () {
            return DB::table('transactional_step')
                ->selectRaw('YEAR(date) as year')
                ->whereNotNull('date')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year');
        });
        
        // Get user's teams
        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();
        
        if (empty($userTeams)) {
            return view('teamadmin.dashboard', compact('availableYears', 'year', 'quarter'))
                ->with('error', 'คุณยังไม่ได้รับมอบหมายทีม');
        }

        $teamKey = md5(implode(',', $userTeams));
        $teamPlaceholders = implode(',', array_fill(0, count($userTeams), '?'));

        // Estimate value (all projects in team, filtered by contact_start_date)
        $query = DB::table('transactional')->whereIn('team_id', $userTeams);
        $this->applyYearFilterToQuery($query, $year);
        $this->applyQuarterFilterToQuery($query, $year, $quarter);
        $estimateValue = (clone $query)->sum('product_value');

        // WIN value and count (latest step is WIN - level = 5, filtered by ts_win.date)
        $winParams = $userTeams;
        $winWhere = " AND t.team_id IN ({$teamPlaceholders})";
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

        // LOST count (latest step is LOST - level = 6, filtered by ts_lost.date)
        $lostParams = $userTeams;
        $lostWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($lostWhere, $lostParams, $year, 'ts_lost', 'date');
        $this->appendQuarterSqlFilter($lostWhere, $lostParams, $year, $quarter, 'ts_lost', 'date');
        $lostData = DB::select("
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
        $lostCount = $lostData[0]->lost_count ?? 0;

        // Cumulative WIN by month (latest step is WIN, filtered by ts_win.date)
        $cumulativeWinParams = $userTeams;
        $cumulativeWinWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($cumulativeWinWhere, $cumulativeWinParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($cumulativeWinWhere, $cumulativeWinParams, $year, $quarter, 'ts_win', 'date');
        $cumulativeWin = Cache::remember('dashboard:teamadmin:cumulativeWin:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($cumulativeWinWhere, $cumulativeWinParams) {
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

        // Sum by team (WIN only, filtered by ts_win.date)
        $sumByTeamParams = $userTeams;
        $sumByTeamWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($sumByTeamWhere, $sumByTeamParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($sumByTeamWhere, $sumByTeamParams, $year, $quarter, 'ts_win', 'date');
        $sumByTeam = Cache::remember('dashboard:teamadmin:sumByTeam:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($sumByTeamWhere, $sumByTeamParams) {
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

        // Sum by person (WIN only, filtered by ts_win.date)
        $sumByPersonParams = $userTeams;
        $sumByPersonWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($sumByPersonWhere, $sumByPersonParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($sumByPersonWhere, $sumByPersonParams, $year, $quarter, 'ts_win', 'date');
        $sumByPerson = Cache::remember('dashboard:teamadmin:sumByPerson:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($sumByPersonWhere, $sumByPersonParams) {
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

        // Sale status count by month and latest step (filtered by ts.date)
        $saleStatusParams = $userTeams;
        $saleStatusWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($saleStatusWhere, $saleStatusParams, $year, 'ts', 'date');
        $this->appendQuarterSqlFilter($saleStatusWhere, $saleStatusParams, $year, $quarter, 'ts', 'date');
        $saleStatus = Cache::remember('dashboard:teamadmin:saleStatus:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($saleStatusParams, $saleStatusWhere) {
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

        // Sale status value by month and latest step (filtered by ts.date)
        $saleStatusValueParams = $userTeams;
        $saleStatusValueWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($saleStatusValueWhere, $saleStatusValueParams, $year, 'ts', 'date');
        $this->appendQuarterSqlFilter($saleStatusValueWhere, $saleStatusValueParams, $year, $quarter, 'ts', 'date');
        $saleStatusValue = Cache::remember('dashboard:teamadmin:saleStatusValue:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($saleStatusValueParams, $saleStatusValueWhere) {
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

        // Top 10 products (WIN only, filtered by ts_win.date)
        $topProductsParams = $userTeams;
        $topProductsWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($topProductsWhere, $topProductsParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($topProductsWhere, $topProductsParams, $year, $quarter, 'ts_win', 'date');
        $topProducts = Cache::remember('dashboard:teamadmin:topProducts:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($topProductsWhere, $topProductsParams) {
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
                GROUP BY pg.product_id, pg.product
                ORDER BY total_value DESC
                LIMIT 10
            ", $topProductsParams);
        });

        // Top 10 customers (WIN only, filtered by ts_win.date)
        $topCustomersParams = $userTeams;
        $topCustomersWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $this->appendYearSqlFilter($topCustomersWhere, $topCustomersParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($topCustomersWhere, $topCustomersParams, $year, $quarter, 'ts_win', 'date');
        $topCustomers = Cache::remember('dashboard:teamadmin:topCustomers:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($topCustomersWhere, $topCustomersParams) {
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

        // Target/Forecast/Win comparison per user (team filtered)
        $tfwForecastWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $tfwWinWhere = " AND t.team_id IN ({$teamPlaceholders})";
        $tfwTargetWhere = "";
        $forecastParams = $userTeams;
        $winParams = $userTeams;

        $this->appendYearSqlFilter($tfwForecastWhere, $forecastParams, $year, 'ts_latest', 'date');
        $this->appendQuarterSqlFilter($tfwForecastWhere, $forecastParams, $year, $quarter, 'ts_latest', 'date');
        $this->appendYearSqlFilter($tfwWinWhere, $winParams, $year, 'ts_win', 'date');
        $this->appendQuarterSqlFilter($tfwWinWhere, $winParams, $year, $quarter, 'ts_win', 'date');

        if ($year !== null) {
            $tfwTargetWhere .= " AND uft.fiscal_year = ?";
        }

        $targetParams = [];
        if ($year !== null) {
            $targetParams[] = $year;
        }

        $targetForecastWin = Cache::remember('dashboard:teamadmin:targetForecastWin:' . $user->user_id . ':' . $teamKey . ':' . ($year ?? 'all') . ':' . ($quarter ?? 'all'), 120, function () use ($tfwTargetWhere, $tfwForecastWhere, $tfwWinWhere, $targetParams, $forecastParams, $winParams, $userTeams, $teamPlaceholders) {
            return DB::select("
                SELECT 
                    u.user_id,
                    u.nname,
                    u.surename,
                    COALESCE(tgt.target_value, 0) as target_value,
                    COALESCE(fc.forecast_value, 0) as forecast_value,
                    COALESCE(wn.win_value, 0) as win_value
                FROM user u
                INNER JOIN (
                    SELECT DISTINCT tt.user_id
                    FROM transactional_team tt
                    WHERE tt.team_id IN ({$teamPlaceholders})
                ) team_users ON team_users.user_id = u.user_id
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
            ", array_merge($userTeams, $targetParams, $forecastParams, $winParams));
        });
        
        return view('teamadmin.dashboard', compact(
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
        $userTeams = DB::table('transactional_team')
            ->where('user_id', Auth::id())
            ->pluck('team_id')
            ->map(fn ($teamId) => (int) $teamId)
            ->all();

        $query = $this->accurateTeamDashboardBaseQuery($userTeams, $year, $quarter)
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
            case 'user':
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

        return response()->json($query
            ->selectRaw("t.transac_id, t.Product_detail, t.product_value, detail_company.company, detail_product.product as product_group, detail_user.nname, detail_user.surename, detail_team.team, COALESCE(current_step.level, 'ยังไม่ระบุสถานะ') as step_name, t.contact_start_date")
            ->orderByDesc('t.product_value')
            ->get());
    }

    /** @deprecated Kept temporarily until the shared dashboard rollout is accepted. */
    private function legacyChartDetail(Request $request)
    {
        $user = Auth::user();
        $type = $request->get('type');
        $value = $request->get('value');
        $value2 = $request->get('value2');
        $yearFilter = $this->resolveDashboardYearFilter($request);
        $year = $yearFilter['year'];
        $quarter = $request->get('quarter');

        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();

        if (empty($userTeams)) {
            return response()->json([]);
        }

        $teamPlaceholders = implode(',', array_fill(0, count($userTeams), '?'));
        $params = $userTeams;
        $where = " AND t.team_id IN ({$teamPlaceholders})";

        // Use the correct date column for year/quarter filter based on chart type
        if ($type === 'step') {
            // Step-based charts: filter by ts.date
            $this->appendYearSqlFilter($where, $params, $year, 'ts', 'date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'ts', 'date');
        } else if ($type === 'month') {
            // Month-based WIN charts: skip year/quarter filter here
            // because month value already includes year (e.g. '2026-03')
        } else if (in_array($type, ['team', 'product', 'company', 'user'])) {
            // WIN-based charts: filter by wintrans.win_date
            $this->appendYearSqlFilter($where, $params, $year, 'wintrans', 'win_date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'wintrans', 'win_date');
        } else {
            // Default: filter by t.contact_start_date
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
            case 'user': // ยอดขายรายคน — win by user
                $extraJoin = $winJoin;
                $extraWhere = " AND t.user_id = ?";
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

    public function dashboardTable(Request $request)
    {
        $user = Auth::user();
        
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
        $availableYears = Cache::remember('dashboard:teamadmin:table:availableYears', 120, function () {
            return DB::table('transactional')
                ->select('fiscalyear')
                ->distinct()
                ->orderBy('fiscalyear', 'desc')
                ->pluck('fiscalyear');
        });
        
        // Get user's teams
        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();

        $availableUsers = collect();
        if (!empty($userTeams)) {
            $availableUsers = DB::table('user as u')
                ->join('transactional as t', 'u.user_id', '=', 't.user_id')
                ->whereIn('t.team_id', $userTeams)
                ->where('u.role_id', 3)
                ->select('u.user_id', 'u.nname', 'u.surename')
                ->distinct()
                ->orderBy('u.nname')
                ->orderBy('u.surename')
                ->get();
        }

        return view('teamadmin.dashboard_table', compact(
            'availableYears', 'availableUsers',
            'year', 'quarter', 'userId', 'userTeams',
            'biddingUserId', 'biddingDateFrom', 'biddingDateTo',
            'contractUserId', 'contractDateFrom', 'contractDateTo'
        ));
    }

    public function dashboardTableData(Request $request)
    {
        $user = Auth::user();
        $year = $request->get('year');
        $quarter = $request->get('quarter');
        $userId = $request->get('user_id');
        $biddingUserId = $request->get('bidding_user_id');
        $biddingDateFrom = $request->get('bidding_date_from');
        $biddingDateTo = $request->get('bidding_date_to');
        $contractUserId = $request->get('contract_user_id');
        $contractDateFrom = $request->get('contract_date_from');
        $contractDateTo = $request->get('contract_date_to');

        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();

        if (empty($userTeams)) {
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

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
            ->leftJoin('step as s', 't.Step_id', '=', 's.level_id')
            ->whereIn('t.team_id', $userTeams);

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
                'action' => '<a href="' . route('teamadmin.sales.edit', $id) . '" class="btn btn-sm btn-info" title="แก้ไข"><i class="fas fa-pencil-alt"></i></a>',
            ];
        })->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    private function accurateTeamDashboardBaseQuery(array $teamIds, ?int $year, ?int $quarter)
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
            ->whereNull('t.deleted_at')
            ->whereIn('t.team_id', $teamIds ?: [-1]);

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

    private function resolveDashboardYearFilter(Request $request): array
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

        return [
            'year' => (int) $yearInput,
            'selected' => (string) $yearInput,
        ];
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

    private function applyYearFilterToQuery($query, $year)
    {
        if ($year === null) {
            return;
        }
        $query->whereRaw('YEAR(contact_start_date) = ?', [$year]);
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

    private function appendYearSqlFilter(&$where, &$params, $year, $alias = 't', $column = 'contact_start_date')
    {
        if ($year === null) {
            return;
        }
        $where .= " AND YEAR({$alias}.{$column}) = ?";
        $params[] = $year;
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

    public function profile()
    {
        $user = auth()->user();
        $roles = \App\Models\RoleCatalog::orderBy('role')->get();
        $positions = \App\Models\Position::orderBy('position')->get();
        $twoFactorEnabled = $user->two_factor_enabled;
        $teamIds = DB::table('transactional_team')->where('user_id', $user->user_id)->pluck('team_id');
        $profileStats = DB::table('transactional as t')
            ->leftJoin('step as s', 't.Step_id', '=', 's.level_id')
            ->whereNull('t.deleted_at')
            ->whereIn('t.team_id', $teamIds)
            ->selectRaw('COUNT(t.transac_id) as project_count, SUM(CASE WHEN s.orderlv = 5 THEN 1 ELSE 0 END) as win_count')
            ->first();

        return view('teamadmin.profile', compact('user', 'roles', 'positions', 'twoFactorEnabled', 'profileStats'));
    }

    public function updateProfile(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'nname' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        ProjectTimelineValidator::attach($validator, $request->all());
        $validator->validate();

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

            return redirect()->route('teamadmin.profile')->with('success', 'อัปเดตโปรไฟล์เรียบร้อยแล้ว');
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

    public function editSales($id)
    {
        $user = Auth::user();
        
        // Get user's teams
        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();
        
        // Find transaction and verify it belongs to user's team
        $transaction = \App\Models\Transactional::whereIn('team_id', $userTeams)
            ->findOrFail($id);
        
        // Cache master data for 1 hour
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
            return \App\Models\User::where('role_id', 3)->orderBy('nname')->get();
        });
        
        // Get transaction steps
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

    public function updateSales(\Illuminate\Http\Request $request, $id)
    {
        $user = Auth::user();
        
        // Get user's teams
        $userTeams = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();
        
        // Find transaction and verify it belongs to user's team
        $transaction = \App\Models\Transactional::whereIn('team_id', $userTeams)
            ->findOrFail($id);
        
        // Validation
        $request->validate([
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

        $targetTeamId = (int) $request->team_id;
        $targetUserId = (int) $request->user_id;
        $managedTeamIds = array_map('intval', $userTeams);

        abort_unless(in_array($targetTeamId, $managedTeamIds, true), 403);
        abort_unless(
            DB::table('user')->where('user_id', $targetUserId)->where('role_id', 3)->exists()
            && DB::table('transactional_team')->where('user_id', $targetUserId)->where('team_id', $targetTeamId)->exists(),
            403
        );

        try {
            $productValue = str_replace(',', '', $request->product_value);

            $stepId = ProjectTimelineValidator::latestSelectedStepId($request->all());

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

            DB::table('transactional_step')->where('transac_id', $id)->delete();

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

            return redirect()->route('teamadmin.dashboard.table')->with('success', 'อัพเดทข้อมูลเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    // ─── Reports ─────────────────────────────────────────────────────────────

    private function getMyTeamIds(): array
    {
        $user = Auth::user();
        return DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->toArray();
    }

    private function getTeamUsers(array $teamIds): \Illuminate\Support\Collection
    {
        return DB::table('user')
            ->join('transactional_team as tt', 'tt.user_id', '=', 'user.user_id')
            ->select('user.user_id', 'user.nname', 'user.surename', 'user.is_active')
            ->where('user.role_id', 3)
            ->whereIn('tt.team_id', $teamIds)
            ->distinct()
            ->orderBy('user.nname')
            ->orderBy('user.surename')
            ->get();
    }

    public function reportBidding(Request $request)
    {
        $teamIds = $this->getMyTeamIds();
        $availableUsers = $this->getTeamUsers($teamIds);
        return view('teamadmin.reports.bidding', compact('availableUsers'));
    }

    public function reportBiddingData(Request $request)
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:user,user_id',
            'date_from'   => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d',
            'export_type' => 'nullable|in:excel',
        ]);

        $teamIds    = $this->getMyTeamIds();
        $userId     = $request->integer('user_id') ?: null;
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $exportType = $request->get('export_type');

        if ($exportType === 'excel') {
            return Excel::download(
                new \App\Exports\TeamAdmin\BiddingTeamExport($teamIds, $userId, $dateFrom, $dateTo),
                'รายงานวันยื่น_Bidding_' . date('Y-m-d') . '.xlsx'
            );
        }

        $query = DB::table('transactional as t')
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->leftJoin('user as u', 't.user_id', '=', 'u.user_id')
            ->select([
                't.Product_detail as project_name',
                'c.company as company_name',
                't.product_value as value',
                't.date_of_closing_of_sale as bidding_date',
                DB::raw("CONCAT(u.nname, ' ', u.surename) as user_name"),
            ])
            ->whereNotNull('t.date_of_closing_of_sale')
            ->whereIn('t.team_id', $teamIds);

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
                    'value'        => number_format($item->value ?? 0, 2),
                    'bidding_date' => $item->bidding_date ? \Carbon\Carbon::parse($item->bidding_date)->format('d/m/Y') : '-',
                    'user_name'    => $item->user_name ?? '-',
                ];
            }),
        ]);
    }

    public function reportContract(Request $request)
    {
        $teamIds = $this->getMyTeamIds();
        $availableUsers = $this->getTeamUsers($teamIds);
        return view('teamadmin.reports.contract', compact('availableUsers'));
    }

    public function reportContractData(Request $request)
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:user,user_id',
            'date_from'   => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d',
            'export_type' => 'nullable|in:excel',
        ]);

        $teamIds    = $this->getMyTeamIds();
        $userId     = $request->integer('user_id') ?: null;
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $exportType = $request->get('export_type');

        if ($exportType === 'excel') {
            return Excel::download(
                new \App\Exports\TeamAdmin\ContractTeamExport($teamIds, $userId, $dateFrom, $dateTo),
                'รายงานวันเซ็นสัญญา_' . date('Y-m-d') . '.xlsx'
            );
        }

        $query = DB::table('transactional as t')
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->leftJoin('user as u', 't.user_id', '=', 'u.user_id')
            ->select([
                't.Product_detail as project_name',
                'c.company as company_name',
                't.product_value as value',
                't.sales_can_be_close as contract_date',
                DB::raw("CONCAT(u.nname, ' ', u.surename) as user_name"),
            ])
            ->whereNotNull('t.sales_can_be_close')
            ->whereIn('t.team_id', $teamIds);

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
                    'project_name'  => $item->project_name ?? '-',
                    'company_name'  => $item->company_name ?? '-',
                    'value'         => number_format($item->value ?? 0, 2),
                    'contract_date' => $item->contract_date ? \Carbon\Carbon::parse($item->contract_date)->format('d/m/Y') : '-',
                    'user_name'     => $item->user_name ?? '-',
                ];
            }),
        ]);
    }

    public function reportWindate(Request $request)
    {
        $teamIds = $this->getMyTeamIds();
        $availableUsers = $this->getTeamUsers($teamIds);
        return view('teamadmin.reports.windate', compact('availableUsers'));
    }

    public function reportWindateData(Request $request)
    {
        $request->validate([
            'user_id'     => 'nullable|integer|exists:user,user_id',
            'date_from'   => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d',
            'export_type' => 'nullable|in:excel',
        ]);

        $teamIds    = $this->getMyTeamIds();
        $userId     = $request->integer('user_id') ?: null;
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $exportType = $request->get('export_type');

        if ($exportType === 'excel') {
            return Excel::download(
                new \App\Exports\TeamAdmin\WindateTeamExport($teamIds, $userId, $dateFrom, $dateTo),
                'รายงาน_Windate_' . date('Y-m-d') . '.xlsx'
            );
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
                DB::raw("CONCAT(u.nname, ' ', u.surename) as user_name"),
            ])
            ->whereNull('t.deleted_at')
            ->whereIn('t.team_id', $teamIds);

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
                    'value'        => number_format($item->value ?? 0, 2),
                    'win_date'     => $item->win_date ? \Carbon\Carbon::parse($item->win_date)->format('d/m/Y') : '-',
                    'user_name'    => $item->user_name ?? '-',
                ];
            }),
        ]);
    }
}
