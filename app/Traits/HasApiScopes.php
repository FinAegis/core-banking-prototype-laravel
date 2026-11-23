<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use App\Support\StaffRoles;
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
        if (StaffRoles::userHasAny($user, StaffRoles::ADMINS)) {
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
     * @param  array<string>|null  $requestedScopes
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
     * @param  array<string>|null  $requestedScopes
     * @return string
     */
    protected function createTokenWithScopes(User $user, string $tokenName, ?array $requestedScopes = null): string
    {
        $scopes = $this->resolveScopes($requestedScopes, $user);

        // Get expiration from config (in minutes)
        $expirationMinutes = config('sanctum.expiration');

        // Every token sign-in (login, sign-up, social, 2FA, refresh) counts as activity.
        $user->recordLogin();

        // Create the token
        $token = $user->createToken($tokenName, $scopes);

        // Set expiration if configured
        if ($expirationMinutes) {
            $token->accessToken->expires_at = Carbon::now()->addMinutes((int) $expirationMinutes);
            $token->accessToken->save();
        }

        return $token->plainTextToken;
    }

    /**
     * Create a refresh token for the given user.
     *
     * @param  User  $user
     * @param  string  $tokenName
     * @return string
     */
    protected function createRefreshToken(User $user, string $tokenName): string
    {
        $token = $user->createToken($tokenName . '-refresh', ['refresh']);

        $expirationMinutes = config('sanctum.refresh_token_expiration');
        if ($expirationMinutes) {
            $token->accessToken->expires_at = Carbon::now()->addMinutes((int) $expirationMinutes);
            $token->accessToken->save();
        }

        return $token->plainTextToken;
    }

    /**
     * Create an access/refresh token pair.
     *
     * @param  User  $user
     * @param  string  $tokenName
     * @param  array<string>|null  $requestedScopes
     * @return array{access_token: string, refresh_token: string, expires_in: int|null, refresh_expires_in: int|null}
     */
    protected function createTokenPair(User $user, string $tokenName, ?array $requestedScopes = null): array
    {
        $accessToken = $this->createTokenWithScopes($user, $tokenName, $requestedScopes);
        $refreshToken = $this->createRefreshToken($user, $tokenName);

        $expirationMinutes = config('sanctum.expiration');
        $refreshExpirationMinutes = config('sanctum.refresh_token_expiration');

        return [
            'access_token'       => $accessToken,
            'refresh_token'      => $refreshToken,
            'expires_in'         => $expirationMinutes ? (int) $expirationMinutes * 60 : null,
            'refresh_expires_in' => $refreshExpirationMinutes ? (int) $refreshExpirationMinutes * 60 : null,
        ];
    }
}
