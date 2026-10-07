<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\CardIssuance;

use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use RuntimeException;

class CancelCardMutation
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

        $cardToken = (string) $args['id'];

        // The card's owner or an operator.
        $mayManage = StaffRoles::userHasAny($user, StaffRoles::OPERATORS)
            || $this->cardProvisioningService->isOwnedBy($cardToken, (string) $user->id);

        if (! $mayManage) {
            throw new AuthorizationException('You are not authorized to manage this card.');
        }

        $this->cardProvisioningService->cancelCard($cardToken, 'Cancelled by user');

        $card = $this->cardProvisioningService->getCard($cardToken);

        if ($card === null) {
            throw new RuntimeException('Card not found after cancel operation.');
        }

        return [
            'id'                   => $card->cardToken,
            'card_token'           => $card->cardToken,
            'cardholder_name'      => $card->cardholderName,
            'last_four'            => $card->last4,
            'network'              => $card->network->value,
            'status'               => $card->status->value,
            'currency'             => null,
            'label'                => $card->label,
            'issuer'               => null,
            'funding_source'       => null,
            'spend_limit_interval' => null,
            'expires_at'           => $card->expiresAt->format('Y-m-d'),
            'created_at'           => now()->toDateTimeString(),
        ];
    }
}
