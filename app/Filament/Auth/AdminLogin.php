<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Domain\Auth\Services\PasswordSignInGate;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login;
use Illuminate\Validation\ValidationException;

/**
 * The admin panel login page. While password sign-in is on
 * (AUTH_PASSWORD_LOGIN_ENABLED), it is Filament's own email and password
 * form. While it is off, the page sends the visitor to the email-code login
 * on /login and remembers the panel as the page to return to. The panel
 * uses the same web session, so an admin who signs in with the email code
 * opens /admin. A password posted to the form directly is refused.
 *
 * It lives outside app/Filament/Admin/Pages so the panel's page discovery
 * does not register it as a second page.
 */
class AdminLogin extends Login
{
    public function mount(): void
    {
        if (! app(PasswordSignInGate::class)->enabled() && ! Filament::auth()->check()) {
            if (! session()->has('url.intended')) {
                session()->put('url.intended', Filament::getUrl());
            }

            $this->redirect(route('login'));

            return;
        }

        parent::mount();
    }

    public function authenticate(): ?LoginResponse
    {
        if (! app(PasswordSignInGate::class)->enabled()) {
            throw ValidationException::withMessages([
                'data.email' => __(PasswordSignInGate::LOGIN_DISABLED_MESSAGE),
            ]);
        }

        return parent::authenticate();
    }
}
