<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Account;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\FreezeAccountWorkflow;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Workflow\WorkflowStub;

class FreezeAccountMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Account
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Operators act on accounts of every team: the BelongsToTeam scope
        // would otherwise limit them to their own current team.
        $query = $this->isAdmin($user) ? Account::query()->allTeams() : Account::query();

        /** @var Account|null $account */
        $account = $query->find($args['id']);

        if (! $account) {
            throw new ModelNotFoundException('Account not found.');
        }

        // Same rule as REST AccountController::freeze(): the account owner or
        // an admin role.
        if (! $account->isOwnedBy($user) && ! $this->isAdmin($user)) {
            throw new AuthorizationException('You are not authorized to freeze this account.');
        }

        // Update the model directly for immediate API response consistency,
        // recording who froze the account (the owner may lift only their own
        // freeze), before the workflow projects it.
        $account->recordFreeze(byStaff: $this->isAdmin($user));

        $accountUuid = AccountUuid::fromString($account->uuid);
        $reason = $args['reason'] ?? 'Frozen via GraphQL';
        $authorizedBy = $user->name ?? (string) $user->id;

        $workflow = WorkflowStub::make(FreezeAccountWorkflow::class);
        $workflow->start($accountUuid, $reason, $authorizedBy);

        return $account->fresh() ?? $account;
    }

    private function isAdmin(Authenticatable $user): bool
    {
        return $user instanceof User && $user->hasRole(['admin', 'super_admin', 'bank_admin']);
    }
}
