<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Interledger;

use App\Domain\Interledger\Services\OpenPaymentsService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class IlpCreateIncomingPaymentMutation
{
    public function __construct(
        private readonly OpenPaymentsService $openPaymentsService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): string
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // The inputs name only external wallet addresses, with no link to a
        // local account the caller could own, so only operators use them.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to use Interledger payments.');
        }

        $result = $this->openPaymentsService->createIncomingPayment(
            walletAddress: (string) $args['wallet_address'],
            amount: (string) $args['amount'],
            assetCode: strtoupper((string) $args['asset_code']),
        );

        return $result['incoming_payment_id'];
    }
}
