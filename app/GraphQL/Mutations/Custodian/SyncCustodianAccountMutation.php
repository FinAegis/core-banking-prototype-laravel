<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Custodian;

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\CustodianAccountService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class SyncCustodianAccountMutation
{
    public function __construct(
        private readonly CustodianAccountService $custodianAccountService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): CustodianAccount
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var CustodianAccount $account */
        $account = CustodianAccount::findOrFail($args['id']);

        // The owner of the linked internal account, or an operator.
        if (
            ! LinkCustodianAccountMutation::isOperator($user)
            && Account::findOwnedBy((string) $account->account_uuid, $user) === null
        ) {
            throw new AuthorizationException('You are not authorized to manage this custodian account.');
        }

        $this->custodianAccountService->syncAccountStatus($account);

        /** @var CustodianAccount */
        return $account->fresh();
    }
}
