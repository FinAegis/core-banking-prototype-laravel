<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Custodian;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianTransfer;
use App\Domain\Custodian\Services\CustodianAccountService;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class InitiateCustodianTransferMutation
{
    public function __construct(
        private readonly CustodianAccountService $custodianAccountService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): CustodianTransfer
    {
        if (! config('ledger.direct_funding_enabled')) {
            throw new Error('This operation is not available.');
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // The caller must own the source account (same rule as the REST
        // custodian transfer and GraphQL initiatePayment).
        if (Account::findOwnedBy((string) $args['from_account_uuid'], $user) === null) {
            throw new AuthorizationException('You are not authorized to use this account.');
        }

        // Only an active custodian link counts (same rule as the REST
        // custodian transfer), and the destination link must be on the same
        // custodian as the source link.
        /** @var CustodianAccount|null $fromAccount */
        $fromAccount = CustodianAccount::query()
            ->active()
            ->where('account_uuid', $args['from_account_uuid'])
            ->orderByDesc('is_primary')
            ->first();

        if ($fromAccount === null) {
            throw new AuthorizationException('Custodian account is not linked to this account.');
        }

        /** @var CustodianAccount|null $toAccount */
        $toAccount = CustodianAccount::query()
            ->active()
            ->where('account_uuid', $args['to_account_uuid'])
            ->where('custodian_name', $fromAccount->custodian_name)
            ->orderByDesc('is_primary')
            ->first();

        if ($toAccount === null) {
            throw new Error('The destination account has no active link with this custodian.');
        }

        $transferId = $this->custodianAccountService->initiateTransfer(
            fromAccount: $fromAccount,
            toAccount: $toAccount,
            amount: new Money((int) ($args['amount'] * 100)),
            assetCode: $args['asset_code'],
            reference: $args['reference'] ?? '',
        );

        /** @var CustodianTransfer */
        return CustodianTransfer::findOrFail($transferId);
    }
}
