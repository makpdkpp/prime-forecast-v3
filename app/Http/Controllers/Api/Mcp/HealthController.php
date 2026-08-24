<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'prime-forecast-mcp-gateway',
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
