<?php

declare(strict_types=1);

namespace App\Domain\Product\Services;

use App\Domain\Product\Aggregates\Product;
use App\Domain\Product\Models\Product as ProductModel;
use App\Domain\Product\ValueObjects\Feature;
use App\Domain\Product\ValueObjects\Price;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCatalogService
{
    /**
     * Create a new product.
     */
    public function createProduct(array $data, string $createdBy): ProductModel
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $productId = Str::uuid()->toString();

            $product = Product::create(
                productId: $productId,
                name: $data['name'],
                description: $data['description'],
                category: $data['category'],
                type: $data['type'],
                metadata: $data['metadata'] ?? []
            );

            // Add default price if provided
            if (isset($data['price'])) {
                $price = new Price(
                    amount: $data['price']['amount'],
                    currency: $data['price']['currency'] ?? 'USD',
                    type: $data['price']['type'] ?? 'fixed',
                    interval: $data['price']['interval'] ?? 'one_time'
                );
                $product->updatePrice($price, $createdBy);
            }

            // Add features if provided
            if (isset($data['features']) && is_array($data['features'])) {
                foreach ($data['features'] as $featureData) {
                    $feature = Feature::fromArray($featureData);
                    $product->addFeature($feature, $createdBy);
                }
            }

            $product->persist();

            return ProductModel::find($productId);
        });
    }

    /**
     * Update a product.
     */
    public function updateProduct(string $productId, array $data, string $updatedBy): ProductModel
    {
        return DB::transaction(function () use ($productId, $data, $updatedBy) {
            $product = Product::retrieve($productId);
            $product->update($data, $updatedBy);
            $product->persist();

            // Clear cache
            $this->clearProductCache($productId);

            return ProductModel::find($productId);
        });
    }

    /**
     * Add a feature to a product.
     */
    public function addFeature(string $productId, array $featureData, string $addedBy): ProductModel
    {
        return DB::transaction(function () use ($productId, $featureData, $addedBy) {
            $product = Product::retrieve($productId);
            $feature = Feature::fromArray($featureData);
            $product->addFeature($feature, $addedBy);
            $product->persist();

            $this->clearProductCache($productId);

            return ProductModel::find($productId);
        });
    }

    /**
     * Remove a feature from a product.
     */
    public function removeFeature(string $productId, string $featureCode, string $removedBy): ProductModel
    {
        return DB::transaction(function () use ($productId, $featureCode, $removedBy) {
            $product = Product::retrieve($productId);
            $product->removeFeature($featureCode, $removedBy);
            $product->persist();

            $this->clearProductCache($productId);

            return ProductModel::find($productId);
        });
    }

    /**
     * Update product pricing.
     */
    public function updatePricing(string $productId, array $priceData, string $updatedBy): ProductModel
    {
        return DB::transaction(function () use ($productId, $priceData, $updatedBy) {
            $product = Product::retrieve($productId);
            $price = Price::fromArray($priceData);
            $product->updatePrice($price, $updatedBy);
            $product->persist();

            $this->clearProductCache($productId);

            return ProductModel::find($productId);
        });
    }

    /**
     * Activate a product.
     */
    public function activateProduct(string $productId, string $activatedBy): ProductModel
    {
        return DB::transaction(function () use ($productId, $activatedBy) {
            $product = Product::retrieve($productId);
            $product->activate($activatedBy);
            $product->persist();

            $this->clearProductCache($productId);

            return ProductModel::find($productId);
        });
    }

    /**
     * Deactivate a product.
     */
    public function deactivateProduct(string $productId, string $reason, string $deactivatedBy): ProductModel
    {
        return DB::transaction(function () use ($productId, $reason, $deactivatedBy) {
            $product = Product::retrieve($productId);
            $product->deactivate($reason, $deactivatedBy);
            $product->persist();

            $this->clearProductCache($productId);

            return ProductModel::find($productId);
        });
    }

    /**
     * Get all products.
     */
    public function getAllProducts(array $filters = []): Collection
    {
        $query = ProductModel::query();

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('status', 'active');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get product by ID.
     */
    public function getProduct(string $productId): ?ProductModel
    {
        return Cache::remember("product.{$productId}", 3600, function () use ($productId) {
            return ProductModel::find($productId);
        });
    }

    /**
     * Get products by category.
     */
    public function getProductsByCategory(string $category): Collection
    {
        return Cache::remember("products.category.{$category}", 1800, function () use ($category) {
            return ProductModel::where('category', $category)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Search products.
     */
    public function searchProducts(string $query): Collection
    {
        return ProductModel::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get product categories.
     */
    public function getCategories(): array
    {
        return Cache::remember('product.categories', 3600, function () {
            return ProductModel::distinct()
                ->pluck('category')
                ->filter()
                ->sort()
                ->values()
                ->toArray();
        });
    }

    /**
     * Compare products.
     */
    public function compareProducts(array $productIds): array
    {
        $products = ProductModel::whereIn('id', $productIds)->get();

        $comparison = [
            'products' => [],
            'features' => [],
            'prices'   => [],
        ];

        foreach ($products as $product) {
            $comparison['products'][$product->id] = [
                'name'        => $product->name,
                'description' => $product->description,
                'category'    => $product->category,
                'type'        => $product->type,
            ];

            foreach ($product->features as $feature) {
                if (! isset($comparison['features'][$feature['code']])) {
                    $comparison['features'][$feature['code']] = [
                        'name'     => $feature['name'],
                        'products' => [],
                    ];
                }
                $comparison['features'][$feature['code']]['products'][$product->id] = $feature['enabled'];
            }

            foreach ($product->prices as $price) {
                $currency = $price['currency'];
                if (! isset($comparison['prices'][$currency])) {
                    $comparison['prices'][$currency] = [];
                }
                $comparison['prices'][$currency][$product->id] = $price['amount'];
            }
        }

        return $comparison;
    }

    /**
     * Get recommended products.
     */
    public function getRecommendedProducts(string $userId, int $limit = 5): Collection
    {
        // Simple recommendation based on user's existing products
        $userProducts = DB::table('user_products')
            ->where('user_id', $userId)
            ->pluck('product_id');

        if ($userProducts->isEmpty()) {
            // Return popular products for new users
            return ProductModel::where('status', 'active')
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        }

        // Get categories of user's products
        $categories = ProductModel::whereIn('id', $userProducts)
            ->pluck('category')
            ->unique();

        // Recommend products from same categories that user doesn't have
        return ProductModel::where('status', 'active')
            ->whereIn('category', $categories)
            ->whereNotIn('id', $userProducts)
            ->orderBy('popularity_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clear product cache.
     */
    private function clearProductCache(string $productId): void
    {
        Cache::forget("product.{$productId}");

        // Also clear category caches
        $product = ProductModel::find($productId);
        if ($product) {
            Cache::forget("products.category.{$product->category}");
        }

        Cache::forget('product.categories');
    }
}
