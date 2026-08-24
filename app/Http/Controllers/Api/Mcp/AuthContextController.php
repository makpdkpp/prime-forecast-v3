<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Services\McpPrincipalResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AuthContextController extends Controller
{
    public function show(McpPrincipalResolver $resolver): JsonResponse
    {
        try {
            $principal = $resolver->resolve();
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        }

        return response()->json(['data' => $principal->toArray()]);
    }
}
