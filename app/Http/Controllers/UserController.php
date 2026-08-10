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

class UserController extends Controller
{
    public function dashboard(Request $request)
    {
        $userId = auth()->id();
        $resolvedYear = $this->resolveDashboardYearFilter($request);
        $year = $resolvedYear['year'];
        $selectedYear = $resolvedYear['selected'];
        $quarter = $request->get('quarter');
        
        // Get available years from transactional data
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
        
        // Load data for charts from the database
        $saleStepData = $this->getSaleStepData($userId, $year, $quarter);
        $winForecastData = $this->getWinForecastData($userId, $year, $quarter);
        $sumValuePercentData = $this->getSumValuePercentData($userId, $year, $quarter);
        
        return view('user.dashboard', compact(
            'saleStepData',
            'winForecastData',
            'sumValuePercentData',
            'availableYears',
            'selectedYear',
            'quarter'
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

        return view('user.dashboard_table', compact('availableYears', 'selectedYear', 'quarter'));
    }

    public function dashboardTableData(Request $request)
    {
        $userId = auth()->id();
        $year    = $request->get('year');
        $quarter = $request->get('quarter');

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
        $validated = $request->validate([
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

        abort_unless($this->currentUserBelongsToTeam((int) $validated['team_id']), 403);

        // Remove comma from product_value and convert to number
        $productValue = str_replace(',', '', $request->product_value);

        // Get the highest step level selected
        $stepId = null;
        if ($request->has('step') && is_array($request->step)) {
            $selectedSteps = array_keys(array_filter($request->step));
            if (!empty($selectedSteps)) {
                $stepId = max($selectedSteps);
            }
        }

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

        $validator = \Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        abort_unless($this->currentUserBelongsToTeam((int) $request->team_id), 403);

        try {
            $productValue = str_replace(',', '', $request->product_value);

            $stepId = null;
            if ($request->has('step') && is_array($request->step)) {
                $selectedSteps = array_keys(array_filter($request->step));
                if (!empty($selectedSteps)) {
                    $stepId = max($selectedSteps);
                }
            }

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
        
        $request->validate([
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

        abort_unless($this->currentUserBelongsToTeam((int) $request->team_id), 403);

        try {
            $productValue = str_replace(',', '', $request->product_value);

            $stepId = null;
            if ($request->has('step') && is_array($request->step)) {
                $selectedSteps = array_keys(array_filter($request->step));
                if (!empty($selectedSteps)) {
                    $stepId = max($selectedSteps);
                }
            }

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
        
        return view('user.profile', compact('user', 'roles', 'positions', 'twoFactorEnabled'));
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
        $params = [$userId];
        $extraWhere = "";
        
        if ($year) {
            $extraWhere .= ' AND t.fiscalyear = ?';
            $params[] = $year;
        }
        
        if ($quarter) {
            $extraWhere .= ' AND QUARTER(t.contact_start_date) = ?';
            $params[] = $quarter;
        }
        
        return DB::select("
            SELECT 
                DATE_FORMAT(ts.date, '%Y-%m') as month,
                SUM(CASE WHEN s.orderlv = 1 THEN t.product_value ELSE 0 END) as present_value,
                SUM(CASE WHEN s.orderlv = 2 THEN t.product_value ELSE 0 END) as budgeted_value,
                SUM(CASE WHEN s.orderlv = 3 THEN t.product_value ELSE 0 END) as tor_value,
                SUM(CASE WHEN s.orderlv = 4 THEN t.product_value ELSE 0 END) as bidding_value,
                SUM(CASE WHEN s.orderlv = 5 THEN t.product_value ELSE 0 END) as win_value,
                SUM(CASE WHEN s.orderlv = 6 THEN t.product_value ELSE 0 END) as lost_value
            FROM transactional t
            JOIN transactional_step ts ON t.transac_id = ts.transac_id
            JOIN step s ON s.level_id = ts.level_id
            WHERE (ts.transacstep_id, ts.transac_id) IN (
                SELECT MAX(ts2.transacstep_id), ts2.transac_id
                FROM transactional_step ts2
                GROUP BY ts2.transac_id
            )
            AND t.user_id = ?
            {$extraWhere}
            GROUP BY DATE_FORMAT(ts.date, '%Y-%m')
            ORDER BY month
        ", $params);
    }

    private function getWinForecastData($userId, $year = null, $quarter = null)
    {
        // Target from user_forecast_target
        $targetParams = [$userId];
        $targetWhere = "";
        if ($year) {
            $targetWhere .= " AND fiscal_year = ?";
            $targetParams[] = $year;
        }
        $targetResult = DB::select("
            SELECT COALESCE(SUM(target_value), 0) as target_value
            FROM user_forecast_target
            WHERE user_id = ? {$targetWhere}
        ", $targetParams);
        $target = $targetResult[0]->target_value ?? 0;

        // Forecast = sum of all transactions for this user
        $forecastParams = [$userId];
        $forecastWhere = "";
        if ($year) {
            $forecastWhere .= " AND t.fiscalyear = ?";
            $forecastParams[] = $year;
        }
        if ($quarter) {
            $forecastWhere .= " AND QUARTER(t.contact_start_date) = ?";
            $forecastParams[] = $quarter;
        }
        $forecastResult = DB::select("
            SELECT COALESCE(SUM(t.product_value), 0) as forecast_value
            FROM transactional t
            WHERE t.user_id = ? {$forecastWhere}
        ", $forecastParams);
        $forecast = $forecastResult[0]->forecast_value ?? 0;

        // Win = sum of transactions whose latest step is WIN (level = 5)
        $winParams = [$userId];
        $winWhere = "";
        if ($year) {
            $winWhere .= " AND t.fiscalyear = ?";
            $winParams[] = $year;
        }
        if ($quarter) {
            $winWhere .= " AND QUARTER(t.contact_start_date) = ?";
            $winParams[] = $quarter;
        }
        $winResult = DB::select("
            SELECT COALESCE(SUM(t.product_value), 0) as win_value
            FROM transactional t
            JOIN (
                SELECT ts.transac_id
                FROM transactional_step ts
                JOIN step s ON s.level_id = ts.level_id
                WHERE s.orderlv = 5
                AND (ts.transacstep_id, ts.transac_id) IN (
                    SELECT MAX(ts2.transacstep_id), ts2.transac_id
                    FROM transactional_step ts2
                    GROUP BY ts2.transac_id
                )
            ) wintrans ON wintrans.transac_id = t.transac_id
            WHERE t.user_id = ? {$winWhere}
        ", $winParams);
        $win = $winResult[0]->win_value ?? 0;

        return (object)[
            'Target' => $target,
            'Forecast' => $forecast,
            'Win' => $win,
        ];
    }

    private function getSumValuePercentData($userId, $year = null, $quarter = null)
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
