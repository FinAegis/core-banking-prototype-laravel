<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SubProductService;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubProductServiceTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected SubProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up default config
        Config::shouldReceive('get')->with('sub_products')->andReturn([
            'exchange' => [
                'enabled' => true,
                'features' => [
                    'fiat_trading' => true,
                    'crypto_trading' => false,
                ],
                'licenses' => ['vasp', 'mica'],
                'metadata' => ['launch_date' => '2025-03-01'],
            ],
            'lending' => [
                'enabled' => false,
                'features' => [
                    'sme_loans' => true,
                    'p2p_marketplace' => true,
                ],
                'licenses' => ['lending_license'],
                'metadata' => ['launch_date' => '2025-10-01'],
            ],
        ]);
        
        // Mock Feature facade
        Feature::shouldReceive('active')->andReturnUsing(function ($feature) {
            $map = [
                'sub_product.exchange' => true,
                'sub_product.lending' => false,
                'sub_product.exchange.fiat_trading' => true,
                'sub_product.exchange.crypto_trading' => false,
            ];
            return $map[$feature] ?? false;
        });
        
        $this->service = new SubProductService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_checks_if_sub_product_is_enabled()
    {
        $this->assertTrue($this->service->isEnabled('exchange'));
        $this->assertFalse($this->service->isEnabled('lending'));
        $this->assertFalse($this->service->isEnabled('non_existent'));
    }

    /** @test */
    public function it_gets_all_sub_products()
    {
        $products = $this->service->getAllSubProducts();
        
        $this->assertArrayHasKey('exchange', $products);
        $this->assertArrayHasKey('lending', $products);
        $this->assertEquals(2, count($products));
    }

    /** @test */
    public function it_gets_enabled_sub_products()
    {
        $enabled = $this->service->getEnabledSubProducts();
        
        $this->assertArrayHasKey('exchange', $enabled);
        $this->assertArrayNotHasKey('lending', $enabled);
        $this->assertEquals(1, count($enabled));
    }

    /** @test */
    public function it_checks_if_feature_is_enabled()
    {
        $this->assertTrue($this->service->isFeatureEnabled('exchange', 'fiat_trading'));
        $this->assertFalse($this->service->isFeatureEnabled('exchange', 'crypto_trading'));
        $this->assertFalse($this->service->isFeatureEnabled('lending', 'sme_loans'));
        $this->assertFalse($this->service->isFeatureEnabled('non_existent', 'feature'));
    }

    /** @test */
    public function it_gets_sub_product_config()
    {
        $config = $this->service->getSubProductConfig('exchange');
        
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('features', $config);
        $this->assertArrayHasKey('licenses', $config);
        $this->assertArrayHasKey('metadata', $config);
        $this->assertTrue($config['enabled']);
    }

    /** @test */
    public function it_returns_null_for_non_existent_sub_product_config()
    {
        $config = $this->service->getSubProductConfig('non_existent');
        
        $this->assertNull($config);
    }

    /** @test */
    public function it_gets_features_for_sub_product()
    {
        $features = $this->service->getFeatures('exchange');
        
        $this->assertArrayHasKey('fiat_trading', $features);
        $this->assertArrayHasKey('crypto_trading', $features);
        $this->assertTrue($features['fiat_trading']);
        $this->assertFalse($features['crypto_trading']);
    }

    /** @test */
    public function it_returns_empty_array_for_non_existent_sub_product_features()
    {
        $features = $this->service->getFeatures('non_existent');
        
        $this->assertEquals([], $features);
    }

    /** @test */
    public function it_gets_required_licenses()
    {
        $licenses = $this->service->getRequiredLicenses('exchange');
        
        $this->assertContains('vasp', $licenses);
        $this->assertContains('mica', $licenses);
        $this->assertEquals(2, count($licenses));
    }

    /** @test */
    public function it_returns_empty_array_for_non_existent_sub_product_licenses()
    {
        $licenses = $this->service->getRequiredLicenses('non_existent');
        
        $this->assertEquals([], $licenses);
    }

    /** @test */
    public function it_gets_metadata()
    {
        $metadata = $this->service->getMetadata('exchange');
        
        $this->assertArrayHasKey('launch_date', $metadata);
        $this->assertEquals('2025-03-01', $metadata['launch_date']);
    }

    /** @test */
    public function it_returns_empty_array_for_non_existent_sub_product_metadata()
    {
        $metadata = $this->service->getMetadata('non_existent');
        
        $this->assertEquals([], $metadata);
    }

    /** @test */
    public function it_validates_sub_product_access()
    {
        // Should not throw for enabled product
        $this->assertNull($this->service->validateAccess('exchange'));
        
        // Should throw for disabled product
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sub-product lending is not enabled');
        
        $this->service->validateAccess('lending');
    }

    /** @test */
    public function it_validates_feature_access()
    {
        // Should not throw for enabled feature
        $this->assertNull($this->service->validateFeatureAccess('exchange', 'fiat_trading'));
        
        // Should throw for disabled feature
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feature crypto_trading is not enabled for sub-product exchange');
        
        $this->service->validateFeatureAccess('exchange', 'crypto_trading');
    }

    /** @test */
    public function it_enables_sub_product()
    {
        Config::shouldReceive('set')
            ->once()
            ->with('sub_products.lending.enabled', true);
        
        Feature::shouldReceive('activate')
            ->once()
            ->with('sub_product.lending');
        
        $this->service->enableSubProduct('lending');
    }

    /** @test */
    public function it_disables_sub_product()
    {
        Config::shouldReceive('set')
            ->once()
            ->with('sub_products.exchange.enabled', false);
        
        Feature::shouldReceive('deactivate')
            ->once()
            ->with('sub_product.exchange');
        
        $this->service->disableSubProduct('exchange');
    }

    /** @test */
    public function it_enables_feature()
    {
        Config::shouldReceive('set')
            ->once()
            ->with('sub_products.exchange.features.crypto_trading', true);
        
        Feature::shouldReceive('activate')
            ->once()
            ->with('sub_product.exchange.crypto_trading');
        
        $this->service->enableFeature('exchange', 'crypto_trading');
    }

    /** @test */
    public function it_disables_feature()
    {
        Config::shouldReceive('set')
            ->once()
            ->with('sub_products.exchange.features.fiat_trading', false);
        
        Feature::shouldReceive('deactivate')
            ->once()
            ->with('sub_product.exchange.fiat_trading');
        
        $this->service->disableFeature('exchange', 'fiat_trading');
    }

    /** @test */
    public function it_handles_missing_features_array()
    {
        Config::shouldReceive('get')->with('sub_products')->andReturn([
            'minimal' => [
                'enabled' => true,
                // No features array
            ],
        ]);
        
        $service = new SubProductService();
        
        $features = $service->getFeatures('minimal');
        $this->assertEquals([], $features);
        
        $this->assertFalse($service->isFeatureEnabled('minimal', 'any_feature'));
    }

    /** @test */
    public function it_handles_missing_licenses_array()
    {
        Config::shouldReceive('get')->with('sub_products')->andReturn([
            'minimal' => [
                'enabled' => true,
                // No licenses array
            ],
        ]);
        
        $service = new SubProductService();
        
        $licenses = $service->getRequiredLicenses('minimal');
        $this->assertEquals([], $licenses);
    }

    /** @test */
    public function it_handles_missing_metadata_array()
    {
        Config::shouldReceive('get')->with('sub_products')->andReturn([
            'minimal' => [
                'enabled' => true,
                // No metadata array
            ],
        ]);
        
        $service = new SubProductService();
        
        $metadata = $service->getMetadata('minimal');
        $this->assertEquals([], $metadata);
    }
}