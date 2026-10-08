<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Interledger;

use App\Domain\Interledger\Services\OpenPaymentsService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class IlpCreateOutgoingPaymentMutation
{
    public function __construct(
        private readonly OpenPaymentsService $openPaymentsService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{payment_id: string, status: string, checked_at: string}
     */
    public function __invoke(mixed $rootValue, array $args): array
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

        $result = $this->openPaymentsService->createOutgoingPayment(
            walletAddress: (string) $args['wallet_address'],
            quoteId: (string) $args['quote_id'],
            grantToken: (string) $args['grant_token'],
        );

        return [
            'payment_id' => $result['outgoing_payment_id'],
            'status'     => $result['status'],
            'checked_at' => $result['created_at'],
        ];
    }
}
