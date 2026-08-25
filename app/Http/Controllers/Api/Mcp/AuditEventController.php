<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuditEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id' => ['required', 'uuid'],
            'occurred_at' => ['required', 'date'],
            'phase' => ['required', 'in:read-only'],
            'actor_user_id' => ['required', 'integer', 'exists:user,user_id'],
            'actor_role' => ['required', 'in:sales,team_admin,admin'],
            'team_ids' => ['present', 'array'],
            'team_ids.*' => ['integer', 'min:1'],
            'tool' => ['required', 'string', 'max:120'],
            'allowed' => ['required', 'boolean'],
            'outcome' => ['required', 'string', 'max:120'],
            'http_status' => ['nullable', 'integer', 'between:100,599'],
            'argument_keys' => ['present', 'array'],
            'argument_keys.*' => ['string', 'max:120'],
            'duration_ms' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => ['code' => 'validation_error', 'message' => 'Invalid audit event.', 'details' => $validator->errors()]], 422);
        }

        $validated = $validator->validated();
        $user = DB::table('user')->where('user_id', $validated['actor_user_id'])->first(['role_id', 'is_active']);
        $actualRole = match ((int) $user->role_id) {
            1 => 'admin',
            2 => 'team_admin',
            3 => 'sales',
            default => 'unknown',
        };

        if (! $user->is_active || $actualRole !== $validated['actor_role']) {
            return response()->json([
                'error' => ['code' => 'invalid_actor', 'message' => 'Audit actor does not match an active MCP principal.'],
            ], 422);
        }

        $httpStatus = $validated['http_status'] ?? match ($validated['outcome']) {
            'success' => 200,
            'permission_denied' => 403,
            default => 500,
        };

        DB::table('mcp_audit_logs')->insert([
            'request_id' => $validated['request_id'],
            'trace_id' => (string) ($request->header('X-Trace-Id') ?: ''),
            'environment' => (string) app()->environment(),
            'user_id' => (int) $validated['actor_user_id'],
            'role' => $actualRole,
            'token_id' => null,
            'tool_name' => $validated['tool'],
            'endpoint' => '/mcp/tools/'.$validated['tool'],
            'arguments' => json_encode(['keys' => $validated['argument_keys']], JSON_UNESCAPED_UNICODE),
            'scope' => json_encode(['team_ids' => array_map('intval', $validated['team_ids'])], JSON_UNESCAPED_UNICODE),
            'decision' => $validated['allowed'] ? 'allowed' : 'denied',
            'http_status' => $httpStatus,
            'duration_ms' => $validated['duration_ms'],
            'items_returned' => null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return response()->json(['data' => ['accepted' => true]], 202);
    }
}
