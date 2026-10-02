<?php

namespace App\Services;

use App\Models\User;

class McpSalesCreateAccess
{
    public const ABILITY = 'mcp:sales:create';

    public const PERMISSION = 'sales.self.create';

    public function enabled(): bool
    {
        return app()->environment('staging', 'testing')
            && (bool) config('services.prime_mcp.enabled')
            && (bool) config('services.prime_mcp.sales_create_enabled')
            && (bool) config('services.prime_mcp.audit_enabled');
    }

    public function eligible(?User $user): bool
    {
        return $this->enabled() && $user && $user->is_active && ! $user->deleted_at
            && in_array((int) $user->role_id, [2, 3], true)
            && in_array((int) $user->user_id, config('services.prime_mcp.sales_create_user_ids', []), true);
    }

    public function tokenAllowed($token): bool
    {
        return $token && $token->expires_at && $token->expires_at->isFuture()
            && in_array('mcp:read', $token->abilities ?? [], true)
            && in_array(self::ABILITY, $token->abilities ?? [], true);
    }
}
