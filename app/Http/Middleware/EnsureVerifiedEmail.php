<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ErrorResponse;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a signed-in account whose email address is not verified on every
 * route that requires a signed-in user, while auth.verified_email_required
 * (AUTH_REQUIRE_VERIFIED_EMAIL) is on.
 *
 * It runs in the web and api route groups and on the GraphQL route; the
 * API key middlewares (auth.apikey, auth.api_or_sanctum) apply the same
 * rule through refuses(). A route
 * counts as signed-in when it carries an authenticating middleware (`auth`,
 * `auth:<guard>`) or Lighthouse's AttemptAuthentication; the user is read
 * from that middleware's guards, so the order of the middleware does not
 * matter. Guests pass, so the authenticating middleware still answers 401.
 *
 * Laravel's `verified` middleware does nothing here: User does not
 * implement MustVerifyEmail. This middleware is the effective check.
 *
 * An unverified account can still verify its address, sign out and delete
 * itself. JSON requests get 403 ERR_REG_003 (reason EMAIL_NOT_VERIFIED); web
 * pages redirect to the verification notice.
 */
final class EnsureVerifiedEmail
{
    /**
     * Paths an unverified account may still use.
     *
     * @var list<string>
     */
    private const ALLOWED_PATHS = [
        'logout',
        'email/verify',
        'email/verify/*',
        'email/verification-notification',
        'api/auth/logout',
        'api/auth/logout-all',
        'api/auth/resend-verification',
        'api/auth/verify-email/*',
        'api/auth/delete-account',
        'api/v2/auth/logout',
        'api/v2/auth/logout-all',
    ];

    public function __construct(
        private readonly Router $router,
        private readonly AuthFactory $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('auth.verified_email_required', true)) {
            return $next($request);
        }

        $route = $request->route();
        if (! $route instanceof Route || $request->is(...self::ALLOWED_PATHS)) {
            return $next($request);
        }

        $guards = $this->authenticatingGuards($route);
        if ($guards === null) {
            return $next($request);
        }

        if (! self::refuses($this->resolveUser($guards))) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*', 'graphql')) {
            return self::refusal();
        }

        return redirect()->guest(route('verification.notice'));
    }

    /**
     * Whether the switch is on and this signed-in user has not verified the
     * email address. Also used by the API key middlewares, which resolve
     * their user themselves.
     */
    public static function refuses(mixed $user): bool
    {
        return (bool) config('auth.verified_email_required', true)
            && $user instanceof User
            && ! $user->hasVerifiedEmail();
    }

    /**
     * The JSON answer for a refused account: 403 ERR_REG_003.
     */
    public static function refusal(): JsonResponse
    {
        return ErrorResponse::make('ERR_REG_003', null, ['reason' => 'EMAIL_NOT_VERIFIED']);
    }

    /**
     * The guards of the route's authenticating middleware, or null when the
     * route does not authenticate. A null guard means the default guard.
     *
     * @return list<string|null>|null
     */
    private function authenticatingGuards(Route $route): ?array
    {
        $guards = null;

        foreach ($this->router->gatherRouteMiddleware($route) as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$class, $parameters] = array_pad(explode(':', $middleware, 2), 2, '');

            if ($class === AttemptAuthentication::class) {
                // The schema's @guard directives use the sanctum guard, which
                // AttemptAuthentication does not try while lighthouse.guards
                // is null, so check it as well.
                $configured = config('lighthouse.guards');
                $guards = array_merge(
                    $guards ?? [],
                    is_array($configured) && $configured !== [] ? array_values(array_map('strval', $configured)) : [null],
                    ['sanctum'],
                );

                continue;
            }

            if (class_exists($class) && is_a($class, AuthenticatesRequests::class, true)) {
                $named = $parameters === '' ? [null] : explode(',', $parameters);
                $guards = array_merge($guards ?? [], $named);
            }
        }

        return $guards;
    }

    /**
     * @param list<string|null> $guards
     */
    private function resolveUser(array $guards): ?object
    {
        foreach ($guards as $guard) {
            $user = $this->auth->guard($guard)->user();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }
}
