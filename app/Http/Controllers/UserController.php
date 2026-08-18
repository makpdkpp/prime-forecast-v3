<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Transactional;
use App\Models\TransactionalStep;
use App\Models\CompanyCatalog;
use App\Models\ProductGroup;
use App\Models\PriorityLevel;
use App\Models\SourceBudget;
use App\Models\Step;
use App\Models\TeamCatalog;
use App\Models\TransactionalTeam;
use App\Models\RoleCatalog;
use App\Models\Position;
use App\Models\CompanyRequest;
use App\Models\User;
use App\Support\ProjectTimelineValidator;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function dashboard(Request $request)
    {
        $userId = auth()->id();
        $resolvedYear = $this->resolveDashboardYearFilter($request);
        $year = $resolvedYear['year'];
        $selectedYear = $resolvedYear['selected'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;
        
        // Get available years from transactional data
        $availableYears = DB::table('transactional')
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->select('fiscalyear')
            ->distinct()
            ->orderBy('fiscalyear', 'desc')
            ->pluck('fiscalyear')
            ->toArray();
        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $availableYears, true)) {
            $availableYears[] = $currentYear;
            rsort($availableYears);
        }
        
        // Load data for charts from the database
        $saleStepData = $this->getSaleStepData($userId, $year, $quarter);
        $winForecastData = $this->getWinForecastData($userId, $year, $quarter);
        $sumValuePercentData = $this->getSumValuePercentData($userId, $year, $quarter);

        $projectQuery = $this->accurateUserDashboardBaseQuery($userId, $year, $quarter);

        $projectCount = (clone $projectQuery)->count('t.transac_id');
        $recentProjects = (clone $projectQuery)
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->select([
                't.transac_id',
                't.Product_detail',
                't.product_value',
                't.updated_at',
                'c.company',
                'current_step.level as step_level',
            ])
            ->orderByDesc('t.updated_at')
            ->limit(5)
            ->get();

        $attentionProjects = (clone $projectQuery)
            ->leftJoin('company_catalog as c', 't.company_id', '=', 'c.company_id')
            ->where(function ($query) {
                $query->whereNotNull('t.date_of_closing_of_sale')
                    ->orWhereNotNull('t.sales_can_be_close');
            })
            ->whereRaw('COALESCE(t.date_of_closing_of_sale, t.sales_can_be_close) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)')
            ->select([
                't.transac_id',
                't.Product_detail',
                't.product_value',
                'c.company',
                'current_step.level as step_level',
                DB::raw('COALESCE(t.date_of_closing_of_sale, t.sales_can_be_close) as due_date'),
            ])
            ->orderByRaw('CASE WHEN COALESCE(t.date_of_closing_of_sale, t.sales_can_be_close) < CURDATE() THEN 1 ELSE 0 END ASC')
            ->orderByRaw('ABS(DATEDIFF(COALESCE(t.date_of_closing_of_sale, t.sales_can_be_close), CURDATE())) ASC')
            ->limit(4)
            ->get();
        
        return view('user.dashboard', compact(
            'saleStepData',
            'winForecastData',
            'sumValuePercentData',
            'availableYears',
            'selectedYear',
            'quarter',
            'projectCount',
            'recentProjects',
            'attentionProjects'
        ));
    }

    public function dashboardTable(Request $request)
    {
        $userId = auth()->id();

        // Get filter parameters — default to "all years" for table view
        $yearInput = $request->query('year');
        $hasYearParam = $request->query->has('year');
        if (!$hasYearParam || $yearInput === null || $yearInput === '' || strtolower(trim((string) $yearInput)) === 'all') {
            $selectedYear = 'all';
        } else {
            $selectedYear = (string) (int) $yearInput;
        }
        $quarter = $request->get('quarter');
        $status = $request->get('status');

        // Get available years (table rows are loaded via dashboardTableData AJAX)
        $availableYears = DB::table('transactional')
            ->where('user_id', $userId)
            ->select('fiscalyear')
            ->distinct()
            ->orderBy('fiscalyear', 'desc')
            ->pluck('fiscalyear')
            ->toArray();
        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $availableYears, true)) {
            $availableYears[] = $currentYear;
            rsort($availableYears);
        }

        $summaryQuery = DB::table('transactional as t')
            ->whereNull('t.deleted_at')
            ->where('t.user_id', $userId)
            ->leftJoin('step as s', 't.Step_id', '=', 's.level_id');

        if ($selectedYear !== 'all') {
            $summaryQuery->where('t.fiscalyear', (int) $selectedYear);
        }
        if ($quarter) {
            $summaryQuery->whereRaw('QUARTER(t.contact_start_date) = ?', [$quarter]);
        }
        if ($status) {
            $summaryQuery->where('t.Step_id', (int) $status);
        }

        $projectSummary = $summaryQuery->selectRaw('
            COUNT(t.transac_id) as total_count,
            COALESCE(SUM(t.product_value), 0) as total_value,
            SUM(CASE WHEN s.orderlv = 5 THEN 1 ELSE 0 END) as win_count,
            SUM(CASE WHEN s.orderlv = 6 THEN 1 ELSE 0 END) as lost_count
        ')->first();

        $projectSummary->active_count = max(
            (int) $projectSummary->total_count - (int) $projectSummary->win_count - (int) $projectSummary->lost_count,
            0
        );

        $availableSteps = Step::orderBy('orderlv')->get(['level_id', 'level']);

        return view('user.dashboard_table', compact('availableYears', 'availableSteps', 'selectedYear', 'quarter', 'status', 'projectSummary'));
    }

    public function dashboardTableData(Request $request)
    {
        $userId = auth()->id();
        $year    = $request->get('year');
        $quarter = $request->get('quarter');
        $status  = $request->get('status');

        $draw   = (int) $request->input('draw', 1);
        $start  = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        if ($length <= 0 || $length > 200) {
            $length = 25;
        }

        $base = DB::table('transactional as t')
            ->whereNull('t.deleted_at')
            ->where('t.user_id', $userId)
            ->leftJoin('company_catalog as c',        't.company_id',        '=', 'c.company_id')
            ->leftJoin('product_group as pg',         't.Product_id',        '=', 'pg.product_id')
            ->leftJoin('team_catalog as tc',          't.team_id',           '=', 'tc.team_id')
            ->leftJoin('priority_level as pl',        't.priority_id',       '=', 'pl.priority_id')
            ->leftJoin('source_of_the_budget as sb',  't.Source_budget_id',  '=', 'sb.Source_budget_id')
            ->leftJoin('step as s',                   't.Step_id',           '=', 's.level_id');

        if ($year) {
            $base->where('t.fiscalyear', $year);
        }
        if ($quarter) {
            $base->whereRaw('QUARTER(t.contact_start_date) = ?', [$quarter]);
        }
        if ($status) {
            $base->where('t.Step_id', (int) $status);
        }

        $total = (clone $base)->count('t.transac_id');

        $searchValue = trim((string) data_get($request->input('search'), 'value', ''));
        if ($searchValue !== '') {
            $base->where(function ($q) use ($searchValue) {
                $like = '%' . $searchValue . '%';
                $q->where('t.Product_detail',  'like', $like)
                  ->orWhere('c.company',        'like', $like)
                  ->orWhere('pg.product',        'like', $like)
                  ->orWhere('s.level',           'like', $like)
                  ->orWhere('pl.priority',       'like', $like)
                  ->orWhere('tc.team',           'like', $like)
                  ->orWhere('t.remark',          'like', $like);
            });
        }

        $filtered = (clone $base)->count('t.transac_id');

        $orderMap = [
            0  => 't.Product_detail',
            1  => 'c.company',
            2  => 't.product_value',
            3  => 's.level',
            4  => 'pl.priority',
            5  => 't.fiscalyear',
            6  => 't.contact_start_date',
            7  => 't.date_of_closing_of_sale',
            8  => 't.sales_can_be_close',
            9  => 'pg.product',
            10 => 'tc.team',
            11 => 't.remark',
        ];

        $orderCol = (int) data_get($request->input('order'), '0.column', 6);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $orderMap[$orderCol] ?? 't.updated_at';

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
                't.contact_person',
                't.contact_phone',
                't.contact_email',
                'c.company',
                'pg.product as product_name',
                'tc.team',
                'pl.priority',
                'sb.Source_budge as source_budget',
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
                'id'             => $id,
                'project'        => $r->Product_detail,
                'company'        => $r->company        ?? '-',
                'value'          => (float) $r->product_value,
                'status'         => $r->step_level     ?? '-',
                'priority'       => $r->priority       ?? '-',
                'year'           => $r->fiscalyear ? ((int) $r->fiscalyear + 543) : '-',
                'start'          => $r->contact_start_date,
                'bidding'        => $r->date_of_closing_of_sale,
                'contract'       => $r->sales_can_be_close,
                'product'        => $r->product_name   ?? '-',
                'team'           => $r->team            ?? '-',
                'source'         => $r->source_budget  ?? '-',
                'contact_person' => $r->contact_person ?? '-',
                'contact_phone'  => $r->contact_phone  ?? '-',
                'contact_email'  => $r->contact_email  ?? '-',
                'remark'         => $r->remark          ?? '-',
                'action'         => '<a href="' . route('user.sales.edit', $id) . '" class="btn btn-sm btn-info" title="แก้ไข"><i class="fas fa-pencil-alt"></i></a>',
            ];
        })->values();

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function chartDetail(Request $request)
    {
        $userId = (int) auth()->id();
        $type = (string) $request->query('type');
        $value = $request->query('value');
        $value2 = $request->query('value2');
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;
        $effectiveDate = $this->dashboardEffectiveDateSql();

        $query = $this->accurateUserDashboardBaseQuery($userId, $year, $quarter)
            ->leftJoin('company_catalog as detail_company', 't.company_id', '=', 'detail_company.company_id')
            ->leftJoin('product_group as detail_product', 't.Product_id', '=', 'detail_product.product_id')
            ->leftJoin('team_catalog as detail_team', 't.team_id', '=', 'detail_team.team_id');

        switch ($type) {
            case 'month':
                $query->where('current_step.orderlv', 5)
                    ->whereRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') = ?", [$value]);
                break;
            case 'step':
                $query->whereRaw('COALESCE(current_step.orderlv, 0) = ?', [(int) $value]);
                if ($value2) {
                    $query->whereRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') = ?", [$value2]);
                }
                break;
            case 'product':
                $query->where('t.Product_id', $value);
                break;
            case 'user_forecast':
                break;
            case 'user_win':
                $query->where('current_step.orderlv', 5);
                break;
            default:
                return response()->json([]);
        }

        return response()->json($query
            ->selectRaw("t.transac_id, t.Product_detail, t.product_value, detail_company.company, detail_product.product as product_group, detail_team.team, COALESCE(current_step.level, 'ยังไม่ระบุสถานะ') as step_name, latest_step.date as win_date, t.contact_start_date")
            ->orderByDesc('t.product_value')
            ->get());
    }

    /** @deprecated Kept temporarily until the shared dashboard rollout is accepted. */
    private function legacyChartDetail(Request $request)
    {
        $userId = auth()->id();
        $type = $request->get('type');
        $value = $request->get('value');
        $value2 = $request->get('value2');
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = $request->get('quarter');

        $params = [$userId];
        $where = " AND t.user_id = ?";

        // Filter by year/quarter using proper date column
        if ($type === 'step') {
            $this->appendYearSqlFilter($where, $params, $year, 'ts', 'date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'ts', 'date');
        } elseif ($type === 'month') {
            // month value already includes year, filter in extraWhere
        } elseif ($type === 'product') {
            // Use fiscalyear to match getSumValuePercentData
            if ($year !== null) {
                $where .= " AND t.fiscalyear = ?";
                $params[] = $year;
            }
            if ($quarter) {
                $where .= " AND QUARTER(t.contact_start_date) = ?";
                $params[] = $quarter;
            }
        } elseif ($type === 'user_forecast') {
            // Year/quarter filter handled inside switch case below (uses fiscalyear)
        } elseif ($type === 'user_win') {
            $this->appendYearSqlFilter($where, $params, $year, 'wintrans', 'win_date');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 'wintrans', 'win_date');
        } else {
            $this->appendYearSqlFilter($where, $params, $year, 't');
            $this->appendQuarterSqlFilter($where, $params, $year, $quarter, 't');
        }

        $winJoin = "
            JOIN (
                SELECT ts.transac_id, ts.date as win_date
                FROM transactional_step ts
                JOIN step s ON s.level_id = ts.level_id
                WHERE s.orderlv = 5
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            ) wintrans ON wintrans.transac_id = t.transac_id
        ";

        $stepJoin = "
            JOIN transactional_step ts ON t.transac_id = ts.transac_id
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            JOIN step s ON s.level_id = ts.level_id
        ";

        $extraJoin = "";
        $extraWhere = "";
        $extraParams = [];

        switch ($type) {
            case 'month':
                $extraJoin = $winJoin;
                $extraWhere = " AND DATE_FORMAT(wintrans.win_date, '%Y-%m') = ?";
                $extraParams[] = $value;
                break;
            case 'step':
                $extraJoin = $stepJoin;
                $extraWhere = " AND s.orderlv = ?";
                $extraParams[] = $value;
                if ($value2) {
                    $extraWhere .= " AND DATE_FORMAT(ts.date, '%Y-%m') = ?";
                    $extraParams[] = $value2;
                }
                break;
            case 'product':
                // Show all forecast for this product (not just WIN)
                $extraWhere = " AND t.Product_id = ?";
                $extraParams[] = $value;
                break;
            case 'user_forecast':
                // Filter to match getWinForecastData: use fiscalyear + QUARTER(contact_start_date)
                if ($year !== null) {
                    $where .= " AND t.fiscalyear = ?";
                    $params[] = $year;
                }
                if ($quarter) {
                    $where .= " AND QUARTER(t.contact_start_date) = ?";
                    $params[] = $quarter;
                }
                break;
            case 'user_win':
                $extraJoin = $winJoin;
                break;
            default:
                return response()->json([]);
        }

        $allParams = array_merge($params, $extraParams);

        // Only select wintrans.win_date when wintrans join is present
        $hasWinJoin = in_array($type, ['month', 'user_win']);
        $winDateSelect = $hasWinJoin ? "wintrans.win_date," : "NULL as win_date,";

        $projects = DB::select("
            SELECT
                t.transac_id,
                t.Product_detail,
                t.product_value,
                c.company,
                pg.product as product_group,
                tc.team,
                COALESCE(latest_s.level, '-') as step_name,
                {$winDateSelect}
                t.contact_start_date
            FROM transactional t
            {$extraJoin}
            LEFT JOIN company_catalog c ON t.company_id = c.company_id
            LEFT JOIN product_group pg ON t.Product_id = pg.product_id
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

    public function winProjects(Request $request)
    {
        $userId = (int) auth()->id();
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = in_array((string) $request->query('quarter'), ['1', '2', '3', '4'], true)
            ? (int) $request->query('quarter')
            : null;

        $projects = $this->accurateUserDashboardBaseQuery($userId, $year, $quarter)
            ->leftJoin('company_catalog as win_company', 't.company_id', '=', 'win_company.company_id')
            ->leftJoin('product_group as win_product', 't.Product_id', '=', 'win_product.product_id')
            ->where('current_step.orderlv', 5)
            ->selectRaw('t.transac_id, t.Product_detail, t.product_value, win_company.company, win_product.product as product_group, DATE_FORMAT(latest_step.date, "%Y-%m-%d") as win_date')
            ->orderByDesc('latest_step.date')
            ->get();

        return response()->json($projects);
    }

    /** @deprecated Kept temporarily until the shared dashboard rollout is accepted. */
    private function legacyWinProjects(Request $request)
    {
        $userId = auth()->id();
        $year = $this->resolveDashboardYearFilter($request)['year'];
        $quarter = $request->get('quarter');

        $params = [$userId];
        $where = "";
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
                WHERE s.orderlv = 5
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

    public function createSales()
    {
        // Cache master data for 1 hour
        $companies = \Cache::remember('companies_list', 3600, function() {
            return CompanyCatalog::orderBy('company')->get();
        });
        
        $products = \Cache::remember('products_list', 3600, function() {
            return ProductGroup::orderBy('product')->get();
        });
        
        $priorities = \Cache::remember('priorities_list', 3600, function() {
            return PriorityLevel::orderBy('priority')->get();
        });
        
        $sources = \Cache::remember('sources_list', 3600, function() {
            return SourceBudget::orderBy('Source_budge')->get();
        });
        
        $steps = \Cache::remember('steps_list', 3600, function() {
            return Step::orderBy('orderlv')->get();
        });
        
        // Get teams for this user (user-specific, not cached)
        $teamIds = TransactionalTeam::where('user_id', auth()->id())->pluck('team_id');
        $teams = TeamCatalog::whereIn('team_id', $teamIds)->orderBy('team')->get();
        
        return view('user.sales.create', compact('companies', 'products', 'priorities', 'sources', 'steps', 'teams'));
    }

    public function storeSales(Request $request)
    {
        // Validation - simplified for better performance
        $validator = Validator::make($request->all(), [
            'Product_detail' => 'required|max:255',
            'company_id' => 'required|integer|exists:company_catalog,company_id',
            'product_value' => 'required',
            'Source_budget_id' => 'required|integer|exists:source_of_the_budget,Source_budget_id',
            'fiscalyear' => 'required|integer',
            'Product_id' => 'required|integer|exists:product_group,product_id',
            'team_id' => 'required|integer|exists:team_catalog,team_id',
            'priority_id' => 'nullable|integer|exists:priority_level,priority_id',
            'contact_start_date' => 'required|date',
            'date_of_closing_of_sale' => 'nullable|date',
            'sales_can_be_close' => 'nullable|date',
            'step_date' => 'nullable|array',
            'step_date.*' => 'nullable|date',
        ]);
        ProjectTimelineValidator::attach($validator, $request->all());
        $validated = $validator->validate();

        abort_unless($this->currentUserBelongsToTeam((int) $validated['team_id']), 403);

        // Remove comma from product_value and convert to number
        $productValue = str_replace(',', '', $request->product_value);

        // Get the highest step level selected
        $stepId = ProjectTimelineValidator::latestSelectedStepId($request->all());

        // Create transactional record
        $transactional = Transactional::create([
            'user_id' => auth()->id(),
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
            'Step_id' => $stepId ?? 1,
            'present' => 0,
            'present_date' => null,
            'budgeted' => 0,
            'budgeted_date' => null,
            'tor' => 0,
            'tor_date' => null,
            'bidding' => 0,
            'bidding_date' => null,
            'win' => 0,
            'win_date' => null,
            'lost' => 0,
            'lost_date' => null,
        ]);

        // Save transactional steps if any
        if ($request->has('step') && is_array($request->step)) {
            foreach ($request->step as $levelId => $value) {
                if ($value && isset($request->step_date[$levelId]) && $request->step_date[$levelId]) {
                    TransactionalStep::create([
                        'transac_id' => $transactional->transac_id,
                        'level_id' => $levelId,
                        'date' => $request->step_date[$levelId],
                    ]);
                }
            }
        }

        return redirect()->route('user.dashboard')->with('success', 'บันทึกข้อมูลการขายเรียบร้อยแล้ว');
    }

    public function editSales($id)
    {
        $transaction = Transactional::where('transac_id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        // Cache master data for 1 hour
        $companies = \Cache::remember('companies_list', 3600, function() {
            return CompanyCatalog::orderBy('company')->get();
        });
        
        $products = \Cache::remember('products_list', 3600, function() {
            return ProductGroup::orderBy('product')->get();
        });
        
        $priorities = \Cache::remember('priorities_list', 3600, function() {
            return PriorityLevel::orderBy('priority')->get();
        });
        
        $sources = \Cache::remember('sources_list', 3600, function() {
            return SourceBudget::orderBy('Source_budge')->get();
        });
        
        $steps = \Cache::remember('steps_list', 3600, function() {
            return Step::orderBy('orderlv')->get();
        });
        
        // Get teams for this user (user-specific, not cached)
        $teamIds = TransactionalTeam::where('user_id', auth()->id())->pluck('team_id');
        $teams = TeamCatalog::whereIn('team_id', $teamIds)->orderBy('team')->get();
        
        // Get transaction steps (transaction-specific, not cached)
        $transactionSteps = TransactionalStep::where('transac_id', $id)
            ->get()
            ->keyBy('level_id');
        
        return view('user.sales.edit', compact(
            'transaction',
            'companies',
            'products',
            'priorities',
            'sources',
            'steps',
            'teams',
            'transactionSteps'
        ));
    }

    public function getEditDataAjax($id)
    {
        $transaction = Transactional::where('transac_id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $companies  = \Cache::remember('companies_list',  3600, fn() => CompanyCatalog::orderBy('company')->get());
        $products   = \Cache::remember('products_list',   3600, fn() => ProductGroup::orderBy('product')->get());
        $priorities = \Cache::remember('priorities_list', 3600, fn() => PriorityLevel::orderBy('priority')->get());
        $sources    = \Cache::remember('sources_list',    3600, fn() => SourceBudget::orderBy('Source_budge')->get());
        $steps      = \Cache::remember('steps_list',      3600, fn() => Step::orderBy('orderlv')->get());

        $teamIds = TransactionalTeam::where('user_id', auth()->id())->pluck('team_id');
        $teams   = TeamCatalog::whereIn('team_id', $teamIds)->orderBy('team')->get();

        $transactionSteps = TransactionalStep::where('transac_id', $id)
            ->get()
            ->keyBy('level_id')
            ->map(fn($ts) => [
                'level_id' => $ts->level_id,
                'date'     => $ts->date ? \Carbon\Carbon::parse($ts->date)->format('Y-m-d') : null,
            ]);

        return response()->json([
            'transaction'      => $transaction,
            'companies'        => $companies,
            'products'         => $products,
            'priorities'       => $priorities,
            'sources'          => $sources,
            'steps'            => $steps->map(fn($s) => ['level_id' => $s->level_id, 'level' => $s->level]),
            'teams'            => $teams,
            'transactionSteps' => $transactionSteps,
        ]);
    }

    public function updateSalesAjax(Request $request, $id)
    {
        $transaction = Transactional::where('transac_id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'Product_detail'           => 'required|max:255',
            'company_id'               => 'required|integer|exists:company_catalog,company_id',
            'product_value'            => 'required',
            'Source_budget_id'         => 'required|integer|exists:source_of_the_budget,Source_budget_id',
            'fiscalyear'               => 'required|integer',
            'Product_id'               => 'required|integer|exists:product_group,product_id',
            'team_id'                  => 'required|integer|exists:team_catalog,team_id',
            'priority_id'              => 'nullable|integer|exists:priority_level,priority_id',
            'contact_start_date'       => 'required|date',
            'date_of_closing_of_sale'  => 'nullable|date',
            'sales_can_be_close'       => 'nullable|date',
            'step_date'                => 'nullable|array',
            'step_date.*'              => 'nullable|date',
        ]);
        ProjectTimelineValidator::attach($validator, $request->all());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        abort_unless($this->currentUserBelongsToTeam((int) $request->team_id), 403);

        try {
            $productValue = str_replace(',', '', $request->product_value);

            $stepId = ProjectTimelineValidator::latestSelectedStepId($request->all());

            $transaction->update([
                'company_id'               => $request->company_id,
                'Product_id'               => $request->Product_id,
                'team_id'                  => $request->team_id,
                'priority_id'              => $request->priority_id,
                'Source_budget_id'         => $request->Source_budget_id,
                'Product_detail'           => $request->Product_detail,
                'product_value'            => $productValue,
                'fiscalyear'               => $request->fiscalyear,
                'contact_start_date'       => $request->contact_start_date,
                'date_of_closing_of_sale'  => $request->date_of_closing_of_sale ?: null,
                'sales_can_be_close'       => $request->sales_can_be_close ?: null,
                'remark'                   => $request->remark ?? '',
                'contact_person'           => $request->contact_person,
                'contact_phone'            => $request->contact_phone,
                'contact_email'            => $request->contact_email,
                'contact_note'             => $request->contact_note,
                'Step_id'                  => $stepId ?? $transaction->Step_id,
            ]);

            TransactionalStep::where('transac_id', $id)->delete();
            if ($request->has('step') && is_array($request->step)) {
                foreach ($request->step as $levelId => $value) {
                    if ($value && isset($request->step_date[$levelId]) && $request->step_date[$levelId]) {
                        TransactionalStep::create([
                            'transac_id' => $id,
                            'level_id'   => $levelId,
                            'date'       => $request->step_date[$levelId],
                        ]);
                    }
                }
            }

            return response()->json(['success' => true, 'message' => 'อัพเดทข้อมูลเรียบร้อยแล้ว']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'], 500);
        }
    }

    public function updateSales(Request $request, $id)
    {
        $transaction = Transactional::where('transac_id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'Product_detail' => 'required|max:255',
            'company_id' => 'required|integer|exists:company_catalog,company_id',
            'product_value' => 'required',
            'Source_budget_id' => 'required|integer|exists:source_of_the_budget,Source_budget_id',
            'fiscalyear' => 'required|integer',
            'Product_id' => 'required|integer|exists:product_group,product_id',
            'team_id' => 'required|integer|exists:team_catalog,team_id',
            'priority_id' => 'nullable|integer|exists:priority_level,priority_id',
            'contact_start_date' => 'required|date',
            'date_of_closing_of_sale' => 'nullable|date',
            'sales_can_be_close' => 'nullable|date',
            'step_date' => 'nullable|array',
            'step_date.*' => 'nullable|date',
        ]);
        ProjectTimelineValidator::attach($validator, $request->all());
        $validator->validate();

        abort_unless($this->currentUserBelongsToTeam((int) $request->team_id), 403);

        try {
            $productValue = str_replace(',', '', $request->product_value);

            $stepId = ProjectTimelineValidator::latestSelectedStepId($request->all());

            $transaction->update([
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

            TransactionalStep::where('transac_id', $id)->delete();

            if ($request->has('step') && is_array($request->step)) {
                foreach ($request->step as $levelId => $value) {
                    if ($value && isset($request->step_date[$levelId]) && $request->step_date[$levelId]) {
                        TransactionalStep::create([
                            'transac_id' => $id,
                            'level_id' => $levelId,
                            'date' => $request->step_date[$levelId],
                        ]);
                    }
                }
            }

            return redirect()->route('user.dashboard.table')->with('success', 'อัพเดทข้อมูลเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function profile()
    {
        $user = auth()->user();
        $roles = RoleCatalog::orderBy('role')->get();
        $positions = Position::orderBy('position')->get();
        $twoFactorEnabled = $user->two_factor_enabled;
        $profileStats = DB::table('transactional as t')
            ->leftJoin('step as s', 't.Step_id', '=', 's.level_id')
            ->whereNull('t.deleted_at')
            ->where('t.user_id', $user->user_id)
            ->selectRaw('COUNT(t.transac_id) as project_count, SUM(CASE WHEN s.orderlv = 5 THEN 1 ELSE 0 END) as win_count')
            ->first();
        
        return view('user.profile', compact('user', 'roles', 'positions', 'twoFactorEnabled', 'profileStats'));
    }

    public function toggleTwoFactor(Request $request)
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

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        // Validation
        $request->validate([
            'nname' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            // Update name and surname
            $user->nname = $request->nname;
            $user->surename = $request->surname;

            // Handle avatar upload
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
                
                // Create directory if not exists (cross-platform)
                $uploadPath = public_path('uploads' . DIRECTORY_SEPARATOR . 'avatars');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                // Move uploaded file
                $avatar->move($uploadPath, $fileName);
                
                // Update avatar path (use forward slash for web path)
                $user->avatar_path = 'uploads/avatars/' . $fileName;
            }

            $user->save();

            return redirect()->route('user.profile')->with('success', 'บันทึกข้อมูลสำเร็จ');
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('user.profile')->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }
    }

    public function requestCompany(Request $request)
    {
        // Validation
        $request->validate([
            'company_name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            // Create company request
            $companyRequest = CompanyRequest::create([
                'company_name' => $request->company_name,
                'notes' => $request->notes ?? '',
                'user_id' => auth()->id(),
                'request_date' => now(),
                'status' => 'pending',
            ]);

            // Get all Super Admin emails (role_id = 1)
            $adminEmails = User::where('role_id', 1)->pluck('email')->toArray();

            // Send email notification to admins
            if (!empty($adminEmails)) {
                $userEmail = auth()->user()->email;
                $companyName = $request->company_name;
                $notes = $request->notes ?? '';

                $safeEmail       = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
                $safeCompanyName = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
                $safeNotes       = nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'));
                $fromAddress     = config('mail.from.address', 'no-reply@primeforecast.com');
                $fromName        = config('mail.from.name', 'PrimeForecast System');

                Mail::send([], [], function ($message) use ($adminEmails, $safeEmail, $safeCompanyName, $safeNotes, $fromAddress, $fromName) {
                    $message->to($adminEmails)
                        ->subject('มีคำขอเพิ่มบริษัทใหม่')
                        ->from($fromAddress, $fromName)
                        ->html("
                            <h2>มีคำขอเพิ่มบริษัทใหม่เข้าระบบ</h2>
                            <p>คุณ <strong>{$safeEmail}</strong> ได้ส่งคำขอเพิ่มข้อมูลบริษัทใหม่ ดังนี้:</p>
                            <hr>
                            <p><strong>ชื่อบริษัทที่ขอเพิ่ม:</strong> {$safeCompanyName}</p>
                            <p><strong>รายละเอียดเพิ่มเติม:</strong><br>{$safeNotes}</p>
                            <hr>
                            <p>กรุณาเข้าระบบเพื่อตรวจสอบและดำเนินการอนุมัติคำขอนี้</p>
                        ");
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'คำขอถูกบันทึกแล้ว ระบบจะแจ้งเตือนผู้ดูแลระบบให้ตรวจสอบ'
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'
            ], 500);
        }
    }

    private function getSaleStepData($userId, $year = null, $quarter = null)
    {
        $effectiveDate = $this->dashboardEffectiveDateSql();

        return $this->accurateUserDashboardBaseQuery((int) $userId, $year, $quarter)
            ->selectRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m') as month")
            ->selectRaw('SUM(CASE WHEN current_step.orderlv = 1 THEN t.product_value ELSE 0 END) as present_value')
            ->selectRaw('SUM(CASE WHEN current_step.orderlv = 2 THEN t.product_value ELSE 0 END) as budgeted_value')
            ->selectRaw('SUM(CASE WHEN current_step.orderlv = 3 THEN t.product_value ELSE 0 END) as tor_value')
            ->selectRaw('SUM(CASE WHEN current_step.orderlv = 4 THEN t.product_value ELSE 0 END) as bidding_value')
            ->selectRaw('SUM(CASE WHEN current_step.orderlv = 5 THEN t.product_value ELSE 0 END) as win_value')
            ->selectRaw('SUM(CASE WHEN current_step.orderlv = 6 THEN t.product_value ELSE 0 END) as lost_value')
            ->groupByRaw("DATE_FORMAT({$effectiveDate}, '%Y-%m')")
            ->orderBy('month')
            ->get();
    }

    private function getWinForecastData($userId, $year = null, $quarter = null)
    {
        $base = $this->accurateUserDashboardBaseQuery((int) $userId, $year, $quarter);
        $target = DB::table('user_forecast_target')
            ->where('user_id', $userId)
            ->when($year !== null, fn ($query) => $query->where('fiscal_year', $year))
            ->sum('target_value');
        $forecast = (clone $base)->sum('t.product_value');
        $win = (clone $base)->where('current_step.orderlv', 5)->sum('t.product_value');

        return (object)[
            'Target' => $target,
            'Forecast' => $forecast,
            'Win' => $win,
        ];
    }

    private function getSumValuePercentData($userId, $year = null, $quarter = null)
    {
        return $this->accurateUserDashboardBaseQuery((int) $userId, $year, $quarter)
            ->leftJoin('product_group as pg', 'pg.product_id', '=', 't.Product_id')
            ->selectRaw("pg.product_id, COALESCE(pg.product, 'ไม่ระบุ') as product, SUM(t.product_value) as sum_value")
            ->groupBy('pg.product_id', 'pg.product')
            ->havingRaw('SUM(t.product_value) > 0')
            ->get();
    }

    /** @deprecated Kept temporarily until the shared dashboard rollout is accepted. */
    private function legacyGetSumValuePercentData($userId, $year = null, $quarter = null)
    {
        $params = [$userId];
        $whereClause = 'WHERE t.user_id = ?';
        
        if ($year) {
            $whereClause .= ' AND t.fiscalyear = ?';
            $params[] = $year;
        }
        
        if ($quarter) {
            $whereClause .= ' AND QUARTER(t.contact_start_date) = ?';
            $params[] = $quarter;
        }
        
        return DB::select("
            SELECT 
                pg.product_id,
                COALESCE(pg.product, 'ไม่ระบุ') as product,
                SUM(t.product_value) as sum_value
            FROM transactional t
            LEFT JOIN product_group pg ON pg.product_id = t.Product_id
            {$whereClause}
            GROUP BY pg.product_id, pg.product
            HAVING SUM(t.product_value) > 0
        ", $params);
    }

    private function accurateUserDashboardBaseQuery(int $userId, ?int $year, ?int $quarter)
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
            ->where('t.user_id', $userId);

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

    private function resolveDashboardYearFilter(Request $request)
    {
        $currentYear = (int) date('Y');
        $yearInput = $request->query('year');
        $hasYearParam = $request->query->has('year');

        if (!$hasYearParam || $yearInput === null || $yearInput === '') {
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

    private function appendYearSqlFilter(&$where, &$params, $year, $table = 't', $column = 'contact_start_date')
    {
        if ($year === null) {
            return;
        }

        $where .= " AND YEAR({$table}.{$column}) = ?";
        $params[] = $year;
    }

    private function appendQuarterSqlFilter(&$where, &$params, $year, $quarter, $table = 't', $column = 'contact_start_date')
    {
        if ($year === null || !$quarter) {
            return;
        }

        $where .= " AND QUARTER({$table}.{$column}) = ?";
        $params[] = $quarter;
    }

    private function currentUserBelongsToTeam(int $teamId): bool
    {
        return TransactionalTeam::query()
            ->where('user_id', auth()->id())
            ->where('team_id', $teamId)
            ->exists();
    }
}
