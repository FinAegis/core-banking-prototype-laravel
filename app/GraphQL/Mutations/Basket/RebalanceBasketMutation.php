<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Basket;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketRebalancingService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class RebalanceBasketMutation
{
    public function __construct(
        private readonly BasketRebalancingService $rebalancingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): BasketAsset
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Baskets are platform assets: operators only.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage baskets.');
        }

        /** @var BasketAsset|null $basket */
        $basket = BasketAsset::query()->find($args['id']);

        if (! $basket) {
            throw new ModelNotFoundException('Basket not found.');
        }

        $this->rebalancingService->rebalanceIfNeeded($basket);

        return $basket->fresh() ?? $basket;
    }
}
