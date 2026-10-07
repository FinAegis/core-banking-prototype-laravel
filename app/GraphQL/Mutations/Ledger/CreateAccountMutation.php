<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Ledger;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Services\ChartOfAccountsService;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class CreateAccountMutation
{
    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {
    }

    /**
     * Create a new ledger account.
     *
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): LedgerAccount
    {
        // The general ledger is kept by platform operators.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage the general ledger.');
        }

        return $this->chartOfAccountsService->createAccount(
            code: (string) $args['code'],
            name: (string) $args['name'],
            type: AccountType::from((string) $args['type']),
            parentCode: isset($args['parent_code']) ? (string) $args['parent_code'] : null,
            currency: isset($args['currency']) ? (string) $args['currency'] : 'USD',
            description: isset($args['description']) ? (string) $args['description'] : null,
        );
    }
}
