<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Wallet\Services;

use App\Domain\Wallet\Contracts\WalletConnectorInterface;
use App\Domain\Wallet\Exceptions\WalletException;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Domain\Wallet\Services\SecureKeyStorageService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockchainWalletServiceTest extends TestCase
{
    private BlockchainWalletService $blockchainWalletService;

    private KeyManagementService|MockInterface $mockKeyManager;

    private SecureKeyStorageService|MockInterface $mockSecureStorage;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up blockchain config
        Config::set('blockchain.ethereum.rpc_url', 'https://test.ethereum.rpc');
        Config::set('blockchain.polygon.rpc_url', 'https://test.polygon.rpc');
        Config::set('blockchain.bsc.rpc_url', 'https://test.bsc.rpc');

        // Create mocks
        $this->mockKeyManager = Mockery::mock(KeyManagementService::class);
        $this->mockSecureStorage = Mockery::mock(SecureKeyStorageService::class);

        // Create service with mocks
        $this->blockchainWalletService = new BlockchainWalletService(
            $this->mockKeyManager,
            $this->mockSecureStorage
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function test_service_implements_wallet_connector_interface()
    {
        $this->assertInstanceOf(WalletConnectorInterface::class, $this->blockchainWalletService);
    }

    #[Test]
    public function test_validate_address_for_ethereum()
    {
        $validAddress = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb81';
        $invalidAddress = 'invalid-address';
        $blockchain = 'ethereum';

        $isValid = $this->blockchainWalletService->validateAddress($blockchain, $validAddress);
        $this->assertTrue($isValid);

        $isInvalid = $this->blockchainWalletService->validateAddress($blockchain, $invalidAddress);
        $this->assertFalse($isInvalid);
    }

    #[Test]
    public function test_validate_address_for_ethereum_with_short_address()
    {
        $shortAddress = '0x742d35Cc';
        $blockchain = 'ethereum';

        $isInvalid = $this->blockchainWalletService->validateAddress($blockchain, $shortAddress);
        $this->assertFalse($isInvalid);
    }

    #[Test]
    public function test_validate_address_for_ethereum_without_0x_prefix()
    {
        $addressWithoutPrefix = '742d35Cc6634C0532925a3b844Bc9e7595f0bEb81';
        $blockchain = 'ethereum';

        $isInvalid = $this->blockchainWalletService->validateAddress($blockchain, $addressWithoutPrefix);
        $this->assertFalse($isInvalid);
    }

    #[Test]
    public function test_estimate_network_fee_for_ethereum_transfer()
    {
        $blockchain = 'ethereum';
        $transactionType = 'transfer';
        $options = ['priority' => 'fast'];

        $fee = $this->blockchainWalletService->estimateNetworkFee($blockchain, $transactionType, $options);

        $this->assertIsArray($fee);
        $this->assertArrayHasKey('estimated_fee', $fee);
        $this->assertArrayHasKey('currency', $fee);
        $this->assertArrayHasKey('priority', $fee);
        $this->assertIsNumeric($fee['estimated_fee']);
        $this->assertEquals('ETH', $fee['currency']);
        $this->assertEquals('fast', $fee['priority']);
    }

    #[Test]
    public function test_estimate_network_fee_for_polygon()
    {
        $blockchain = 'polygon';
        $transactionType = 'transfer';
        $options = ['priority' => 'standard'];

        $fee = $this->blockchainWalletService->estimateNetworkFee($blockchain, $transactionType, $options);

        $this->assertIsArray($fee);
        $this->assertEquals('MATIC', $fee['currency']);
        $this->assertEquals('standard', $fee['priority']);
    }

    #[Test]
    public function test_estimate_network_fee_for_bsc()
    {
        $blockchain = 'bsc';
        $transactionType = 'transfer';
        $options = ['priority' => 'slow'];

        $fee = $this->blockchainWalletService->estimateNetworkFee($blockchain, $transactionType, $options);

        $this->assertIsArray($fee);
        $this->assertEquals('BNB', $fee['currency']);
        $this->assertEquals('slow', $fee['priority']);
    }

    #[Test]
    public function test_estimate_network_fee_for_smart_contract()
    {
        $blockchain = 'ethereum';
        $transactionType = 'smart_contract';
        $options = [];

        $fee = $this->blockchainWalletService->estimateNetworkFee($blockchain, $transactionType, $options);

        $this->assertIsArray($fee);
        $this->assertArrayHasKey('estimated_fee', $fee);
        // Smart contract fees should be higher than transfers
        $this->assertGreaterThan(0.001, $fee['estimated_fee']);
    }

    #[Test]
    public function test_get_supported_blockchains()
    {
        $blockchains = $this->blockchainWalletService->getSupportedBlockchains();

        $this->assertIsArray($blockchains);
        $this->assertContains('ethereum', $blockchains);
        $this->assertContains('polygon', $blockchains);
        $this->assertContains('bsc', $blockchains);
        $this->assertCount(3, $blockchains);
    }

    #[Test]
    public function test_get_transaction_status_returns_valid_status()
    {
        $transactionHash = '0xabc123def456789';
        $blockchain = 'ethereum';

        $status = $this->blockchainWalletService->getTransactionStatus($transactionHash, $blockchain);

        $this->assertIsString($status);
        $this->assertContains($status, ['pending', 'confirmed', 'failed']);
    }

    #[Test]
    public function test_monitor_incoming_transactions_accepts_callback()
    {
        $walletId = 'wallet-123';
        $blockchain = 'ethereum';
        $callbackCalled = false;

        $callback = function ($transaction) use (&$callbackCalled) {
            $callbackCalled = true;

            return true;
        };

        // This method sets up monitoring but doesn't immediately call the callback
        $this->blockchainWalletService->monitorIncomingTransactions($walletId, $blockchain, $callback);

        // The method should accept the callback without errors
        $this->assertTrue(true);
    }

    #[Test]
    public function test_format_balance_internal_method()
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->blockchainWalletService);
        $method = $reflection->getMethod('formatBalance');
        $method->setAccessible(true);

        // Test ETH formatting
        $formatted = $method->invoke($this->blockchainWalletService, '1000000000000000000', 'ethereum');
        $this->assertEquals(1.0, $formatted);

        // Test MATIC formatting
        $formatted = $method->invoke($this->blockchainWalletService, '2000000000000000000', 'polygon');
        $this->assertEquals(2.0, $formatted);

        // Test BNB formatting
        $formatted = $method->invoke($this->blockchainWalletService, '500000000000000000', 'bsc');
        $this->assertEquals(0.5, $formatted);
    }

    #[Test]
    public function test_get_connector_returns_correct_instance()
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->blockchainWalletService);
        $method = $reflection->getMethod('getConnector');
        $method->setAccessible(true);

        $ethereumConnector = $method->invoke($this->blockchainWalletService, 'ethereum');
        $this->assertNotNull($ethereumConnector);

        $polygonConnector = $method->invoke($this->blockchainWalletService, 'polygon');
        $this->assertNotNull($polygonConnector);

        $bscConnector = $method->invoke($this->blockchainWalletService, 'bsc');
        $this->assertNotNull($bscConnector);
    }

    #[Test]
    public function test_get_connector_throws_exception_for_unsupported_blockchain()
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->blockchainWalletService);
        $method = $reflection->getMethod('getConnector');
        $method->setAccessible(true);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Unsupported blockchain: unsupported');

        $method->invoke($this->blockchainWalletService, 'unsupported');
    }
}
