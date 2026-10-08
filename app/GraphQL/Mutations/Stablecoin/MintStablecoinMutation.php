<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Stablecoin;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Models\StablecoinReserve;
use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;
use App\Models\User;
use App\Support\StaffRoles;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Workflow\WorkflowStub;

class MintStablecoinMutation
{
    /**
     * Roles allowed to issue stablecoins: StaffRoles::STABLECOIN_OPERATORS,
     * the same set as the REST stablecoin configuration endpoints and the
     * web stablecoin operations console.
     *
     * @var list<string>
     */
    public const OPERATOR_ROLES = StaffRoles::STABLECOIN_OPERATORS;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): StablecoinReserve
    {
        if (! config('ledger.direct_funding_enabled')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! $user instanceof User || ! $user->hasRole(self::OPERATOR_ROLES)) {
            throw new AuthorizationException('You are not authorized to perform stablecoin operations.');
        }

        $accountUuid = AccountUuid::fromString($args['account_uuid'] ?? $user->uuid);

        $workflow = WorkflowStub::make(MintStablecoinWorkflow::class);
        $workflow->start(
            $accountUuid,
            $args['stablecoin_code'],
            $args['collateral_asset_code'] ?? $args['stablecoin_code'],
            (int) $args['amount'],
            (int) $args['amount'],
        );

        // Return the read-model projection or create a fallback record.
        return StablecoinReserve::create([
            'reserve_id'      => Str::uuid()->toString(),
            'pool_id'         => $args['pool_id'],
            'stablecoin_code' => $args['stablecoin_code'],
            'asset_code'      => $args['stablecoin_code'],
            'amount'          => $args['amount'],
            'value_usd'       => $args['amount'],
            'custodian_type'  => 'internal',
            'status'          => 'active',
        ]);
    }
}
