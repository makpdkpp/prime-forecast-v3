<?php

namespace App\Http\Controllers;

use App\Services\McpSalesCreateAccess;
use App\Services\McpSalesDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class McpSalesConfirmationController extends Controller
{
    public function __construct(private McpSalesDraftService $drafts, private McpSalesCreateAccess $access) {}

    public function show(Request $request, string $draftId)
    {
        abort_unless($this->access->eligible($request->user()), 403);
        $draft = $this->drafts->owned($request->user(), $draftId);
        $payload = $this->drafts->payload($draft);
        $labels = [
            'company_id' => DB::table('company_catalog')->where('company_id', $payload['company_id'])->value('company'),
            'Product_id' => DB::table('product_group')->where('product_id', $payload['Product_id'])->value('product'),
            'team_id' => DB::table('team_catalog')->where('team_id', $payload['team_id'])->value('team'),
            'priority_id' => DB::table('priority_level')->where('priority_id', $payload['priority_id'])->value('priority'),
            'Source_budget_id' => DB::table('source_of_the_budget')->where('Source_budget_id', $payload['Source_budget_id'])->value('Source_budge'),
        ];

        return response()->view('mcp.sales-confirmation', [
            'draft' => $this->drafts->result($draft), 'payload' => $payload, 'labels' => $labels,
            'steps' => DB::table('step')->pluck('level', 'level_id'), 'user' => $request->user(),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, string $draftId)
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $this->drafts->confirm($request->user(), $draftId);

        return redirect()->route('mcp.sales-drafts.show', $draftId);
    }
}
