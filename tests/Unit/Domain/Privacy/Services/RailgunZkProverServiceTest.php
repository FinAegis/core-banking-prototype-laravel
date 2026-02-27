<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Services\RailgunBridgeClient;
use App\Domain\Privacy\Services\RailgunZkProverService;
use App\Domain\Privacy\ValueObjects\ZkProof;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RailgunZkProverServiceTest extends TestCase
{
    /** @var RailgunBridgeClient&MockInterface */
    private RailgunBridgeClient $bridge;

    private RailgunZkProverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $this->bridge = $bridge;
        $this->service = new RailgunZkProverService($this->bridge);
    }

    public function test_get_provider_name(): void
    {
        $this->assertEquals('railgun', $this->service->getProviderName());
    }

    public function test_supports_proof_type_for_shield(): void
    {
        $this->assertTrue($this->service->supportsProofType(ProofType::SANCTIONS_CLEAR));
    }

    public function test_supports_proof_type_for_unshield(): void
    {
        $this->assertTrue($this->service->supportsProofType(ProofType::KYC_TIER));
    }

    public function test_supports_proof_type_for_transfer(): void
    {
        $this->assertTrue($this->service->supportsProofType(ProofType::AGE_VERIFICATION));
    }

    public function test_does_not_support_custom_proof_type(): void
    {
        $this->assertFalse($this->service->supportsProofType(ProofType::CUSTOM));
    }

    public function test_generate_shield_proof(): void
    {
        $this->bridge
            ->shouldReceive('shield')
            ->with('wallet-1', '0xtoken', '100', 'polygon')
            ->once()
            ->andReturn([
                'transaction'  => ['to' => '0xabc', 'data' => '0x123', 'value' => '0'],
                'gas_estimate' => '150000',
                'nullifiers'   => [],
                'network'      => 'polygon',
            ]);

        $proof = $this->service->generateProof(
            ProofType::SANCTIONS_CLEAR,
            ['wallet_id'     => 'wallet-1'],
            ['token_address' => '0xtoken', 'amount' => '100', 'network' => 'polygon'],
        );

        $this->assertInstanceOf(ZkProof::class, $proof);
        $this->assertEquals(ProofType::SANCTIONS_CLEAR, $proof->type);
        $this->assertFalse($proof->isExpired());
        $this->assertEquals('railgun', $proof->metadata['provider']);
        $this->assertEquals('shield', $proof->metadata['endpoint']);
    }

    public function test_generate_unshield_proof(): void
    {
        $this->bridge
            ->shouldReceive('unshield')
            ->once()
            ->andReturn([
                'transaction' => ['to' => '0xabc', 'data' => '0x456', 'value' => '0'],
                'nullifiers'  => ['0xnull1'],
                'network'     => 'polygon',
            ]);

        $proof = $this->service->generateProof(
            ProofType::KYC_TIER,
            ['wallet_id' => 'wallet-1', 'encryption_key' => 'enc-key'],
            ['recipient' => '0xrecipient', 'token_address' => '0xtoken', 'amount' => '50', 'network' => 'polygon'],
        );

        $this->assertInstanceOf(ZkProof::class, $proof);
        $this->assertEquals('unshield', $proof->metadata['endpoint']);
    }

    public function test_generate_transfer_proof(): void
    {
        $this->bridge
            ->shouldReceive('privateTransfer')
            ->once()
            ->andReturn([
                'transaction' => ['to' => '0xabc', 'data' => '0x789', 'value' => '0'],
                'nullifiers'  => ['0xnull2'],
                'network'     => 'polygon',
            ]);

        $proof = $this->service->generateProof(
            ProofType::AGE_VERIFICATION,
            ['wallet_id'                 => 'wallet-1', 'encryption_key' => 'enc-key'],
            ['recipient_railgun_address' => '0zk_recipient', 'token_address' => '0xtoken', 'amount' => '25', 'network' => 'polygon'],
        );

        $this->assertInstanceOf(ZkProof::class, $proof);
        $this->assertEquals('transfer', $proof->metadata['endpoint']);
    }

    public function test_generate_proof_throws_for_unsupported_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be mapped to a RAILGUN circuit');

        $this->service->generateProof(
            ProofType::CUSTOM,
            [],
            [],
        );
    }

    public function test_verify_proof_with_valid_railgun_proof(): void
    {
        $proofData = base64_encode(json_encode([
            'transaction' => ['to' => '0xabc', 'data' => '0x123'],
        ], JSON_THROW_ON_ERROR));

        $proof = new ZkProof(
            type: ProofType::SANCTIONS_CLEAR,
            proof: $proofData,
            publicInputs: ['network' => 'polygon'],
            verifierAddress: '0x' . str_repeat('0', 40),
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+90 days'),
            metadata: ['provider' => 'railgun', 'endpoint' => 'shield'],
        );

        $this->assertTrue($this->service->verifyProof($proof));
    }

    public function test_verify_proof_returns_false_for_expired(): void
    {
        $proofData = base64_encode(json_encode([
            'transaction' => ['to' => '0xabc', 'data' => '0x123'],
        ], JSON_THROW_ON_ERROR));

        $proof = new ZkProof(
            type: ProofType::SANCTIONS_CLEAR,
            proof: $proofData,
            publicInputs: [],
            verifierAddress: '0x' . str_repeat('0', 40),
            createdAt: new DateTimeImmutable('-100 days'),
            expiresAt: new DateTimeImmutable('-10 days'),
            metadata: ['provider' => 'railgun'],
        );

        $this->assertFalse($this->service->verifyProof($proof));
    }

    public function test_verify_proof_returns_false_for_non_railgun(): void
    {
        $proof = new ZkProof(
            type: ProofType::SANCTIONS_CLEAR,
            proof: base64_encode('{}'),
            publicInputs: [],
            verifierAddress: '0x' . str_repeat('0', 40),
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+90 days'),
            metadata: ['provider' => 'snarkjs'],
        );

        $this->assertFalse($this->service->verifyProof($proof));
    }
}
