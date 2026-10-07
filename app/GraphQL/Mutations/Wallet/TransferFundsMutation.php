<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Wallet;

use App\Domain\Account\Models\Account;
use App\Domain\Wallet\Models\MultiSigWallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class TransferFundsMutation
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): MultiSigWallet
    {
        if (! config('ledger.direct_funding_enabled')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var MultiSigWallet|null $wallet */
        $wallet = MultiSigWallet::query()->find($args['from_wallet_id']);

        if (! $wallet) {
            throw new ModelNotFoundException('Wallet not found.');
        }

        // Only the wallet owner or one of its active signers may move funds
        // from it.
        $userId = (int) $user->getAuthIdentifier();
        if (! $user instanceof User || ((int) $wallet->user_id !== $userId && ! $wallet->isUserSigner($userId))) {
            throw new AuthorizationException('You are not authorized to use this wallet.');
        }

        // A source account, when one is given, must belong to the caller.
        if (isset($args['from_account_uuid']) && Account::findOwnedBy((string) $args['from_account_uuid'], $user) === null) {
            throw new AuthorizationException('You are not authorized to use this account.');
        }

        $this->walletService->transfer(
            fromAccountUuid: $args['from_account_uuid'] ?? $wallet->id,
            toAccountUuid: $args['to_account_uuid'] ?? $args['to_address'],
            assetCode: $args['chain'],
            amount: $args['amount'],
            reference: $args['reference'] ?? null,
        );

        return $wallet->fresh() ?? $wallet;
    }
}
