<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Product;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Services\ProductCatalogService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class CreateProductMutation
{
    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): Product
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Catalog products are managed by platform operators.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to create products.');
        }

        $product = $this->productCatalogService->createProduct(
            data: [
                'name'        => $args['name'],
                'description' => $args['description'] ?? '',
                'category'    => $args['category'],
                'type'        => $args['type'],
            ],
            createdBy: (string) $user->id,
        );

        return $product;
    }
}
