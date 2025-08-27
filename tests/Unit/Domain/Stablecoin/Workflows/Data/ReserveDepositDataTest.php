<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Data;

use App\Domain\Stablecoin\Workflows\Data\ReserveDepositData;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ReserveDepositDataTest extends DomainTestCase
{
    #[Test]
    public function test_creates_reserve_deposit_data_with_required_fields(): void
    {
        $data = new ReserveDepositData(
            poolId: 'pool-123',
            asset: 'USDC',
            amount: '1000000000000',
            custodianId: 'cust-456',
            transactionHash: '0xabc123def456',
            expectedAmount: '1000000000000'
        );

        $this->assertEquals('pool-123', $data->poolId);
        $this->assertEquals('USDC', $data->asset);
        $this->assertEquals('1000000000000', $data->amount);
        $this->assertEquals('cust-456', $data->custodianId);
        $this->assertEquals('0xabc123def456', $data->transactionHash);
        $this->assertEquals('1000000000000', $data->expectedAmount);
        $this->assertEquals([], $data->metadata);
    }

    #[Test]
    public function test_creates_reserve_deposit_data_with_metadata(): void
    {
        $metadata = [
            'source'    => 'protocol_fees',
            'batch_id'  => 'batch-789',
            'timestamp' => '2024-01-01T12:00:00Z',
            'notes'     => 'Monthly fee collection',
        ];

        $data = new ReserveDepositData(
            poolId: 'pool-456',
            asset: 'DAI',
            amount: '5000000000000000000000',
            custodianId: 'cust-789',
            transactionHash: '0xdef789ghi012',
            expectedAmount: '5000000000000000000000',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $data->metadata);
        $this->assertEquals('protocol_fees', $data->metadata['source']);
        $this->assertEquals('batch-789', $data->metadata['batch_id']);
    }

    #[Test]
    public function test_to_array_converts_data_correctly(): void
    {
        $data = new ReserveDepositData(
            poolId: 'pool-001',
            asset: 'USDT',
            amount: '2500000000',
            custodianId: 'bitgo-001',
            transactionHash: '0x1234567890abcdef',
            expectedAmount: '2500000000',
            metadata: ['verified' => true]
        );

        $array = $data->toArray();

        $this->assertEquals([
            'pool_id'          => 'pool-001',
            'asset'            => 'USDT',
            'amount'           => '2500000000',
            'custodian_id'     => 'bitgo-001',
            'transaction_hash' => '0x1234567890abcdef',
            'expected_amount'  => '2500000000',
            'metadata'         => ['verified' => true],
        ], $array);
    }

    #[Test]
    public function test_handles_different_asset_types(): void
    {
        $assets = ['USDC', 'USDT', 'DAI', 'FRAX', 'TUSD', 'BUSD'];

        foreach ($assets as $asset) {
            $data = new ReserveDepositData(
                poolId: 'pool-multi',
                asset: $asset,
                amount: '1000000000',
                custodianId: 'cust-multi',
                transactionHash: '0x' . md5($asset),
                expectedAmount: '1000000000'
            );

            $this->assertEquals($asset, $data->asset);
        }
    }

    #[Test]
    public function test_handles_large_amounts(): void
    {
        $largeAmount = '1000000000000000000000000'; // 1 million tokens with 18 decimals

        $data = new ReserveDepositData(
            poolId: 'pool-large',
            asset: 'DAI',
            amount: $largeAmount,
            custodianId: 'cust-large',
            transactionHash: '0xlarge',
            expectedAmount: $largeAmount
        );

        $this->assertEquals($largeAmount, $data->amount);
        $this->assertEquals($largeAmount, $data->expectedAmount);
    }

    #[Test]
    public function test_handles_amount_discrepancy(): void
    {
        // Sometimes expected amount differs from actual amount (fees, slippage)
        $data = new ReserveDepositData(
            poolId: 'pool-fee',
            asset: 'USDC',
            amount: '999000000',
            custodianId: 'cust-fee',
            transactionHash: '0xfee',
            expectedAmount: '1000000000'
        );

        $this->assertNotEquals($data->amount, $data->expectedAmount);
        $this->assertEquals('999000000', $data->amount);
        $this->assertEquals('1000000000', $data->expectedAmount);
    }

    #[Test]
    public function test_metadata_can_be_empty_array(): void
    {
        $data = new ReserveDepositData(
            poolId: 'pool-empty',
            asset: 'USDT',
            amount: '100',
            custodianId: 'cust-empty',
            transactionHash: '0xempty',
            expectedAmount: '100',
            metadata: []
        );

        $this->assertIsArray($data->metadata);
        $this->assertEmpty($data->metadata);
    }

    #[Test]
    public function test_metadata_handles_nested_structures(): void
    {
        $metadata = [
            'approval' => [
                'required'  => true,
                'approvers' => ['alice', 'bob'],
                'timestamp' => '2024-01-01T10:00:00Z',
            ],
            'audit' => [
                'trail' => [
                    ['action' => 'initiated', 'by' => 'system'],
                    ['action' => 'approved', 'by' => 'alice'],
                    ['action' => 'approved', 'by' => 'bob'],
                ],
            ],
            'tags' => ['monthly', 'recurring', 'automated'],
        ];

        $data = new ReserveDepositData(
            poolId: 'pool-nested',
            asset: 'FRAX',
            amount: '750000000000',
            custodianId: 'cust-nested',
            transactionHash: '0xnested',
            expectedAmount: '750000000000',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $data->metadata);
        $this->assertTrue($data->metadata['approval']['required']);
        $this->assertCount(3, $data->metadata['audit']['trail']);
        $this->assertContains('automated', $data->metadata['tags']);
    }

    #[Test]
    public function test_immutability_of_properties(): void
    {
        $data = new ReserveDepositData(
            poolId: 'pool-readonly',
            asset: 'USDC',
            amount: '1000',
            custodianId: 'cust-readonly',
            transactionHash: '0xreadonly',
            expectedAmount: '1000'
        );

        // All properties should be readonly
        $reflection = new ReflectionClass($data);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly());
        }
    }

    #[Test]
    public function test_handles_various_transaction_hash_formats(): void
    {
        $hashes = [
            '0x1234567890abcdef',
            'tx_abc123def456',
            'btc:1234567890abcdef',
            'eth:0xabc',
            '1234567890',
            'HASH-UPPER-CASE',
        ];

        foreach ($hashes as $hash) {
            $data = new ReserveDepositData(
                poolId: 'pool-hash',
                asset: 'USDC',
                amount: '1000',
                custodianId: 'cust-hash',
                transactionHash: $hash,
                expectedAmount: '1000'
            );

            $this->assertEquals($hash, $data->transactionHash);
        }
    }

    #[Test]
    public function test_custodian_id_variations(): void
    {
        $custodianIds = [
            'bitgo-001',
            'fireblocks-prod-123',
            'custody-provider-xyz',
            'self-custody',
            'multisig-3-of-5',
            'cold-storage-vault-1',
        ];

        foreach ($custodianIds as $custodianId) {
            $data = new ReserveDepositData(
                poolId: 'pool-custody',
                asset: 'DAI',
                amount: '5000',
                custodianId: $custodianId,
                transactionHash: '0xcustody',
                expectedAmount: '5000'
            );

            $this->assertEquals($custodianId, $data->custodianId);
        }
    }
}
