<?php

namespace Tests\Unit\Domain\Account\Events;

use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Values\EventQueues;
use Error;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class AssetBalanceSubtractedTest extends DomainTestCase
{
    #[Test]
    public function test_creates_event_with_required_properties(): void
    {
        $hash = Hash::fromData('subtract-hash-value');

        $event = new AssetBalanceSubtracted(
            assetCode: 'USD',
            amount: 2000,
            hash: $hash
        );

        $this->assertEquals('USD', $event->assetCode);
        $this->assertEquals(2000, $event->amount);
        $this->assertSame($hash, $event->hash);
        $this->assertEquals([], $event->metadata);
        $this->assertEquals(EventQueues::TRANSACTIONS->value, $event->queue);
    }

    #[Test]
    public function test_creates_event_with_metadata(): void
    {
        $hash = Hash::fromData('subtract-with-metadata');
        $metadata = [
            'reason'      => 'withdrawal',
            'destination' => 'external_bank',
            'fee'         => 250,
        ];

        $event = new AssetBalanceSubtracted(
            assetCode: 'EUR',
            amount: 5000,
            hash: $hash,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $event->metadata);
        $this->assertEquals('withdrawal', $event->metadata['reason']);
    }

    #[Test]
    public function test_get_amount_returns_correct_value(): void
    {
        $event = new AssetBalanceSubtracted(
            assetCode: 'GBP',
            amount: 75000, // £750.00
            hash: Hash::fromData('gbp-subtract-hash')
        );

        $this->assertEquals(75000, $event->getAmount());
    }

    #[Test]
    public function test_get_asset_code_returns_correct_value(): void
    {
        $event = new AssetBalanceSubtracted(
            assetCode: 'JPY',
            amount: 100000, // ¥100,000
            hash: Hash::fromData('jpy-subtract-hash')
        );

        $this->assertEquals('JPY', $event->getAssetCode());
    }

    #[Test]
    public function test_handles_large_amount_subtraction(): void
    {
        $event = new AssetBalanceSubtracted(
            assetCode: 'BTC',
            amount: 500000000, // 5 BTC in satoshi
            hash: Hash::fromData('large-btc-subtract')
        );

        $this->assertEquals(500000000, $event->amount);
        $this->assertEquals(500000000, $event->getAmount());
    }

    #[Test]
    public function test_metadata_can_contain_complex_data(): void
    {
        $metadata = [
            'transaction_type' => 'payment',
            'recipient'        => [
                'name'    => 'John Doe',
                'account' => '1234567890',
                'bank'    => 'Test Bank',
            ],
            'fees' => [
                'processing' => 100,
                'network'    => 50,
                'total'      => 150,
            ],
            'timestamp' => '2024-01-15T14:30:00Z',
        ];

        $event = new AssetBalanceSubtracted(
            assetCode: 'USD',
            amount: 10000,
            hash: Hash::fromData('complex-metadata-hash'),
            metadata: $metadata
        );

        $this->assertEquals($metadata, $event->metadata);
        $this->assertEquals('payment', $event->metadata['transaction_type']);
        $this->assertEquals(150, $event->metadata['fees']['total']);
    }

    #[Test]
    public function test_event_properties_are_readonly(): void
    {
        $event = new AssetBalanceSubtracted(
            assetCode: 'CHF',
            amount: 3000,
            hash: Hash::fromData('readonly-hash')
        );

        // Attempting to modify readonly property should cause error
        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/Cannot modify readonly property/');
        $event->amount = 4000;
    }

    #[Test]
    public function test_supports_crypto_asset_codes(): void
    {
        $cryptoAssets = [
            'BTC'  => 100000000,    // 1 BTC
            'ETH'  => 1000000000000000000, // 1 ETH
            'USDT' => 100000,      // 1000 USDT
            'BNB'  => 100000000,    // 1 BNB
            'SOL'  => 1000000000,   // 1 SOL
        ];

        foreach ($cryptoAssets as $assetCode => $amount) {
            $event = new AssetBalanceSubtracted(
                assetCode: $assetCode,
                amount: $amount,
                hash: Hash::fromData("crypto-hash-{$assetCode}")
            );

            $this->assertEquals($assetCode, $event->getAssetCode());
            $this->assertEquals($amount, $event->getAmount());
        }
    }
}
