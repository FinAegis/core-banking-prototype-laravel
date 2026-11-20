<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\CardIssuance;

use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Http\Middleware\TransactionRateLimitMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\RateLimitException;

class ProvisionCardMutation
{
    public function __construct(
        private readonly CardProvisioningService $cardProvisioningService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // One per-user counter with REST POST /api/v1/cards and
        // /api/v1/cards/provision (transaction.rate_limit:card_provision).
        if (! TransactionRateLimitMiddleware::consume((int) $user->id, 'card_provision')) {
            throw new RateLimitException('Mutation.provisionCard');
        }

        $card = $this->cardProvisioningService->createCard(
            userId: (string) $user->id,
            cardholderName: $args['cardholder_name'],
        );

        return [
            'id'              => $card->cardToken,
            'card_token'      => $card->cardToken,
            'cardholder_name' => $card->cardholderName,
            'last_four'       => $card->last4,
            'network'         => $card->network->value,
            'status'          => $card->status->value,
            'expires_at'      => $card->expiresAt->format('Y-m-d'),
            'created_at'      => now()->toDateTimeString(),
        ];
    }
}
