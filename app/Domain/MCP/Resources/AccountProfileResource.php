<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\Account\Models\Account;
use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Models\User;

/**
 * `account://profile` — primary account metadata for the authenticated user.
 *
 * "Primary" is currently defined as the first account belonging to the user
 * (ordered by created_at). Adjust here if the User model later grows a
 * `primary_account_uuid` field.
 */
final class AccountProfileResource implements McpResourceInterface
{
    public function uriTemplate(): string
    {
        return 'account://profile';
    }

    public function name(): string
    {
        return 'Primary account profile';
    }

    public function description(): string
    {
        return 'The authenticated user primary account metadata.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function scope(): string
    {
        return 'accounts:read';
    }

    /**
     * @param array<string, string> $params
     */
    public function read(array $params, ?int $userId): string
    {
        if ($userId === null) {
            return $this->json(['error' => 'no user context']);
        }

        $user = User::find($userId);
        if (! $user instanceof User) {
            return $this->json(['error' => 'user not found']);
        }

        $account = Account::where('user_uuid', $user->uuid)
            ->orderBy('created_at')
            ->first();

        if (! $account instanceof Account) {
            return $this->json(['error' => 'no primary account']);
        }

        return $this->json([
            'account_id' => $account->uuid,
            'user_uuid'  => $user->uuid,
            'name'       => $account->name,
            'created_at' => $account->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
