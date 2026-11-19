<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Treasury;

use App\Domain\Treasury\Models\AssetAllocation;
use App\Domain\Treasury\Services\YieldOptimizationService;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CreatePortfolioMutation
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

        // Asset allocations carry no owner, so only operators create them.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to create a portfolio.');
        }

        $riskProfile = new RiskProfile(
            level: $args['risk_level'] ?? 'medium',
            score: (float) ($args['risk_score'] ?? 50.0),
        );

        $this->yieldService->optimizePortfolio(
            accountId: (string) $user->id,
            totalAmount: (float) ($args['target_amount'] ?? 0),
            targetYield: (float) ($args['target_yield'] ?? 5.0),
            riskProfile: $riskProfile,
        );

        // Return the read-model projection or create a fallback record.
        $portfolioId = Str::uuid()->toString();

        return AssetAllocation::create([
            'portfolio_id'   => $portfolioId,
            'asset_class'    => $args['asset_class'],
            'target_weight'  => $args['target_weight'],
            'current_weight' => 0,
            'drift'          => 0,
            'target_amount'  => $args['target_amount'] ?? null,
            'current_amount' => 0,
        ]);
    }
}
