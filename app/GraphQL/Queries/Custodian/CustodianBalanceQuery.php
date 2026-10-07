<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Custodian;

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Models\CustodianAccount;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class CustodianBalanceQuery
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): float
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var CustodianAccount $account */
        $account = CustodianAccount::findOrFail($args['account_id']);

        // Only links to an internal account the caller owns.
        if (Account::findOwnedBy((string) $account->account_uuid, $user) === null) {
            throw new AuthorizationException('You are not authorized to view this account.');
        }

        return (float) ($account->last_known_balance ?? 0.0);
    }
}
