<?php

namespace Tests\Unit\Domain\Stablecoin\ValueObjects;

use App\Domain\Stablecoin\ValueObjects\PriceData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class PriceDataTest extends TestCase
{
    #[Test]
    public function test_creates_price_data_with_required_fields(): void
    {
        $timestamp = Carbon::now();

        $priceData = new PriceData(
            base: 'BTC',
            quote: 'USD',
            price: '48000.00',
            source: 'chainlink',
            timestamp: $timestamp
        );

        $this->assertEquals('BTC', $priceData->base);
        $this->assertEquals('USD', $priceData->quote);
        $this->assertEquals('48000.00', $priceData->price);
        $this->assertEquals('chainlink', $priceData->source);
        $this->assertEquals($timestamp, $priceData->timestamp);
        $this->assertNull($priceData->volume24h);
        $this->assertNull($priceData->changePercent24h);
        $this->assertEquals([], $priceData->metadata);
    }

    #[Test]
    public function test_creates_price_data_with_all_fields(): void
    {
        $timestamp = Carbon::now();
        $metadata = ['feed_address' => '0xabc123', 'round_id' => 123456];

        $priceData = new PriceData(
            base: 'ETH',
            quote: 'USDT',
            price: '3200.50',
            source: 'binance',
            timestamp: $timestamp,
            volume24h: '1500000.00',
            changePercent24h: '2.5',
            metadata: $metadata
        );

        $this->assertEquals('ETH', $priceData->base);
        $this->assertEquals('USDT', $priceData->quote);
        $this->assertEquals('3200.50', $priceData->price);
        $this->assertEquals('binance', $priceData->source);
        $this->assertEquals($timestamp, $priceData->timestamp);
        $this->assertEquals('1500000.00', $priceData->volume24h);
        $this->assertEquals('2.5', $priceData->changePercent24h);
        $this->assertEquals($metadata, $priceData->metadata);
    }

    #[Test]
    public function test_to_array_converts_price_data_correctly(): void
    {
        $timestamp = Carbon::parse('2024-01-01 12:00:00');

        $priceData = new PriceData(
            base: 'BTC',
            quote: 'EUR',
            price: '42000.00',
            source: 'internal_amm',
            timestamp: $timestamp,
            volume24h: '500000.00',
            changePercent24h: '-1.25',
            metadata: ['pool_id' => 'pool-123']
        );

        $array = $priceData->toArray();

        $this->assertEquals([
            'base'               => 'BTC',
            'quote'              => 'EUR',
            'price'              => '42000.00',
            'source'             => 'internal_amm',
            'timestamp'          => $timestamp->toIso8601String(),
            'volume_24h'         => '500000.00',
            'change_percent_24h' => '-1.25',
            'metadata'           => ['pool_id' => 'pool-123'],
        ], $array);
    }

    #[Test]
    public function test_to_array_handles_null_optional_fields(): void
    {
        $timestamp = Carbon::now();

        $priceData = new PriceData('GBP', 'USD', '1.27', 'chainlink', $timestamp);

        $array = $priceData->toArray();

        $this->assertNull($array['volume_24h']);
        $this->assertNull($array['change_percent_24h']);
        $this->assertEquals([], $array['metadata']);
    }

    #[Test]
    public function test_is_stale_returns_true_for_old_prices(): void
    {
        $oldTimestamp = Carbon::now()->subMinutes(10);

        $priceData = new PriceData('BTC', 'USD', '48000', 'chainlink', $oldTimestamp);

        // Default 5 minutes (300 seconds)
        $this->assertTrue($priceData->isStale());

        // Custom 15 minutes (900 seconds)
        $this->assertFalse($priceData->isStale(900));
    }

    #[Test]
    public function test_is_stale_returns_false_for_fresh_prices(): void
    {
        $recentTimestamp = Carbon::now()->subSeconds(30);

        $priceData = new PriceData('ETH', 'USD', '3200', 'binance', $recentTimestamp);

        $this->assertFalse($priceData->isStale());

        // Even with 1 minute threshold
        $this->assertFalse($priceData->isStale(60));
    }

    #[Test]
    public function test_is_stale_edge_case_exactly_at_threshold(): void
    {
        $timestamp = Carbon::now()->subSeconds(300);

        $priceData = new PriceData('SOL', 'USD', '150', 'binance', $timestamp);

        // Exactly at 5 minutes should be considered stale
        $this->assertTrue($priceData->isStale(300));
    }

    #[Test]
    public function test_handles_negative_change_percent(): void
    {
        $priceData = new PriceData(
            base: 'LUNA',
            quote: 'USD',
            price: '0.0001',
            source: 'binance',
            timestamp: Carbon::now(),
            volume24h: '1000.00',
            changePercent24h: '-99.99'
        );

        $this->assertEquals('-99.99', $priceData->changePercent24h);
    }

    #[Test]
    public function test_handles_zero_price(): void
    {
        $priceData = new PriceData(
            base: 'TEST',
            quote: 'USD',
            price: '0.00000000',
            source: 'test',
            timestamp: Carbon::now()
        );

        $this->assertEquals('0.00000000', $priceData->price);
    }

    #[Test]
    public function test_handles_very_large_prices(): void
    {
        $priceData = new PriceData(
            base: 'BTC',
            quote: 'SATS',
            price: '100000000.00000000',
            source: 'conversion',
            timestamp: Carbon::now()
        );

        $this->assertEquals('100000000.00000000', $priceData->price);
    }

    #[Test]
    public function test_handles_complex_metadata(): void
    {
        $metadata = [
            'sources'     => ['binance', 'kraken', 'coinbase'],
            'weights'     => [0.4, 0.3, 0.3],
            'outliers'    => ['ftx' => '0.01'],
            'calculation' => [
                'method'     => 'weighted_average',
                'samples'    => 100,
                'confidence' => 0.95,
            ],
        ];

        $priceData = new PriceData(
            base: 'ETH',
            quote: 'USD',
            price: '3200.50',
            source: 'aggregated',
            timestamp: Carbon::now(),
            metadata: $metadata
        );

        $this->assertEquals($metadata, $priceData->metadata);
        $this->assertEquals(['binance', 'kraken', 'coinbase'], $priceData->metadata['sources']);
        $this->assertEquals(0.95, $priceData->metadata['calculation']['confidence']);
    }

    #[Test]
    public function test_different_sources_create_different_instances(): void
    {
        $timestamp = Carbon::now();

        $chainlinkPrice = new PriceData('BTC', 'USD', '48000', 'chainlink', $timestamp);
        $binancePrice = new PriceData('BTC', 'USD', '48100', 'binance', $timestamp);

        $this->assertNotSame($chainlinkPrice, $binancePrice);
        $this->assertEquals('chainlink', $chainlinkPrice->source);
        $this->assertEquals('binance', $binancePrice->source);
        $this->assertEquals('48000', $chainlinkPrice->price);
        $this->assertEquals('48100', $binancePrice->price);
    }

    #[Test]
    public function test_immutability_of_properties(): void
    {
        $priceData = new PriceData(
            base: 'BTC',
            quote: 'USD',
            price: '48000',
            source: 'chainlink',
            timestamp: Carbon::now()
        );

        // Properties are readonly, so we can't modify them
        // This test verifies that the object is immutable
        $reflection = new ReflectionClass($priceData);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly());
        }
    }
}
