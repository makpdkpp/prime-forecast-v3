<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Services\McpPrincipalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuditEventController extends Controller
{
    public function store(Request $request, McpPrincipalResolver $resolver): JsonResponse
    {
        $principal = $resolver->resolve();
        $validator = Validator::make($request->all(), [
            'tool_name' => ['required', 'string', 'max:120'],
            'decision' => ['required', 'in:allowed,denied'],
            'arguments' => ['nullable', 'array'],
            'scope' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => ['code' => 'validation_error', 'message' => 'Invalid audit event.', 'details' => $validator->errors()]], 422);
        }

        DB::table('mcp_audit_logs')->insert([
            'request_id' => Str::isUuid((string) $request->header('X-Request-Id'))
                ? (string) $request->header('X-Request-Id')
                : (string) Str::uuid(),
            'trace_id' => (string) ($request->header('X-Trace-Id') ?: ''),
            'environment' => (string) app()->environment(),
            'user_id' => $principal->userId,
            'role' => $principal->role,
            'token_id' => $principal->tokenId,
            'tool_name' => $request->string('tool_name')->toString(),
            'endpoint' => '/' . ltrim($request->path(), '/'),
            'arguments' => json_encode($this->redact($request->input('arguments', [])), JSON_UNESCAPED_UNICODE),
            'scope' => json_encode($request->input('scope', []), JSON_UNESCAPED_UNICODE),
            'decision' => $request->string('decision')->toString(),
            'http_status' => 200,
            'duration_ms' => 0,
            'items_returned' => null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return response()->json(['data' => ['recorded' => true]], 201);
    }

    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            $redacted[$key] = preg_match('/token|secret|password|api[_-]?key|cookie|authorization/', $normalizedKey)
                ? '[REDACTED]'
                : $this->redact($item);
        }

        return $redacted;
    }
}
