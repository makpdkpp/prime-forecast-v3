<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class McpAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $storageReady = Schema::hasTable('mcp_audit_logs');

        if (! $storageReady) {
            return view('admin.mcp-audit-logs.index', [
                'storageReady' => false,
                'logs' => null,
                'summary' => ['total' => 0, 'allowed' => 0, 'denied' => 0, 'errors' => 0],
                'filters' => $filters,
                'tools' => collect(),
            ]);
        }

        $query = $this->filteredQuery($filters);
        $summary = [
            'total' => (clone $query)->count(),
            'allowed' => (clone $query)->where('decision', 'allowed')->count(),
            'denied' => (clone $query)->where('decision', 'denied')->count(),
            'errors' => (clone $query)->where('http_status', '>=', 400)->count(),
        ];

        $logs = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(50)->withQueryString();
        $tools = DB::table('mcp_audit_logs')->whereNotNull('tool_name')->distinct()->orderBy('tool_name')->pluck('tool_name');

        return view('admin.mcp-audit-logs.index', compact('storageReady', 'logs', 'summary', 'filters', 'tools'));
    }

    public function export(Request $request)
    {
        abort_unless(Schema::hasTable('mcp_audit_logs'), 503, 'MCP audit storage is not ready.');

        $filters = $this->validatedFilters($request);
        $records = $this->filteredQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5000)
            ->get()
            ->map(fn ($row) => [
                'request_id' => $row->request_id,
                'trace_id' => $row->trace_id ?: null,
                'created_at' => $row->created_at,
                'environment' => $row->environment,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'role' => $row->role,
                'tool_name' => $row->tool_name,
                'endpoint' => $row->endpoint,
                'arguments' => $this->decodeJson($row->arguments),
                'scope' => $this->decodeJson($row->scope),
                'decision' => $row->decision,
                'http_status' => (int) $row->http_status,
                'duration_ms' => (int) $row->duration_ms,
                'items_returned' => $row->items_returned === null ? null : (int) $row->items_returned,
            ]);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'filters' => $filters,
            'record_count' => $records->count(),
            'records' => $records->all(),
        ];
        $filename = 'prime-forecast-mcp-audit-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store'],
        );
    }

    private function validatedFilters(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'role' => ['nullable', 'in:sales,team_admin,admin'],
            'decision' => ['nullable', 'in:allowed,denied'],
            'status' => ['nullable', 'integer', 'between:100,599'],
            'tool' => ['nullable', 'string', 'max:120'],
            'request_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        return array_filter($validator->validated(), static fn ($value) => $value !== null && $value !== '');
    }

    private function filteredQuery(array $filters): Builder
    {
        return DB::table('mcp_audit_logs')
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['role']), fn ($query) => $query->where('role', $filters['role']))
            ->when(isset($filters['decision']), fn ($query) => $query->where('decision', $filters['decision']))
            ->when(isset($filters['status']), fn ($query) => $query->where('http_status', (int) $filters['status']))
            ->when(isset($filters['tool']), fn ($query) => $query->where('tool_name', $filters['tool']))
            ->when(isset($filters['request_id']), fn ($query) => $query->where('request_id', $filters['request_id']));
    }

    private function decodeJson(?string $value): array
    {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
