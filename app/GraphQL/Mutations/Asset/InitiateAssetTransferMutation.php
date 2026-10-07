<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Asset;

use App\Domain\Asset\Services\AssetTransferService;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

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

        return $this->assetTransferService->transfer(
            $args['from_account_uuid'],
            $args['to_account_uuid'],
            $args['asset_code'],
            (string) $args['amount'],
        );
    }
}
