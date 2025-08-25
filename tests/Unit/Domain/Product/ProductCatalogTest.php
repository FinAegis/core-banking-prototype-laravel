<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Product;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Services\ProductCatalogService;
use App\Domain\Product\ValueObjects\Feature;
use App\Domain\Product\ValueObjects\Price;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private ProductCatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductCatalogService();
    }

    public function test_can_create_product(): void
    {
        $product = $this->service->createProduct([
            'name'        => 'Premium Account',
            'description' => 'Premium banking account with advanced features',
            'category'    => 'Banking',
            'type'        => 'subscription',
            'price'       => [
                'amount'   => 9.99,
                'currency' => 'USD',
                'interval' => 'monthly',
            ],
        ], 'admin');

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Premium Account', $product->name);
        $this->assertEquals('Banking', $product->category);
        $this->assertEquals('subscription', $product->type);
        $this->assertEquals('draft', $product->status);
    }

    public function test_can_add_features_to_product(): void
    {
        $product = $this->service->createProduct([
            'name'        => 'Test Product',
            'description' => 'Test description',
            'category'    => 'Test',
            'type'        => 'service',
        ], 'admin');

        $updated = $this->service->addFeature($product->id, [
            'code'        => 'unlimited_transfers',
            'name'        => 'Unlimited Transfers',
            'description' => 'No limit on transfers',
            'enabled'     => true,
            'limits'      => [
                'daily'   => null,
                'monthly' => null,
            ],
        ], 'admin');

        $this->assertTrue($updated->hasFeature('unlimited_transfers'));
        $feature = $updated->getFeature('unlimited_transfers');
        $this->assertEquals('Unlimited Transfers', $feature['name']);
    }

    public function test_can_update_pricing(): void
    {
        $product = $this->service->createProduct([
            'name'        => 'Test Product',
            'description' => 'Test description',
            'category'    => 'Test',
            'type'        => 'service',
        ], 'admin');

        $updated = $this->service->updatePricing($product->id, [
            'amount'   => 19.99,
            'currency' => 'EUR',
            'type'     => 'fixed',
            'interval' => 'yearly',
        ], 'admin');

        $price = $updated->getPrice('EUR');
        $this->assertNotNull($price);
        $this->assertEquals(19.99, $price['amount']);
        $this->assertEquals('yearly', $price['interval']);
    }

    public function test_can_activate_product(): void
    {
        $product = $this->service->createProduct([
            'name'        => 'Test Product',
            'description' => 'Test description',
            'category'    => 'Test',
            'type'        => 'service',
        ], 'admin');

        $this->assertEquals('draft', $product->status);

        $activated = $this->service->activateProduct($product->id, 'admin');

        $this->assertEquals('active', $activated->status);
        $this->assertNotNull($activated->activated_at);
    }

    public function test_can_deactivate_product(): void
    {
        $product = $this->service->createProduct([
            'name'        => 'Test Product',
            'description' => 'Test description',
            'category'    => 'Test',
            'type'        => 'service',
        ], 'admin');

        $activated = $this->service->activateProduct($product->id, 'admin');
        $deactivated = $this->service->deactivateProduct(
            $product->id,
            'Product discontinued',
            'admin'
        );

        $this->assertEquals('inactive', $deactivated->status);
        $this->assertNotNull($deactivated->deactivated_at);
    }

    public function test_can_search_products(): void
    {
        // Create some products
        $this->service->createProduct([
            'name'        => 'Banking Premium',
            'description' => 'Premium banking features',
            'category'    => 'Banking',
            'type'        => 'subscription',
        ], 'admin');

        $product2 = $this->service->createProduct([
            'name'        => 'Investment Pro',
            'description' => 'Professional investment tools',
            'category'    => 'Investment',
            'type'        => 'subscription',
        ], 'admin');

        // Activate the second product
        $this->service->activateProduct($product2->id, 'admin');

        // Search for "investment"
        $results = $this->service->searchProducts('investment');

        $this->assertCount(1, $results);
        $this->assertEquals('Investment Pro', $results->first()->name);
    }

    public function test_price_value_object(): void
    {
        $price = new Price(
            amount: 99.99,
            currency: 'USD',
            type: 'fixed',
            interval: 'monthly'
        );

        $this->assertEquals(99.99, $price->getAmount());
        $this->assertEquals('USD', $price->getCurrency());
        $this->assertEquals('$99.99', $price->getFormattedAmount());
        $this->assertTrue($price->isRecurring());

        $total = $price->calculateTotal(3);
        $this->assertEqualsWithDelta(299.97, $total, 0.01);
    }

    public function test_feature_value_object(): void
    {
        $feature = new Feature(
            code: 'api_access',
            name: 'API Access',
            description: 'Full API access',
            enabled: true,
            limits: [
                'requests_per_minute' => 100,
                'requests_per_day'    => 10000,
            ]
        );

        $this->assertEquals('api_access', $feature->getCode());
        $this->assertTrue($feature->isEnabled());
        $this->assertEquals(100, $feature->getLimit('requests_per_minute'));
        $this->assertTrue($feature->hasLimit('requests_per_day'));
        $this->assertFalse($feature->hasLimit('requests_per_hour'));
    }
}
