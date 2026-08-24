<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMcpServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: (string) str()->uuid());
        $request->attributes->set('mcp_request_id', $requestId);

        if (!(bool) config('services.prime_mcp.enabled')) {
            return $this->error('mcp_disabled', 'MCP Gateway is disabled.', 503, $requestId);
        }

        $expected = (string) config('services.prime_mcp.service_key');
        $provided = (string) $request->header('X-Prime-MCP-Key');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return $this->error('invalid_service_key', 'Invalid MCP service key.', 401, $requestId);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        return $response;
    }

    private function error(string $code, string $message, int $status, string $requestId): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $status, ['X-Request-Id' => $requestId]);
    }
}
