<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\StaffRoles;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `is_admin` route middleware: platform administrators only
 * ({@see StaffRoles::ADMINS}). 401 without a signed-in user, 403 for any
 * other user.
 */
class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! StaffRoles::userHasAny($user, StaffRoles::ADMINS)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
                'error'   => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
