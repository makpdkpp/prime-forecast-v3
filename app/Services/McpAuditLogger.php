<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class McpAuditLogger
{
    public function record(Request $request, ?Response $response, float $startedAt, ?\Throwable $exception = null): void
    {
        try {
            $user = $request->user();
            $token = $user?->currentAccessToken();
            $status = $response?->getStatusCode() ?? (int) ($exception?->getCode() ?: 500);
            if ($status < 100 || $status > 599) {
                $status = 500;
            }

            $requestId = (string) $request->attributes->get('mcp_request_id', '');
            if (!Str::isUuid($requestId)) {
                $requestId = (string) Str::uuid();
            }

            DB::table('mcp_audit_logs')->insert([
                'request_id' => $requestId,
                'trace_id' => (string) ($request->header('X-Trace-Id') ?: ''),
                'environment' => (string) app()->environment(),
                'user_id' => $user?->user_id,
                'role' => $user ? match ((int) $user->role_id) { 1 => 'admin', 2 => 'team_admin', 3 => 'sales', default => 'unknown' } : null,
                'token_id' => $token?->id,
                'tool_name' => $request->header('X-MCP-Tool-Name'),
                'endpoint' => '/' . ltrim($request->path(), '/'),
                'arguments' => json_encode($this->safeArguments($request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'scope' => json_encode($this->safeScope($request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'decision' => $status >= 400 ? 'denied' : 'allowed',
                'http_status' => $status,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'items_returned' => $this->itemsReturned($response),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Auditing must never turn a read-only API request into a 500.
        }
    }

    private function safeArguments(Request $request): array
    {
        $input = $request->except(['token', 'password', 'password_confirmation', 'authorization', 'api_key']);
        unset($input['X-Prime-MCP-Key']);
        return $input;
    }

    private function safeScope(Request $request): array
    {
        return array_filter([
            'team_id' => $request->route('teamId'),
            'sales_id' => $request->route('salesId'),
        ], static fn ($value) => $value !== null);
    }

    private function itemsReturned(?Response $response): ?int
    {
        if (!$response || !str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return null;
        }
        $payload = json_decode((string) $response->getContent(), true);
        return isset($payload['data']) && is_array($payload['data']) ? count($payload['data']) : null;
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
