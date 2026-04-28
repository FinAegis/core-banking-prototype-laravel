<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

final class ConsentScreenController
{
    public function __invoke(Request $request): View
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
            'state'               => $request->query('state'),
            'auth_token'          => $request->session()->token(),
        ]);
    }
}
