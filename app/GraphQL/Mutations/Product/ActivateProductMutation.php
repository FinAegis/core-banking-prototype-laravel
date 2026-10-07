<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Product;

use App\Domain\Product\Models\Product;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class ActivateProductMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Product
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Activating publishes a catalog product: operators only.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to activate products.');
        }

        /** @var Product|null $product */
        $product = Product::query()->find($args['id']);

        if (! $product) {
            throw new ModelNotFoundException('Product not found.');
        }

        $product->update([
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        return $product->fresh() ?? $product;
    }
}
