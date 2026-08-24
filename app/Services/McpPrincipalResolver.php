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

        if (! $user) {
            throw new AuthorizationException('MCP authentication is required.');
        }

        if (! $user->is_active) {
            throw new AuthorizationException('User account is inactive.');
        }

        $token = $user->currentAccessToken();
        if (! $token || ! $token->can('mcp:read')) {
            throw new AuthorizationException('The token does not have the mcp:read ability.');
        }

        if (! $token->expires_at || $token->expires_at->isPast()) {
            throw new AuthorizationException('The MCP token is expired or has no expiry.');
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

        $permissions = match ($role) {
            'sales' => ['forecast.self.read'],
            'team_admin' => ['forecast.self.read', 'forecast.team.read'],
            'admin' => ['forecast.self.read', 'forecast.team.read', 'forecast.company.read'],
        };

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
            $permissions,
            $token->id ? (int) $token->id : null,
            $token->expires_at->toIso8601String(),
        );
    }
}
