<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Basket;

use App\Domain\Basket\Models\BasketAsset;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CreateBasketMutation
{
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

        $code = 'BSK-' . strtoupper(Str::random(6));

        // Create basket through the model (event sourced via projectors).
        // The BasketService composeBasket workflow handles composition;
        // here we create the basket definition itself.
        /** @var BasketAsset $basket */
        $basket = BasketAsset::create([
            'code'                => $code,
            'name'                => $args['name'],
            'description'         => $args['description'] ?? null,
            'type'                => $args['type'] ?? 'static',
            'rebalance_frequency' => $args['rebalance_frequency'] ?? 'never',
            'is_active'           => true,
            'created_by'          => $user->uuid ?? (string) $user->id,
        ]);

        return $basket;
    }
}
