<?php

namespace App\Services;

use App\Support\McpPrincipal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class McpPrincipalResolver
{
    public function resolve(): McpPrincipal
    {
        $user = request()->user();

        if (!$user) {
            throw new AuthorizationException('MCP authentication is required.');
        }

        if (!$user->is_active) {
            throw new AuthorizationException('User account is inactive.');
        }

        $token = $user->currentAccessToken();
        if (!$token || !$token->can('mcp:read')) {
            throw new AuthorizationException('The token does not have the mcp:read ability.');
        }

        $role = match ((int) $user->role_id) {
            1 => 'admin',
            2 => 'team_admin',
            3 => 'sales',
            default => null,
        };

        if ($role === null) {
            throw new AuthorizationException('The user role is not allowed to access MCP.');
        }

        $teamIds = DB::table('transactional_team')
            ->where('user_id', $user->user_id)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return new McpPrincipal(
            (int) $user->user_id,
            $role,
            $teamIds,
            $token->id ? (int) $token->id : null,
        );
    }
}
