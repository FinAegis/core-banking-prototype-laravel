<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Data;

use App\Domain\Stablecoin\Workflows\Data\ReserveWithdrawalData;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ReserveWithdrawalDataTest extends DomainTestCase
{
    #[Test]
    public function test_creates_reserve_withdrawal_data_with_required_fields(): void
    {
        $data = new ReserveWithdrawalData(
            poolId: 'pool-123',
            asset: 'USDC',
            amount: '500000000',
            custodianId: 'cust-456',
            destinationAddress: '0xabc123def456',
            reason: 'operational_expenses'
        );

        $this->assertEquals('pool-123', $data->poolId);
        $this->assertEquals('USDC', $data->asset);
        $this->assertEquals('500000000', $data->amount);
        $this->assertEquals('cust-456', $data->custodianId);
        $this->assertEquals('0xabc123def456', $data->destinationAddress);
        $this->assertEquals('operational_expenses', $data->reason);
        $this->assertEquals([], $data->metadata);
    }

    #[Test]
    public function test_creates_reserve_withdrawal_data_with_metadata(): void
    {
        $metadata = [
            'approval_id'   => 'appr-123',
            'authorized_by' => 'treasury',
            'invoice_id'    => 'INV-2024-001',
            'notes'         => 'Q1 2024 operating costs',
            'timestamp'     => '2024-01-15T10:00:00Z',
        ];

        $data = new ReserveWithdrawalData(
            poolId: 'pool-456',
            asset: 'DAI',
            amount: '250000000000',
            custodianId: 'cust-789',
            destinationAddress: '0xdef789ghi012',
            reason: 'vendor_payment',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $data->metadata);
        $this->assertEquals('appr-123', $data->metadata['approval_id']);
        $this->assertEquals('treasury', $data->metadata['authorized_by']);
    }

    #[Test]
    public function test_to_array_converts_data_correctly(): void
    {
        $data = new ReserveWithdrawalData(
            poolId: 'pool-001',
            asset: 'USDT',
            amount: '1000000000',
            custodianId: 'fireblocks-001',
            destinationAddress: '0x1234567890abcdef',
            reason: 'liquidity_provision',
            metadata: ['urgent' => true]
        );

        $array = $data->toArray();

        $this->assertEquals([
            'pool_id'             => 'pool-001',
            'asset'               => 'USDT',
            'amount'              => '1000000000',
            'custodian_id'        => 'fireblocks-001',
            'destination_address' => '0x1234567890abcdef',
            'reason'              => 'liquidity_provision',
            'metadata'            => ['urgent' => true],
        ], $array);
    }

    #[Test]
    public function test_handles_various_withdrawal_reasons(): void
    {
        $reasons = [
            'operational_expenses',
            'vendor_payment',
            'liquidity_provision',
            'emergency_withdrawal',
            'scheduled_disbursement',
            'regulatory_requirement',
            'audit_fee',
            'insurance_premium',
            'marketing_budget',
            'development_grant',
        ];

        foreach ($reasons as $reason) {
            $data = new ReserveWithdrawalData(
                poolId: 'pool-reason',
                asset: 'USDC',
                amount: '100000',
                custodianId: 'cust-reason',
                destinationAddress: '0xreason',
                reason: $reason
            );

            $this->assertEquals($reason, $data->reason);
        }
    }

    #[Test]
    public function test_handles_different_destination_address_formats(): void
    {
        $addresses = [
            '0x1234567890abcdef1234567890abcdef12345678', // Ethereum
            'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', // Bitcoin bech32
            '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', // Bitcoin legacy
            'cosmos1xxkueklal9vejv9unqu80w9vptyepfa95pd53u', // Cosmos
            'addr1qxxx...', // Cardano
            'tz1xxx...', // Tezos
        ];

        foreach ($addresses as $address) {
            $data = new ReserveWithdrawalData(
                poolId: 'pool-addr',
                asset: 'USDC',
                amount: '1000',
                custodianId: 'cust-addr',
                destinationAddress: $address,
                reason: 'test'
            );

            $this->assertEquals($address, $data->destinationAddress);
        }
    }

    #[Test]
    public function test_handles_large_withdrawal_amounts(): void
    {
        $largeAmount = '1000000000000000000000'; // 1000 tokens with 18 decimals

        $data = new ReserveWithdrawalData(
            poolId: 'pool-large',
            asset: 'DAI',
            amount: $largeAmount,
            custodianId: 'cust-large',
            destinationAddress: '0xlarge',
            reason: 'major_expense'
        );

        $this->assertEquals($largeAmount, $data->amount);
    }

    #[Test]
    public function test_metadata_handles_approval_workflow(): void
    {
        $metadata = [
            'approval_workflow' => [
                'required_approvals' => 3,
                'current_approvals'  => 2,
                'approvers'          => [
                    ['name' => 'Alice', 'approved_at' => '2024-01-01T10:00:00Z'],
                    ['name' => 'Bob', 'approved_at' => '2024-01-01T11:00:00Z'],
                ],
                'pending_approvers' => ['Charlie'],
            ],
            'threshold_amount' => '1000000000',
            'risk_score'       => 'medium',
        ];

        $data = new ReserveWithdrawalData(
            poolId: 'pool-approval',
            asset: 'USDC',
            amount: '5000000000',
            custodianId: 'cust-approval',
            destinationAddress: '0xapproval',
            reason: 'large_payment',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $data->metadata);
        $this->assertEquals(3, $data->metadata['approval_workflow']['required_approvals']);
        $this->assertCount(2, $data->metadata['approval_workflow']['approvers']);
    }

    #[Test]
    public function test_handles_different_custodian_types(): void
    {
        $custodians = [
            'self-custody',
            'bitgo-prod-001',
            'fireblocks-treasury',
            'gnosis-safe-0x123',
            'ledger-vault-enterprise',
            'coinbase-custody',
        ];

        foreach ($custodians as $custodian) {
            $data = new ReserveWithdrawalData(
                poolId: 'pool-custody',
                asset: 'USDT',
                amount: '1000000',
                custodianId: $custodian,
                destinationAddress: '0xcustody',
                reason: 'test'
            );

            $this->assertEquals($custodian, $data->custodianId);
        }
    }

    #[Test]
    public function test_metadata_can_include_compliance_info(): void
    {
        $metadata = [
            'compliance' => [
                'kyc_verified'        => true,
                'aml_check'           => 'passed',
                'sanctions_screening' => 'clear',
                'risk_rating'         => 'low',
            ],
            'regulatory' => [
                'jurisdiction'       => 'US',
                'reporting_required' => true,
                'form_8300_filed'    => false,
            ],
        ];

        $data = new ReserveWithdrawalData(
            poolId: 'pool-compliance',
            asset: 'USDC',
            amount: '15000000000',
            custodianId: 'cust-compliance',
            destinationAddress: '0xcompliance',
            reason: 'regulated_transfer',
            metadata: $metadata
        );

        $this->assertTrue($data->metadata['compliance']['kyc_verified']);
        $this->assertEquals('passed', $data->metadata['compliance']['aml_check']);
        $this->assertEquals('US', $data->metadata['regulatory']['jurisdiction']);
    }

    #[Test]
    public function test_immutability_of_properties(): void
    {
        $data = new ReserveWithdrawalData(
            poolId: 'pool-readonly',
            asset: 'USDC',
            amount: '1000',
            custodianId: 'cust-readonly',
            destinationAddress: '0xreadonly',
            reason: 'test'
        );

        // All properties should be readonly
        $reflection = new ReflectionClass($data);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly());
        }
    }

    #[Test]
    public function test_handles_zero_amount_withdrawal(): void
    {
        // Edge case: zero withdrawal (might be used for testing)
        $data = new ReserveWithdrawalData(
            poolId: 'pool-zero',
            asset: 'USDC',
            amount: '0',
            custodianId: 'cust-zero',
            destinationAddress: '0xzero',
            reason: 'test_transaction'
        );

        $this->assertEquals('0', $data->amount);
    }

    #[Test]
    public function test_metadata_preserves_order_and_types(): void
    {
        $metadata = [
            'string'  => 'value',
            'number'  => 123,
            'float'   => 45.67,
            'boolean' => true,
            'null'    => null,
            'array'   => [1, 2, 3],
            'object'  => ['key' => 'value'],
        ];

        $data = new ReserveWithdrawalData(
            poolId: 'pool-types',
            asset: 'DAI',
            amount: '1000',
            custodianId: 'cust-types',
            destinationAddress: '0xtypes',
            reason: 'type_test',
            metadata: $metadata
        );

        $this->assertSame('value', $data->metadata['string']);
        $this->assertSame(123, $data->metadata['number']);
        $this->assertSame(45.67, $data->metadata['float']);
        $this->assertSame(true, $data->metadata['boolean']);
        $this->assertNull($data->metadata['null']);
        $this->assertSame([1, 2, 3], $data->metadata['array']);
        $this->assertSame(['key' => 'value'], $data->metadata['object']);
    }
}
