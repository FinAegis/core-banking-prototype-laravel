<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

final class ConsentScreenController
{
    /**
     * @param  array<string, mixed>  $passportParameters  The parameter bag Passport hands to its
     *                                                    authorization view callback. We need
     *                                                    `authToken` from here — Passport stores a
     *                                                    `Str::random()` value under session key
     *                                                    `authToken` and the approve/deny POST
     *                                                    handlers compare the form's `auth_token`
     *                                                    field against it (see
     *                                                    `Laravel\Passport\Http\Controllers\RetrievesAuthRequestFromSession`).
     *                                                    This is NOT the Laravel CSRF token —
     *                                                    `@csrf` already handles that separately.
     *
     * NOTE on `mcp_daily_limit_minor`:
     * The consent form posts a `mcp_daily_limit_minor` field that captures the user's chosen
     * 24-hour spending cap at consent time. There is currently no server-side enforcement of
     * this value — that's intentionally deferred to a later task. The next implementer should
     * read it during access-token issuance (Passport `OAuthEvent::AccessTokenIssued` or a
     * `passport:before-saving-token` hook) and persist it on the issued token, then enforce it
     * in the MCP tool dispatcher. Search for `mcp_daily_limit_minor` to find this hook point.
     */
    public function __invoke(Request $request, array $passportParameters = []): View
    {
        $clientId = (string) $request->query('client_id');
        $scopeStr = (string) $request->query('scope', '');

        /** @var ClientRepository $repo */
        $repo = app(ClientRepository::class);
        $client = $repo->find($clientId);
        if ($client === null) {
            abort(404, 'Unknown client');
        }

        $requestedScopes = array_values(array_filter(explode(' ', $scopeStr)));
        /** @var array<string, string> $scopeCatalog */
        $scopeCatalog = (array) config('mcp.scopes');

        $scopeRows = [];
        foreach ($requestedScopes as $s) {
            $scopeRows[] = [
                'id'          => $s,
                'description' => $scopeCatalog[$s] ?? $s,
                'is_write'    => str_ends_with($s, ':write') || $s === 'sms:send',
            ];
        }

        return view('mcp.consent', [
            'client'              => $client,
            'scopes'              => $scopeRows,
            'authorize_url'       => route('passport.authorizations.approve'),
            'deny_url'            => route('passport.authorizations.deny'),
            'spending_options'    => config('mcp.spending.consent_options_minor'),
            'default_limit_minor' => config('mcp.spending.default_daily_limit_minor'),
            'currency'            => config('mcp.spending.default_daily_limit_currency'),
            'state'               => is_string($request->query('state')) ? $request->query('state') : '',
            'auth_token'          => is_string($passportParameters['authToken'] ?? null)
                ? (string) $passportParameters['authToken']
                : '',
        ]);
    }
}
