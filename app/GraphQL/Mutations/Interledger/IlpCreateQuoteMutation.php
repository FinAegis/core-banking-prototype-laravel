<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Interledger;

use App\Domain\Interledger\Services\QuoteService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class IlpCreateQuoteMutation
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{send_asset: string, send_amount: string, receive_asset: string, receive_amount: string, exchange_rate: float, fee: string, expires_at: string}
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Same rule as the Interledger payment mutations: no local account
        // is involved that the caller could own, so only operators use them.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to use Interledger payments.');
        }

        return $this->quoteService->getQuote(
            sendAsset: strtoupper((string) $args['send_asset']),
            receiveAsset: strtoupper((string) $args['receive_asset']),
            sendAmount: (string) $args['send_amount'],
        );
    }
}
