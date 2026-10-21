<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Custodian;

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Connectors\DemoBankConnector;
use App\Domain\Custodian\Connectors\MockBankConnector;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\CustodianAccountService;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

/**
 * Links an internal account to a custodian account.
 *
 * An active link is what the REST and GraphQL custodian transfer endpoints
 * accept as the right to move funds between the two accounts, and the
 * connectors only check that the custodian account exists, not who holds it.
 * Links are therefore created by an operator (StaffRoles::OPERATORS). An
 * account owner may link their own account only to a demo connector (mock or
 * demo bank) while LEDGER_DIRECT_FUNDING_ENABLED is on.
 */
final class LinkCustodianAccountMutation
{
    public function __construct(
        private readonly CustodianAccountService $custodianAccountService,
        private readonly CustodianRegistry $custodianRegistry,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): CustodianAccount
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $accountUuid = (string) $args['account_uuid'];
        $custodianName = (string) $args['custodian_name'];

        if (self::isOperator($user)) {
            // Any team's account (the BelongsToTeam scope would otherwise
            // limit the operator to their own current team).
            /** @var Account $account */
            $account = Account::query()
                ->allTeams()
                ->where('uuid', $accountUuid)
                ->firstOrFail();
        } else {
            $account = Account::findOwnedBy($accountUuid, $user);

            if ($account === null) {
                throw new AuthorizationException('You are not authorized to use this account.');
            }

            if (! $this->allowsOwnerLink($custodianName)) {
                throw new AuthorizationException('Custodian accounts are linked by an operator.');
            }
        }

        return $this->custodianAccountService->linkAccount(
            $account,
            $custodianName,
            (string) $args['custodian_account_id'],
        );
    }

    /**
     * Operators may link, unlink and sync any custodian account link.
     */
    public static function isOperator(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->hasRole(StaffRoles::OPERATORS);
    }

    private function allowsOwnerLink(string $custodianName): bool
    {
        if (! config('ledger.direct_funding_enabled')) {
            return false;
        }

        $connector = $this->custodianRegistry->all()[$custodianName] ?? null;

        return $connector instanceof MockBankConnector || $connector instanceof DemoBankConnector;
    }
}
