<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            return response()->json([
                'status' => 'not_ready',
                'service' => 'prime-forecast-mcp-gateway',
                'database' => 'unavailable',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }

        if (! Schema::hasTable('mcp_audit_logs')) {
            return response()->json([
                'status' => 'not_ready',
                'service' => 'prime-forecast-mcp-gateway',
                'database' => 'ok',
                'audit_log' => 'unavailable',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'service' => 'prime-forecast-mcp-gateway',
            'database' => 'ok',
            'audit_log' => 'ok',
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
