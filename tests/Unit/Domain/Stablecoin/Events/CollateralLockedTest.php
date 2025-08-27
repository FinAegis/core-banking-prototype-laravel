<?php

namespace Tests\Unit\Domain\Stablecoin\Events;

use App\Domain\Stablecoin\Events\CollateralLocked;
use Error;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Tests\DomainTestCase;

class CollateralLockedTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(CollateralLocked::class));
    }

    #[Test]
    public function test_extends_should_be_stored(): void
    {
        $reflection = new ReflectionClass(CollateralLocked::class);
        $this->assertTrue($reflection->isSubclassOf(ShouldBeStored::class));
    }

    #[Test]
    public function test_event_has_constructor_properties(): void
    {
        $reflection = new ReflectionClass(CollateralLocked::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(5, $constructor->getNumberOfParameters());

        $parameters = $constructor->getParameters();

        $this->assertEquals('position_uuid', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());

        $this->assertEquals('account_uuid', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('collateral_asset_code', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('amount', $parameters[3]->getName());
        $this->assertEquals('float', $parameters[3]->getType()->getName());

        $this->assertEquals('metadata', $parameters[4]->getName());
        $this->assertEquals('array', $parameters[4]->getType()->getName());
        $this->assertTrue($parameters[4]->isDefaultValueAvailable());
        $this->assertEquals([], $parameters[4]->getDefaultValue());
    }

    #[Test]
    public function test_event_properties_are_public_readonly(): void
    {
        $reflection = new ReflectionClass(CollateralLocked::class);

        $properties = [
            'position_uuid',
            'account_uuid',
            'collateral_asset_code',
            'amount',
            'metadata',
        ];

        foreach ($properties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $this->assertTrue($property->isPublic());
            $this->assertTrue($property->isReadOnly());
        }
    }

    #[Test]
    public function test_event_creation_with_minimal_data(): void
    {
        $positionUuid = 'pos-123';
        $accountUuid = 'acc-456';
        $assetCode = 'ETH';
        $amount = 1000000;

        $event = new CollateralLocked(
            position_uuid: $positionUuid,
            account_uuid: $accountUuid,
            collateral_asset_code: $assetCode,
            amount: $amount
        );

        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($assetCode, $event->collateral_asset_code);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals([], $event->metadata);
    }

    #[Test]
    public function test_event_creation_with_metadata(): void
    {
        $positionUuid = 'pos-123';
        $accountUuid = 'acc-456';
        $assetCode = 'ETH';
        $amount = 1000000;
        $metadata = [
            'transaction_hash' => '0x123abc',
            'block_number'     => 18500000,
            'timestamp'        => '2024-01-01T00:00:00Z',
        ];

        $event = new CollateralLocked(
            position_uuid: $positionUuid,
            account_uuid: $accountUuid,
            collateral_asset_code: $assetCode,
            amount: $amount,
            metadata: $metadata
        );

        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
        $this->assertEquals($assetCode, $event->collateral_asset_code);
        $this->assertEquals($amount, $event->amount);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_event_is_immutable(): void
    {
        $event = new CollateralLocked(
            position_uuid: 'pos-123',
            account_uuid: 'acc-456',
            collateral_asset_code: 'ETH',
            amount: 1000000
        );

        // Test that properties cannot be modified (readonly)
        $reflection = new ReflectionClass($event);
        $property = $reflection->getProperty('position_uuid');

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        $property->setValue($event, 'new-pos-123');
    }

    #[Test]
    public function test_event_serialization(): void
    {
        $positionUuid = 'pos-123';
        $accountUuid = 'acc-456';
        $assetCode = 'ETH';
        $amount = 1000000;
        $metadata = ['key' => 'value'];

        $event = new CollateralLocked(
            position_uuid: $positionUuid,
            account_uuid: $accountUuid,
            collateral_asset_code: $assetCode,
            amount: $amount,
            metadata: $metadata
        );

        // Test that event can be serialized (important for event sourcing)
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertEquals($positionUuid, $unserialized->position_uuid);
        $this->assertEquals($accountUuid, $unserialized->account_uuid);
        $this->assertEquals($assetCode, $unserialized->collateral_asset_code);
        $this->assertEquals($amount, $unserialized->amount);
        $this->assertEquals($metadata, $unserialized->metadata);
    }

    #[Test]
    public function test_event_with_large_amount(): void
    {
        $event = new CollateralLocked(
            position_uuid: 'pos-123',
            account_uuid: 'acc-456',
            collateral_asset_code: 'BTC',
            amount: PHP_INT_MAX
        );

        $this->assertEquals(PHP_INT_MAX, $event->amount);
    }

    #[Test]
    public function test_event_with_complex_metadata(): void
    {
        $metadata = [
            'nested' => [
                'data' => [
                    'key' => 'value',
                ],
            ],
            'array'   => [1, 2, 3],
            'boolean' => true,
            'null'    => null,
            'number'  => 123.45,
        ];

        $event = new CollateralLocked(
            position_uuid: 'pos-123',
            account_uuid: 'acc-456',
            collateral_asset_code: 'USDC',
            amount: 100000,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function test_event_stores_uuid_formats(): void
    {
        $positionUuid = 'b1f5c2e8-1234-5678-9abc-def012345678';
        $accountUuid = 'a2e6d3f9-8765-4321-fedc-ba0987654321';

        $event = new CollateralLocked(
            position_uuid: $positionUuid,
            account_uuid: $accountUuid,
            collateral_asset_code: 'ETH',
            amount: 500000
        );

        $this->assertEquals($positionUuid, $event->position_uuid);
        $this->assertEquals($accountUuid, $event->account_uuid);
    }

    #[Test]
    public function test_event_stores_various_asset_codes(): void
    {
        $assetCodes = ['BTC', 'ETH', 'USDC', 'USDT', 'EUR', 'USD', 'CUSTOM-TOKEN'];

        foreach ($assetCodes as $assetCode) {
            $event = new CollateralLocked(
                position_uuid: 'pos-123',
                account_uuid: 'acc-456',
                collateral_asset_code: $assetCode,
                amount: 1000
            );

            $this->assertEquals($assetCode, $event->collateral_asset_code);
        }
    }

    #[Test]
    public function test_event_with_zero_amount(): void
    {
        $event = new CollateralLocked(
            position_uuid: 'pos-123',
            account_uuid: 'acc-456',
            collateral_asset_code: 'ETH',
            amount: 0
        );

        $this->assertEquals(0, $event->amount);
    }

    #[Test]
    public function test_event_with_negative_amount(): void
    {
        // Event allows negative amounts (might be used for reversals)
        $event = new CollateralLocked(
            position_uuid: 'pos-123',
            account_uuid: 'acc-456',
            collateral_asset_code: 'ETH',
            amount: -1000
        );

        $this->assertEquals(-1000, $event->amount);
    }
}
