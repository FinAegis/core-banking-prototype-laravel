<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Asset;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Services\AssetTransferService;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class InitiateAssetTransferMutation
{
    public function __construct(
        private readonly AssetTransferService $assetTransferService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): string
    {
        if (! config('ledger.direct_funding_enabled')) {
            throw new Error('This operation is not available.');
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // The caller must own the source account (same rule as REST transfers).
        if (Account::findOwnedBy((string) $args['from_account_uuid'], $user) === null) {
            throw new AuthorizationException('You are not authorized to use this account.');
        }

        return $this->assetTransferService->transfer(
            $args['from_account_uuid'],
            $args['to_account_uuid'],
            $args['asset_code'],
            (string) $args['amount'],
        );
    }
}
