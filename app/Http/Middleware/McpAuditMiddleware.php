<?php

namespace App\Http\Middleware;

use App\Services\McpAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpAuditMiddleware
{
    public function __construct(private McpAuditLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = null;
        $exception = null;

        try {
            $response = $next($request);
            return $response;
        } catch (\Throwable $throwable) {
            $exception = $throwable;
            throw $throwable;
        } finally {
            if ((bool) config('services.prime_mcp.audit_enabled')) {
                $this->logger->record($request, $response, $startedAt, $exception);
            }
        }
    }
}
