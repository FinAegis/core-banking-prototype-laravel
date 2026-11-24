<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\MCP\Policy\ConsentSpendingPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Saves the daily spending limit the user picked on the MCP consent screen.
 *
 * Passport's approve handler ignores extra form fields, so this reads the
 * client of the pending authorization from the session before Passport
 * consumes it, and saves the limit only when Passport accepted the approval
 * (it answers with a redirect carrying the code).
 *
 * Passport also skips the consent screen when the user already holds a live
 * token for the app. An approval without a saved limit (one made before
 * limits were saved) would then never get one, so the authorize request asks
 * for consent again until it has a limit. A client that sent prompt=none
 * keeps that choice.
 */
final class CaptureMcpConsentLimit
{
    public function __construct(private readonly ConsentSpendingPolicy $policies)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->routeIs('passport.authorizations.authorize')) {
            $this->askForConsentWithoutLimit($request);

            return $next($request);
        }

        if (! $request->isMethod('POST') || ! $request->routeIs('passport.authorizations.approve')) {
            return $next($request);
        }

        $clientId = $this->pendingClientId($request);
        $userId = $request->user()?->getAuthIdentifier();
        // "No limit" posts an empty value, which Laravel turns into null;
        // only a missing field means "use the default".
        $field = $request->exists('mcp_daily_limit_minor') ? ($request->input('mcp_daily_limit_minor') ?? '') : null;
        $limit = ConsentSpendingPolicy::limitFromConsent($field);

        $response = $next($request);

        if ($clientId !== null && (is_int($userId) || is_string($userId)) && $response->isRedirection()) {
            $this->policies->record($clientId, $userId, $limit);
        }

        return $response;
    }

    private function askForConsentWithoutLimit(Request $request): void
    {
        $userId = $request->user()?->getAuthIdentifier();
        $clientId = $request->query('client_id');
        if ((! is_int($userId) && ! is_string($userId)) || ! is_string($clientId) || $clientId === '') {
            return;
        }

        $prompts = array_values(array_filter(explode(' ', (string) $request->query('prompt', ''))));
        if (in_array('none', $prompts, true) || in_array('consent', $prompts, true)) {
            return;
        }

        if (DB::table('mcp_token_policies')->where('token_id', ConsentSpendingPolicy::key($clientId, $userId))->exists()) {
            return;
        }

        $request->query->set('prompt', implode(' ', [...$prompts, 'consent']));
    }

    /**
     * The client of the authorization Passport is about to approve, read the
     * same way Passport reads it, and only when the form's auth token matches.
     */
    private function pendingClientId(Request $request): ?string
    {
        $session = $request->session();
        $authToken = $session->get('authToken');
        if (! is_string($authToken) || $authToken === '' || $request->input('auth_token') !== $authToken) {
            return null;
        }

        $serialized = $session->get('authRequest');
        if (! is_string($serialized)) {
            return null;
        }

        try {
            $authRequest = unserialize($serialized, ['allowed_classes' => [
                AuthorizationRequest::class,
                \Laravel\Passport\Bridge\Client::class,
                \Laravel\Passport\Bridge\Scope::class,
                \Laravel\Passport\Bridge\User::class,
            ]]);
        } catch (Throwable) {
            return null;
        }

        return $authRequest instanceof AuthorizationRequest ? $authRequest->getClient()->getIdentifier() : null;
    }
}
