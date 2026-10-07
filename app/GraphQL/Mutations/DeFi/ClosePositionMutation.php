<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\DeFi;

use App\Domain\DeFi\Models\DeFiPosition;
use App\Domain\DeFi\Services\DeFiPositionTrackerService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class ClosePositionMutation
{
    public function __construct(
        private readonly DeFiPositionTrackerService $positionTracker,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): DeFiPosition
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        /** @var DeFiPosition|null $position */
        $position = DeFiPosition::query()->find($args['position_id']);

        if (! $position) {
            throw new ModelNotFoundException('DeFi position not found.');
        }

        // The position's owner or an operator.
        if ((string) $position->user_id !== (string) $user->id && ! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to close this position.');
        }

        $walletAddress = $args['wallet_address'] ?? '';
        $positionId = (string) ($position->position_id ?? $position->id);

        $this->positionTracker->closePosition($walletAddress, $positionId);

        $position->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return $position->fresh() ?? $position;
    }
}
