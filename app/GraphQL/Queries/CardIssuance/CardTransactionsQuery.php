<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\CardIssuance;

use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class CardTransactionsQuery
{
    public function __construct(
        private readonly CardProvisioningService $cardProvisioningService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $cardToken = (string) $args['card_id'];

        // Only the card's owner (or an operator) sees its transactions; any
        // other card reads as having none.
        $mayView = StaffRoles::userHasAny($user, StaffRoles::OPERATORS)
            || $this->cardProvisioningService->isOwnedBy($cardToken, (string) $user->id);

        if (! $mayView) {
            return [];
        }

        $limit = isset($args['first']) ? (int) $args['first'] : 20;

        $result = $this->cardProvisioningService->getTransactions(
            cardToken: $cardToken,
            limit: $limit,
        );

        return array_map(
            fn ($transaction) => [
                'id'                => $transaction->transactionId,
                'card_id'           => $transaction->cardToken,
                'merchant_name'     => $transaction->merchantName,
                'merchant_category' => $transaction->merchantCategory,
                'amount_cents'      => $transaction->amountCents,
                'currency'          => $transaction->currency,
                'status'            => $transaction->status,
                'transacted_at'     => $transaction->timestamp->format('Y-m-d\TH:i:s\Z'),
            ],
            $result['transactions'],
        );
    }
}
