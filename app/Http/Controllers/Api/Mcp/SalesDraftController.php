<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Services\McpSalesDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesDraftController extends Controller
{
    public function __construct(private McpSalesDraftService $drafts) {}

    public function options(Request $request)
    {
        $user = $request->user();
        $this->drafts->authorize($user, $user->currentAccessToken());
        $data = $request->validate(['company_search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $companies = DB::table('company_catalog')->when($data['company_search'] ?? null, fn ($q, $search) => $q->where('company', 'like', '%'.$search.'%'))
            ->orderBy('company')->orderBy('company_id')->paginate(50, ['company_id', 'company']);

        return response()->json(['data' => [
            'companies' => $companies->items(),
            'companies_meta' => ['page' => $companies->currentPage(), 'last_page' => $companies->lastPage(), 'total' => $companies->total()],
            'teams' => DB::table('team_catalog')->whereIn('team_id', DB::table('transactional_team')->where('user_id', $user->user_id)->select('team_id'))->get(['team_id', 'team']),
            'products' => DB::table('product_group')->get(['product_id', 'product']),
            'budget_sources' => DB::table('source_of_the_budget')->get(['Source_budget_id', 'Source_budge']),
            'priorities' => DB::table('priority_level')->get(['priority_id', 'priority']),
            'steps' => DB::table('step')->orderBy('orderlv')->get(['level_id', 'level', 'orderlv']),
            'sales_id' => (int) $user->user_id,
            'sales_name' => trim($user->nname.' '.$user->surename),
            'date_format' => 'YYYY-MM-DD (Gregorian)', 'currency' => 'THB',
        ]]);
    }

    public function store(Request $request)
    {
        $this->drafts->authorize($request->user(), $request->user()->currentAccessToken());
        $data = $request->validate(['idempotency_key' => ['required', 'uuid'], 'project' => ['required', 'array']]);
        abort_if(array_diff(array_keys($request->all()), ['idempotency_key', 'project']), 422, 'Unexpected fields.');
        $draft = $this->drafts->prepare($request->user(), $request->user()->currentAccessToken(), $data['idempotency_key'], $data['project']);

        return response()->json(['data' => $this->drafts->result($draft)], 200)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $draftId)
    {
        $this->drafts->authorize($request->user(), $request->user()->currentAccessToken());

        return response()->json(['data' => $this->drafts->result($this->drafts->owned($request->user(), $draftId))])->header('Cache-Control', 'no-store');
    }
}
