<?php

namespace App\Domain\Product\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

class SubProductService
{
    /**
     * Cache duration in seconds (5 minutes).
     */
    private const CACHE_TTL = 300;

    /**
     * Check if a sub-product is enabled.
     */
    public function isEnabled(string $subProduct): bool
    {
        $cacheKey = "sub_product.{$subProduct}.enabled";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($subProduct) {
                // Check if feature flag exists
                $featureKey = "sub_product.{$subProduct}";

                try {
                    if (Feature::active($featureKey)) {
                        return true;
                    }

                    if (Feature::inactive($featureKey)) {
                        return false;
                    }
                } catch (Exception $e) {
                    // Feature not defined, fall back to config
                }

                // Fall back to config
                return config("sub_products.{$subProduct}.enabled", false);
            }
        );
    }

    /**
     * Check if a specific feature within a sub-product is enabled.
     */
    public function isFeatureEnabled(string $subProduct, string $feature): bool
    {
        // If sub-product is disabled, all features are disabled
        if (! $this->isEnabled($subProduct)) {
            return false;
        }

        $cacheKey = "sub_product.{$subProduct}.feature.{$feature}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($subProduct, $feature) {
                // Check feature flag
                $featureKey = "sub_product.{$subProduct}.{$feature}";

                try {
                    if (Feature::active($featureKey)) {
                        return true;
                    }

                    if (Feature::inactive($featureKey)) {
                        return false;
                    }
                } catch (Exception $e) {
                    // Feature not defined, fall back to config
                }

                // Fall back to config
                return config("sub_products.{$subProduct}.features.{$feature}", false);
            }
        );
    }

    /**
     * Get all enabled sub-products.
     */
    public function getEnabledSubProducts(): array
    {
        return Cache::remember(
            'sub_products.enabled',
            self::CACHE_TTL,
            function () {
                $subProducts = config('sub_products', []);
                $enabled = [];

                foreach ($subProducts as $key => $config) {
                    if ($this->isEnabled($key)) {
                        $enabled[$key] = array_merge(
                            $config,
                            [
                                'key'              => $key,
                                'enabled_features' => $this->getEnabledFeatures($key),
                            ]
                        );
                    }
                }

                return $enabled;
            }
        );
    }

    /**
     * Get all sub-products with their status.
     */
    public function getAllSubProducts(): array
    {
        $subProducts = config('sub_products', []);
        $all = [];

        foreach ($subProducts as $key => $config) {
            $isEnabled = $this->isEnabled($key);
            $all[$key] = array_merge(
                $config,
                [
                    'key'              => $key,
                    'is_enabled'       => $isEnabled,
                    'enabled_features' => $isEnabled ? $this->getEnabledFeatures($key) : [],
                ]
            );
        }

        return $all;
    }

    /**
     * Get enabled features for a sub-product.
     */
    public function getEnabledFeatures(string $subProduct): array
    {
        if (! $this->isEnabled($subProduct)) {
            return [];
        }

        $features = config("sub_products.{$subProduct}.features", []);
        $enabled = [];

        foreach ($features as $feature => $default) {
            if ($this->isFeatureEnabled($subProduct, $feature)) {
                $enabled[] = $feature;
            }
        }

        return $enabled;
    }

    /**
     * Enable a sub-product (requires admin permission).
     */
    public function enableSubProduct(string $subProduct, ?string $enabledBy = null): bool
    {
        try {
            Feature::activate("sub_product.{$subProduct}");
            $this->clearCache();

            Log::info(
                'Sub-product enabled',
                [
                    'sub_product' => $subProduct,
                    'enabled_by'  => $enabledBy ?? 'system',
                ]
            );

            return true;
        } catch (Exception $e) {
            Log::error(
                'Failed to enable sub-product',
                [
                    'sub_product' => $subProduct,
                    'error'       => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Disable a sub-product (requires admin permission).
     */
    public function disableSubProduct(string $subProduct, ?string $disabledBy = null): bool
    {
        try {
            Feature::deactivate("sub_product.{$subProduct}");
            $this->clearCache();

            Log::info(
                'Sub-product disabled',
                [
                    'sub_product' => $subProduct,
                    'disabled_by' => $disabledBy ?? 'system',
                ]
            );

            return true;
        } catch (Exception $e) {
            Log::error(
                'Failed to disable sub-product',
                [
                    'sub_product' => $subProduct,
                    'error'       => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Enable a specific feature within a sub-product.
     */
    public function enableFeature(string $subProduct, string $feature, ?string $enabledBy = null): bool
    {
        try {
            Feature::activate("sub_product.{$subProduct}.{$feature}");
            $this->clearCache();

            Log::info(
                'Sub-product feature enabled',
                [
                    'sub_product' => $subProduct,
                    'feature'     => $feature,
                    'enabled_by'  => $enabledBy ?? 'system',
                ]
            );

            return true;
        } catch (Exception $e) {
            Log::error(
                'Failed to enable sub-product feature',
                [
                    'sub_product' => $subProduct,
                    'feature'     => $feature,
                    'error'       => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Disable a specific feature within a sub-product.
     */
    public function disableFeature(string $subProduct, string $feature, ?string $disabledBy = null): bool
    {
        try {
            Feature::deactivate("sub_product.{$subProduct}.{$feature}");
            $this->clearCache();

            Log::info(
                'Sub-product feature disabled',
                [
                    'sub_product' => $subProduct,
                    'feature'     => $feature,
                    'disabled_by' => $disabledBy ?? 'system',
                ]
            );

            return true;
        } catch (Exception $e) {
            Log::error(
                'Failed to disable sub-product feature',
                [
                    'sub_product' => $subProduct,
                    'feature'     => $feature,
                    'error'       => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Check if user has required licenses for a sub-product.
     */
    public function hasRequiredLicenses(string $subProduct, array $userLicenses = []): bool
    {
        $requiredLicenses = config("sub_products.{$subProduct}.licenses", []);

        if (empty($requiredLicenses)) {
            return true;
        }

        return ! empty(array_intersect($requiredLicenses, $userLicenses));
    }

    /**
     * Get sub-product configuration.
     */
    public function getConfiguration(string $subProduct): array
    {
        return config("sub_products.{$subProduct}", []);
    }

    /**
     * Clear all sub-product related caches.
     */
    public function clearCache(): void
    {
        // Clear all sub-product cache keys
        $subProducts = array_keys(config('sub_products', []));

        foreach ($subProducts as $subProduct) {
            Cache::forget("sub_product.{$subProduct}.enabled");

            $features = array_keys(config("sub_products.{$subProduct}.features", []));
            foreach ($features as $feature) {
                Cache::forget("sub_product.{$subProduct}.feature.{$feature}");
            }
        }

        Cache::forget('sub_products.enabled');
    }

    /**
     * Get sub-product status for API response.
     */
    public function getApiStatus(): array
    {
        $subProducts = $this->getAllSubProducts();
        $status = [];

        foreach ($subProducts as $key => $config) {
            $status[$key] = [
                'enabled'     => $config['is_enabled'],
                'name'        => $config['name'],
                'description' => $config['description'],
                'features'    => array_map(
                    function ($feature) use ($key) {
                        return [
                            'key'     => $feature,
                            'enabled' => $this->isFeatureEnabled($key, $feature),
                        ];
                    },
                    array_keys($config['features'])
                ),
            ];
        }

        return $status;
    }

    /**
     * Get sub-product config.
     */
    public function getSubProductConfig(string $subProduct): ?array
    {
        $config = config("sub_products.{$subProduct}");

        return $config ?: null;
    }

    /**
     * Get features for sub-product.
     */
    public function getFeatures(string $subProduct): array
    {
        return config("sub_products.{$subProduct}.features", []);
    }

    /**
     * Get required licenses.
     */
    public function getRequiredLicenses(string $subProduct): array
    {
        return config("sub_products.{$subProduct}.licenses", []);
    }

    /**
     * Get metadata.
     */
    public function getMetadata(string $subProduct): array
    {
        return config("sub_products.{$subProduct}.metadata", []);
    }

    /**
     * Validate sub-product access.
     */
    public function validateAccess(string $subProduct, array $context = []): bool
    {
        if (! $this->isEnabled($subProduct)) {
            return false;
        }

        // Check licenses if provided in context
        if (isset($context['user_licenses'])) {
            return $this->hasRequiredLicenses($subProduct, $context['user_licenses']);
        }

        return true;
    }
}
