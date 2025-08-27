<?php

namespace Tests\Unit\Domain\Stablecoin\ValueObjects;

use App\Domain\Stablecoin\ValueObjects\AggregatedPrice;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class AggregatedPriceTest extends DomainTestCase
{
    #[Test]
    public function test_creates_aggregated_price_with_required_fields(): void
    {
        $timestamp = Carbon::now();
        $sources = [
            'chainlink' => '48000.00',
            'binance'   => '48100.00',
        ];

        $aggregatedPrice = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48050.00',
            sources: $sources,
            aggregationMethod: 'median',
            timestamp: $timestamp
        );

        $this->assertEquals('BTC', $aggregatedPrice->base);
        $this->assertEquals('USD', $aggregatedPrice->quote);
        $this->assertEquals('48050.00', $aggregatedPrice->price);
        $this->assertEquals($sources, $aggregatedPrice->sources);
        $this->assertEquals('median', $aggregatedPrice->aggregationMethod);
        $this->assertEquals($timestamp, $aggregatedPrice->timestamp);
        $this->assertEquals(1.0, $aggregatedPrice->confidence);
        $this->assertEquals([], $aggregatedPrice->metadata);
    }

    #[Test]
    public function test_creates_aggregated_price_with_all_fields(): void
    {
        $timestamp = Carbon::now();
        $sources = [
            'chainlink'    => '3200.00',
            'binance'      => '3195.00',
            'internal_amm' => '3198.00',
        ];
        $metadata = [
            'deviation'           => '0.15%',
            'outliers_removed'    => 0,
            'calculation_time_ms' => 45,
        ];

        $aggregatedPrice = new AggregatedPrice(
            base: 'ETH',
            quote: 'USDT',
            price: '3197.67',
            sources: $sources,
            aggregationMethod: 'weighted_average',
            timestamp: $timestamp,
            confidence: 0.95,
            metadata: $metadata
        );

        $this->assertEquals('ETH', $aggregatedPrice->base);
        $this->assertEquals('USDT', $aggregatedPrice->quote);
        $this->assertEquals('3197.67', $aggregatedPrice->price);
        $this->assertEquals($sources, $aggregatedPrice->sources);
        $this->assertEquals('weighted_average', $aggregatedPrice->aggregationMethod);
        $this->assertEquals($timestamp, $aggregatedPrice->timestamp);
        $this->assertEquals(0.95, $aggregatedPrice->confidence);
        $this->assertEquals($metadata, $aggregatedPrice->metadata);
    }

    #[Test]
    public function test_to_array_converts_aggregated_price_correctly(): void
    {
        $timestamp = Carbon::parse('2024-01-01 12:00:00');
        $sources = ['source1' => '100.00', 'source2' => '101.00'];

        $aggregatedPrice = new AggregatedPrice(
            base: 'EUR',
            quote: 'USD',
            price: '100.50',
            sources: $sources,
            aggregationMethod: 'mean',
            timestamp: $timestamp,
            confidence: 0.99,
            metadata: ['samples' => 100]
        );

        $array = $aggregatedPrice->toArray();

        $this->assertEquals([
            'base'               => 'EUR',
            'quote'              => 'USD',
            'price'              => '100.50',
            'sources'            => $sources,
            'aggregation_method' => 'mean',
            'timestamp'          => $timestamp->toIso8601String(),
            'confidence'         => 0.99,
            'metadata'           => ['samples' => 100],
        ], $array);
    }

    #[Test]
    public function test_is_high_confidence_returns_true_for_high_confidence(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48000',
            sources: ['chainlink' => '48000'],
            aggregationMethod: 'single',
            timestamp: Carbon::now(),
            confidence: 0.95
        );

        $this->assertTrue($aggregatedPrice->isHighConfidence());
    }

    #[Test]
    public function test_is_high_confidence_returns_true_at_threshold(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48000',
            sources: ['chainlink' => '48000'],
            aggregationMethod: 'single',
            timestamp: Carbon::now(),
            confidence: 0.8
        );

        $this->assertTrue($aggregatedPrice->isHighConfidence());
    }

    #[Test]
    public function test_is_high_confidence_returns_false_for_low_confidence(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48000',
            sources: ['questionable' => '48000'],
            aggregationMethod: 'single',
            timestamp: Carbon::now(),
            confidence: 0.5
        );

        $this->assertFalse($aggregatedPrice->isHighConfidence());
    }

    #[Test]
    public function test_get_source_count_returns_correct_count(): void
    {
        $singleSource = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48000',
            sources: ['chainlink' => '48000'],
            aggregationMethod: 'single',
            timestamp: Carbon::now()
        );

        $this->assertEquals(1, $singleSource->getSourceCount());

        $multipleSource = new AggregatedPrice(
            base: 'ETH',
            quote: 'USD',
            price: '3200',
            sources: [
                'chainlink'    => '3200',
                'binance'      => '3195',
                'internal_amm' => '3198',
                'kraken'       => '3202',
            ],
            aggregationMethod: 'median',
            timestamp: Carbon::now()
        );

        $this->assertEquals(4, $multipleSource->getSourceCount());
    }

    #[Test]
    public function test_get_source_count_returns_zero_for_empty_sources(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'TEST',
            quote: 'USD',
            price: '0',
            sources: [],
            aggregationMethod: 'none',
            timestamp: Carbon::now()
        );

        $this->assertEquals(0, $aggregatedPrice->getSourceCount());
    }

    #[Test]
    public function test_handles_various_aggregation_methods(): void
    {
        $methods = ['mean', 'median', 'weighted_average', 'vwap', 'twap', 'min', 'max'];

        foreach ($methods as $method) {
            $aggregatedPrice = new AggregatedPrice(
                base: 'BTC',
                quote: 'USD',
                price: '48000',
                sources: ['test' => '48000'],
                aggregationMethod: $method,
                timestamp: Carbon::now()
            );

            $this->assertEquals($method, $aggregatedPrice->aggregationMethod);
        }
    }

    #[Test]
    public function test_handles_zero_confidence(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'SCAM',
            quote: 'USD',
            price: '0.0001',
            sources: ['sketchy_exchange' => '0.0001'],
            aggregationMethod: 'single',
            timestamp: Carbon::now(),
            confidence: 0.0
        );

        $this->assertEquals(0.0, $aggregatedPrice->confidence);
        $this->assertFalse($aggregatedPrice->isHighConfidence());
    }

    #[Test]
    public function test_handles_maximum_confidence(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'USD',
            quote: 'USD',
            price: '1.00',
            sources: ['identity' => '1.00'],
            aggregationMethod: 'fixed',
            timestamp: Carbon::now(),
            confidence: 1.0
        );

        $this->assertEquals(1.0, $aggregatedPrice->confidence);
        $this->assertTrue($aggregatedPrice->isHighConfidence());
    }

    #[Test]
    public function test_sources_with_different_prices(): void
    {
        $sources = [
            'oracle1' => '48000.00',
            'oracle2' => '48500.00',
            'oracle3' => '47500.00',
            'oracle4' => '48200.00',
        ];

        $aggregatedPrice = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48050.00',
            sources: $sources,
            aggregationMethod: 'mean',
            timestamp: Carbon::now(),
            confidence: 0.75
        );

        $this->assertEquals(4, $aggregatedPrice->getSourceCount());
        $this->assertEquals('48000.00', $aggregatedPrice->sources['oracle1']);
        $this->assertEquals('47500.00', $aggregatedPrice->sources['oracle3']);
    }

    #[Test]
    public function test_metadata_can_contain_complex_structures(): void
    {
        $metadata = [
            'weights' => [
                'chainlink'    => 0.5,
                'binance'      => 0.3,
                'internal_amm' => 0.2,
            ],
            'deviations' => [
                'chainlink'    => 0.0,
                'binance'      => 0.002,
                'internal_amm' => -0.001,
            ],
            'timestamps' => [
                'chainlink'    => Carbon::now()->subSeconds(5)->toIso8601String(),
                'binance'      => Carbon::now()->subSeconds(1)->toIso8601String(),
                'internal_amm' => Carbon::now()->toIso8601String(),
            ],
            'quality_scores' => [
                'freshness'    => 0.98,
                'consistency'  => 0.95,
                'availability' => 1.0,
            ],
        ];

        $aggregatedPrice = new AggregatedPrice(
            base: 'ETH',
            quote: 'USD',
            price: '3200.00',
            sources: ['chainlink' => '3200', 'binance' => '3206', 'internal_amm' => '3197'],
            aggregationMethod: 'weighted_average',
            timestamp: Carbon::now(),
            confidence: 0.96,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $aggregatedPrice->metadata);
        $this->assertEquals(0.5, $aggregatedPrice->metadata['weights']['chainlink']);
        $this->assertEquals(0.98, $aggregatedPrice->metadata['quality_scores']['freshness']);
    }

    #[Test]
    public function test_immutability_of_properties(): void
    {
        $aggregatedPrice = new AggregatedPrice(
            base: 'BTC',
            quote: 'USD',
            price: '48000',
            sources: ['test' => '48000'],
            aggregationMethod: 'single',
            timestamp: Carbon::now()
        );

        // Properties are readonly, so we can't modify them
        $reflection = new ReflectionClass($aggregatedPrice);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly());
        }
    }
}
