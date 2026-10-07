<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Microfinance;

use App\Domain\Microfinance\Models\ShareAccount;
use App\Domain\Microfinance\Services\ShareAccountService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class PurchaseSharesMutation
{
    public function __construct(
        private readonly ShareAccountService $shareAccountService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): ShareAccount
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // The share account's holder or an operator. Unknown ids are left
        // to the service, which reports them as not found.
        /** @var ShareAccount|null $account */
        $account = ShareAccount::query()->find((string) $args['account_id']);

        $othersAccount = $account !== null && (string) $account->user_id !== (string) $user->id;

        if ($othersAccount && ! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to use this share account.');
        }

        return $this->shareAccountService->purchaseShares(
            accountId: (string) $args['account_id'],
            shares: (int) $args['shares'],
        );
    }
}
