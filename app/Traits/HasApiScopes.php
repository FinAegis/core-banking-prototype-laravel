<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Carbon\Carbon;

trait HasApiScopes
{
    /**
     * Get default scopes for a user based on their role.
     *
     * @param  User  $user
     * @return array<string>
     */
    protected function getDefaultScopesForUser(User $user): array
    {
        // Admin users get all scopes
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return ['read', 'write', 'delete', 'admin'];
        }

        // Business users get read and write
        if ($user->hasRole('customer_business') || $user->hasRole('business')) {
            return ['read', 'write'];
        }

        // Regular users get read and write (but not delete or admin)
        return ['read', 'write'];
    }

    /**
     * Get scopes from request or use defaults.
     *
     * @param  array|null  $requestedScopes
     * @param  User  $user
     * @return array<string>
     */
    protected function resolveScopes(?array $requestedScopes, User $user): array
    {
        if (empty($requestedScopes)) {
            return $this->getDefaultScopesForUser($user);
        }

        $defaultScopes = $this->getDefaultScopesForUser($user);

        // Only allow scopes that the user is entitled to
        return array_intersect($requestedScopes, $defaultScopes);
    }

    /**
     * Create a token with appropriate scopes and expiration.
     *
     * @param  User  $user
     * @param  string  $tokenName
     * @param  array|null  $requestedScopes
     * @return string
     */
    protected function createTokenWithScopes(User $user, string $tokenName, ?array $requestedScopes = null): string
    {
        $scopes = $this->resolveScopes($requestedScopes, $user);

        // Get expiration from config (in minutes)
        $expirationMinutes = config('sanctum.expiration');

        // Create the token
        $token = $user->createToken($tokenName, $scopes);

        // Set expiration if configured
        if ($expirationMinutes) {
            $token->accessToken->expires_at = Carbon::now()->addMinutes((int) $expirationMinutes);
            $token->accessToken->save();
        }

        return $token->plainTextToken;
    }
}
