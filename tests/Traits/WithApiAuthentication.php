<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait WithApiAuthentication
{
    /**
     * Act as a user with specific API scopes.
     *
     * @param User $user
     * @param array $scopes
     * @return void
     */
    protected function actingAsWithScopes(User $user, array $scopes = ['read', 'write']): void
    {
        Sanctum::actingAs($user, $scopes);
    }

    /**
     * Act as a user with read-only access.
     *
     * @param User $user
     * @return void
     */
    protected function actingAsReadOnly(User $user): void
    {
        Sanctum::actingAs($user, ['read']);
    }

    /**
     * Act as a user with full access (read, write, delete).
     *
     * @param User $user
     * @return void
     */
    protected function actingAsFullAccess(User $user): void
    {
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
    }

    /**
     * Act as an admin user with all scopes.
     *
     * @param User $user
     * @return void
     */
    protected function actingAsAdmin(User $user): void
    {
        Sanctum::actingAs($user, ['read', 'write', 'delete', 'admin']);
    }
}
