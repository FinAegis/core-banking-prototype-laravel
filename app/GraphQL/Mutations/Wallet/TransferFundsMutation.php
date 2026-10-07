<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Wallet;

use App\Domain\Wallet\Models\MultiSigWallet;
use App\Domain\Wallet\Services\WalletService;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

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
