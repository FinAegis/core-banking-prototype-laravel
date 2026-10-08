<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Treasury;

use App\Domain\Treasury\Models\AssetAllocation;
use App\Domain\Treasury\Services\YieldOptimizationService;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class RebalancePortfolioMutation
{
    public function __construct(
        private readonly YieldOptimizationService $yieldService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): AssetAllocation
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Asset allocations carry no owner, so only operators rebalance them.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to rebalance this portfolio.');
        }

        $allocation = AssetAllocation::where('portfolio_id', $args['portfolio_id'])->first();

        if (! $allocation) {
            throw new ModelNotFoundException('Portfolio allocation not found.');
        }

        $riskProfile = new RiskProfile(
            level: $args['risk_level'] ?? 'medium',
            score: (float) ($args['risk_score'] ?? 50.0),
        );

        $this->yieldService->optimizePortfolio(
            accountId: (string) $user->id,
            totalAmount: (float) ($allocation->target_amount ?? 0),
            targetYield: (float) ($args['target_yield'] ?? 5.0),
            riskProfile: $riskProfile,
        );

        return $allocation->fresh() ?? $allocation;
    }
}
