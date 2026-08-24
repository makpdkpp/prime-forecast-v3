<?php

namespace App\Support;

final class McpPrincipal
{
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly array $teamIds,
        public readonly array $permissions,
        public readonly ?int $tokenId,
        public readonly string $tokenExpiresAt,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'role' => $this->role,
            'team_ids' => array_values($this->teamIds),
            'permissions' => array_values($this->permissions),
            'token_id' => $this->tokenId,
            'token_expires_at' => $this->tokenExpiresAt,
        ];
    }
}
