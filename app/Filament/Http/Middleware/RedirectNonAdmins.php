<?php

declare(strict_types=1);

namespace App\Filament\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Drop-in replacement for Filament's bundled Authenticate middleware that
 * redirects authenticated users without admin access to /dashboard instead
 * of returning a bare 403. Unauthenticated users follow the standard
 * redirect-to-login flow inherited from the framework.
 */
class RedirectNonAdmins extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();
        $panel = Filament::getCurrentPanel();

        if ($panel === null) {
            return;
        }

        $canAccess = $user instanceof FilamentUser
            ? $user->canAccessPanel($panel)
            : config('app.env') === 'local';

        if (! $canAccess) {
            // Bail out via a thrown HttpResponseException so the redirect
            // takes effect immediately and the rest of the middleware
            // pipeline doesn't run on a half-authorised request.
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->buildRedirect($request)
            );
        }
    }

    private function buildRedirect(Request $request): RedirectResponse
    {
        $message = 'Your account does not have admin access. You\'ve been redirected to your dashboard.';

        return redirect()
            ->route('dashboard')
            ->with('error', $message);
    }
}
