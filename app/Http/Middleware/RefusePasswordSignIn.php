<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\Services\PasswordSignInGate;
use App\Support\ErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses password sign-in and password reset while
 * auth.password_login_enabled (AUTH_PASSWORD_LOGIN_ENABLED) is off.
 *
 * It runs in the web group and acts only on the Fortify routes named in
 * FORTIFY_ROUTES. The API routes attach it by alias with the kind of path:
 * `password.signin:login` or `password.signin:reset`. The admin panel login
 * form is handled by App\Filament\Auth\AdminLogin.
 *
 * A login may go ahead for an active reviewer account (PasswordSignInGate).
 * JSON requests get 403 ERR_AUTH_008; web requests go back to the login
 * page, which offers the email code.
 */
final class RefusePasswordSignIn
{
    public const KIND_LOGIN = 'login';

    public const KIND_TWO_FACTOR = 'two-factor';

    public const KIND_RESET = 'reset';

    /**
     * Fortify route name => kind.
     *
     * @var array<string, string>
     */
    private const FORTIFY_ROUTES = [
        'login.store'            => self::KIND_LOGIN,
        'two-factor.login'       => self::KIND_TWO_FACTOR,
        'two-factor.login.store' => self::KIND_TWO_FACTOR,
        'password.request'       => self::KIND_RESET,
        'password.email'         => self::KIND_RESET,
        'password.reset'         => self::KIND_RESET,
        'password.update'        => self::KIND_RESET,
    ];

    public function __construct(
        private readonly PasswordSignInGate $gate,
    ) {
    }

    public function handle(Request $request, Closure $next, ?string $kind = null): Response
    {
        if ($this->gate->enabled()) {
            return $next($request);
        }

        $kind ??= $this->fortifyKind($request);

        if ($kind === null || $this->allowed($request, $kind)) {
            return $next($request);
        }

        return $this->refuse($request, $kind);
    }

    private function fortifyKind(Request $request): ?string
    {
        $name = $request->route()?->getName();

        return is_string($name) ? (self::FORTIFY_ROUTES[$name] ?? null) : null;
    }

    private function allowed(Request $request, string $kind): bool
    {
        return match ($kind) {
            self::KIND_LOGIN      => $this->gate->allowsEmail($request->input('email')),
            self::KIND_TWO_FACTOR => $this->gate->allowsUser($this->pendingTwoFactorUserId($request)),
            default               => false,
        };
    }

    /**
     * The user who passed the password step and now owes the second factor
     * (Fortify keeps it in the session as login.id).
     */
    private function pendingTwoFactorUserId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get('login.id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function refuse(Request $request, string $kind): Response
    {
        $reason = $kind === self::KIND_RESET ? 'PASSWORD_RESET_DISABLED' : 'PASSWORD_LOGIN_DISABLED';
        $message = __($kind === self::KIND_RESET
            ? PasswordSignInGate::RESET_DISABLED_MESSAGE
            : PasswordSignInGate::LOGIN_DISABLED_MESSAGE);

        if ($request->expectsJson() || $request->is('api/*')) {
            return ErrorResponse::make('ERR_AUTH_008', $message, ['reason' => $reason]);
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message])
            ->withInput($request->only('email'))
            ->with('error_code', 'ERR_AUTH_008');
    }
}
