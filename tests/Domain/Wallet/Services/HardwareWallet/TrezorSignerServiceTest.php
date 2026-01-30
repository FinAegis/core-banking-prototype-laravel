<?php

declare(strict_types=1);

namespace Tests\Domain\Wallet\Services\HardwareWallet;

use App\Domain\Wallet\Services\HardwareWallet\TrezorSignerService;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TrezorSignerService.
 *
 * Tests Trezor hardware wallet signing operations including
 * transaction preparation, signature validation, and derivation paths.
 */
class TrezorSignerServiceTest extends TestCase
{
    private TrezorSignerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrezorSignerService();
    }

    #[Test]
    public function it_returns_trezor_type(): void
    {
        $this->assertEquals('hardware_trezor', $this->service->getType());
    }

    #[Test]
    public function it_returns_supported_chains(): void
    {
        $chains = $this->service->getSupportedChains();

        $this->assertContains('ethereum', $chains);
        $this->assertContains('polygon', $chains);
        $this->assertContains('bsc', $chains);
        $this->assertContains('bitcoin', $chains);
    }

    #[Test]
    public function it_supports_ethereum_chain(): void
    {
        $this->assertTrue($this->service->supportsChain('ethereum'));
    }

    #[Test]
    public function it_supports_polygon_chain(): void
    {
        $this->assertTrue($this->service->supportsChain('polygon'));
    }

    #[Test]
    public function it_supports_bsc_chain(): void
    {
        $this->assertTrue($this->service->supportsChain('bsc'));
    }

    #[Test]
    public function it_supports_bitcoin_chain(): void
    {
        $this->assertTrue($this->service->supportsChain('bitcoin'));
    }

    #[Test]
    public function it_does_not_support_unknown_chain(): void
    {
        $this->assertFalse($this->service->supportsChain('unknown_chain'));
    }

    #[Test]
    public function it_returns_correct_derivation_path_for_ethereum(): void
    {
        $path = $this->service->getDerivationPath('ethereum');
        $this->assertEquals("m/44'/60'/0'/0/0", $path);
    }

    #[Test]
    public function it_returns_correct_derivation_path_for_bitcoin(): void
    {
        $path = $this->service->getDerivationPath('bitcoin');
        $this->assertEquals("m/44'/0'/0'/0/0", $path);
    }

    #[Test]
    public function it_returns_correct_derivation_path_with_account_index(): void
    {
        $path = $this->service->getDerivationPath('ethereum', 3);
        $this->assertEquals("m/44'/60'/0'/0/3", $path);
    }

    #[Test]
    public function it_prepares_ethereum_transaction_for_signing(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'ethereum',
            data: null,
            gasLimit: '21000',
            gasPrice: '50000000000',
            nonce: 5
        );

        $prepared = $this->service->prepareForSigning($transaction);

        $this->assertArrayHasKey('raw_data', $prepared);
        $this->assertArrayHasKey('display_data', $prepared);
        $this->assertArrayHasKey('encoding', $prepared);

        // Check display_data has chain_id
        $this->assertArrayHasKey('chain_id', $prepared['display_data']);
        $this->assertEquals(1, $prepared['display_data']['chain_id']);
    }

    #[Test]
    public function it_prepares_polygon_transaction_with_correct_chain_id(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'polygon',
            gasLimit: '21000',
            gasPrice: '30000000000',
            nonce: 0
        );

        $prepared = $this->service->prepareForSigning($transaction);

        $this->assertEquals(137, $prepared['display_data']['chain_id']);
    }

    #[Test]
    public function it_prepares_bsc_transaction_with_correct_chain_id(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'bsc',
            gasLimit: '21000',
            gasPrice: '5000000000',
            nonce: 0
        );

        $prepared = $this->service->prepareForSigning($transaction);

        $this->assertEquals(56, $prepared['display_data']['chain_id']);
    }

    #[Test]
    public function it_prepares_bitcoin_transaction(): void
    {
        $transaction = new TransactionData(
            from: 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
            to: 'bc1q7cyrfmck2ffu2ud3rn5l5a8yv6f0chkp0zpemf',
            value: '100000',
            chain: 'bitcoin',
            nonce: null
        );

        $prepared = $this->service->prepareForSigning($transaction);

        $this->assertArrayHasKey('raw_data', $prepared);
        $this->assertArrayHasKey('display_data', $prepared);
        $this->assertArrayHasKey('encoding', $prepared);
    }

    #[Test]
    public function it_constructs_signed_evm_transaction(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'ethereum',
            gasLimit: '21000',
            gasPrice: '50000000000',
            nonce: 5
        );

        $signature = '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b';
        $publicKey = '0x04' . str_repeat('ef', 64);

        $signed = $this->service->constructSignedTransaction($transaction, $signature, $publicKey);

        $this->assertInstanceOf(SignedTransaction::class, $signed);
        $this->assertNotEmpty($signed->rawTransaction);
        $this->assertNotEmpty($signed->hash);
    }

    #[Test]
    public function it_rejects_cryptographically_invalid_signature(): void
    {
        // This test verifies that the cryptographic validation works correctly.
        // A random signature will not recover to the expected public key,
        // so validateSignature should return false - this is correct behavior.
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'ethereum',
            gasLimit: '21000',
            gasPrice: '50000000000',
            nonce: 5
        );

        // Well-formatted but cryptographically invalid signature (random data)
        $signature = '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b';
        $publicKey = '0x04' . str_repeat('ef', 64);

        $isValid = $this->service->validateSignature($transaction, $signature, $publicKey);

        // The signature won't recover to the expected public key, so validation fails
        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_rejects_empty_signature(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'ethereum',
            gasLimit: '21000',
            gasPrice: '50000000000',
            nonce: 5
        );

        $isValid = $this->service->validateSignature($transaction, '', 'pubkey');

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_rejects_empty_public_key(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'ethereum',
            gasLimit: '21000',
            gasPrice: '50000000000',
            nonce: 5
        );

        $isValid = $this->service->validateSignature($transaction, '0xsignature', '');

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_returns_confirmation_steps(): void
    {
        $result = $this->service->getConfirmationSteps();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('steps', $result);
        $this->assertArrayHasKey('estimated_time_seconds', $result);
        $this->assertArrayHasKey('requires_physical_confirmation', $result);

        $steps = $result['steps'];
        $this->assertArrayHasKey('connect', $steps);
        $this->assertArrayHasKey('unlock', $steps);
        $this->assertArrayHasKey('confirm', $steps);
        $this->assertTrue($result['requires_physical_confirmation']);
    }

    #[Test]
    public function it_handles_transaction_with_data(): void
    {
        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '0',
            chain: 'ethereum',
            data: '0xa9059cbb000000000000000000000000abcdef1234567890000000000000000001',
            gasLimit: '100000',
            gasPrice: '50000000000',
            nonce: 10
        );

        $prepared = $this->service->prepareForSigning($transaction);

        $this->assertArrayHasKey('raw_data', $prepared);
        $this->assertArrayHasKey('display_data', $prepared);
    }
}
