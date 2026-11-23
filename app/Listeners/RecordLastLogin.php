<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Keeps users.last_login_at for every session sign-in (web, Fortify, Privy
 * web). Token sign-ins record it where the token is issued.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->recordLogin();
        }
    }
}
