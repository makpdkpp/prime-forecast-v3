<?php

namespace App\Support;

final class McpPrincipal
{
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly array $teamIds,
        public readonly ?int $tokenId,
    ) {
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'role' => $this->role,
            'team_ids' => array_values($this->teamIds),
            'token_id' => $this->tokenId,
        ];
    }
}
