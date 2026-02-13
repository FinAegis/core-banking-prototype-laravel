<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Account;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Workflows\FreezeAccountWorkflow;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
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

        /** @var Account|null $account */
        $account = Account::query()->find($args['id']);

        if (! $account) {
            throw new ModelNotFoundException('Account not found.');
        }

        $accountUuid = AccountUuid::fromString($account->uuid);
        $reason = $args['reason'] ?? 'Frozen via GraphQL';
        $authorizedBy = $user->name ?? (string) $user->id;

        $workflow = WorkflowStub::make(FreezeAccountWorkflow::class);
        $workflow->start($accountUuid, $reason, $authorizedBy);

        // Update model directly for immediate API response consistency
        $account->update(['frozen' => true]);

        return $account->fresh() ?? $account;
    }
}
