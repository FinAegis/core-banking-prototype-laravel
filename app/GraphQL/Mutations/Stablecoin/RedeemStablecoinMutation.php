<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Stablecoin;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Models\StablecoinReserve;
use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;
use App\Models\User;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Workflow\WorkflowStub;

class RedeemStablecoinMutation
{
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

        if (! $user instanceof User || ! $user->hasRole(MintStablecoinMutation::OPERATOR_ROLES)) {
            throw new AuthorizationException('You are not authorized to perform stablecoin operations.');
        }

        $reserve = StablecoinReserve::where('reserve_id', $args['reserve_id'])->first();

        if (! $reserve) {
            throw new ModelNotFoundException('Stablecoin reserve not found.');
        }

        $currentAmount = (float) $reserve->amount;
        $redeemAmount = (float) $args['amount'];

        if ($redeemAmount > $currentAmount) {
            throw new InvalidArgumentException('Redeem amount exceeds available reserve.');
        }

        $accountUuid = AccountUuid::fromString($args['account_uuid'] ?? $user->uuid);

        $workflow = WorkflowStub::make(BurnStablecoinWorkflow::class);
        $workflow->start(
            $accountUuid,
            $args['position_uuid'] ?? $args['reserve_id'],
            (string) $reserve->stablecoin_code,
            (int) $redeemAmount,
            (int) $redeemAmount,
            false,
        );

        // Update read-model for immediate response.
        $newAmount = number_format($currentAmount - $redeemAmount, 18, '.', '');
        $reserve->update([
            'amount'    => $newAmount,
            'value_usd' => $newAmount,
        ]);

        return $reserve->fresh() ?? $reserve;
    }
}
