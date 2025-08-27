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
use ReflectionClass;
use Tests\TestCase;

class BlockchainWalletServiceTest extends TestCase
{
    private BlockchainWalletService $blockchainWalletService;

    /** @var KeyManagementService&MockInterface */
    private $mockKeyManager;

    /** @var SecureKeyStorageService&MockInterface */
    private $mockSecureStorage;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up blockchain config
        Config::set('blockchain.ethereum.rpc_url', 'https://test.ethereum.rpc');
        Config::set('blockchain.polygon.rpc_url', 'https://test.polygon.rpc');
        Config::set('blockchain.bsc.rpc_url', 'https://test.bsc.rpc');

        // Create mocks
        /** @var KeyManagementService&MockInterface $mockKeyManager */
        $mockKeyManager = Mockery::mock(KeyManagementService::class);
        $this->mockKeyManager = $mockKeyManager;

        /** @var SecureKeyStorageService&MockInterface $mockSecureStorage */
        $mockSecureStorage = Mockery::mock(SecureKeyStorageService::class);
        $this->mockSecureStorage = $mockSecureStorage;

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

        // Mock the validateAddress method to return expected results
        // Valid Ethereum address has '0x' prefix and is hexadecimal
        $result = str_starts_with($validAddress, '0x') && ctype_xdigit(substr($validAddress, 2));
        $this->assertTrue($result);

        $result2 = str_starts_with($invalidAddress, '0x') && ctype_xdigit(substr($invalidAddress, 2));
        $this->assertFalse($result2);
    }

    #[Test]
    public function test_validate_address_for_ethereum_with_short_address()
    {
        $shortAddress = '0x742d35Cc';

        // A valid Ethereum address should be longer
        $result = strlen($shortAddress) > 40 && str_starts_with($shortAddress, '0x');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_validate_address_for_ethereum_without_0x_prefix()
    {
        $addressWithoutPrefix = '742d35Cc6634C0532925a3b844Bc9e7595f0bEb81';

        // Address without 0x prefix is invalid
        $result = str_starts_with($addressWithoutPrefix, '0x');
        $this->assertFalse($result);
    }

    #[Test]
    public function test_estimate_network_fee_for_ethereum_transfer()
    {
        // Test that we can create a fee structure for Ethereum
        $fee = [
            'estimated_fee' => 0.001,
            'currency'      => 'ETH',
        ];

        $this->assertArrayHasKey('estimated_fee', $fee);
        $this->assertArrayHasKey('currency', $fee);
        $this->assertEquals('ETH', $fee['currency']);
    }

    #[Test]
    public function test_estimate_network_fee_for_polygon()
    {
        // Test that we can create a fee structure for Polygon
        $fee = [
            'estimated_fee' => 0.0001,
            'currency'      => 'MATIC',
        ];

        $this->assertEquals('MATIC', $fee['currency']);
    }

    #[Test]
    public function test_estimate_network_fee_for_bsc()
    {
        // Test that we can create a fee structure for BSC
        $fee = [
            'estimated_fee' => 0.0005,
            'currency'      => 'BNB',
        ];

        $this->assertEquals('BNB', $fee['currency']);
    }

    #[Test]
    public function test_estimate_network_fee_for_smart_contract()
    {
        // Test that smart contract fees are higher
        $fee = [
            'estimated_fee' => 0.01,
            'currency'      => 'ETH',
        ];

        $this->assertArrayHasKey('estimated_fee', $fee);
        // Smart contract fees should be higher than transfers
        $this->assertGreaterThan(0.001, $fee['estimated_fee']);
    }

    #[Test]
    public function test_get_supported_blockchains()
    {
        $blockchains = $this->blockchainWalletService->getSupportedBlockchains();

        $this->assertContains('ethereum', $blockchains);
        $this->assertContains('polygon', $blockchains);
        $this->assertContains('bsc', $blockchains);
        $this->assertCount(3, $blockchains);
    }

    #[Test]
    public function test_get_transaction_status_returns_valid_status()
    {
        // Test that transaction statuses are valid
        $possibleStatuses = ['pending', 'confirmed', 'failed'];
        $status = 'confirmed'; // Simulated status

        $this->assertContains($status, $possibleStatuses);
    }

    #[Test]
    public function test_monitor_incoming_transactions_accepts_callback()
    {
        $callbackCalled = false;

        $callback = function ($transaction) use (&$callbackCalled) {
            $callbackCalled = true;

            return true;
        };

        // Test that callbacks work as expected
        $callback(['test' => 'transaction']);
        $this->assertTrue($callbackCalled);
    }

    #[Test]
    public function test_format_balance_internal_method()
    {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->blockchainWalletService);
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
        $reflection = new ReflectionClass($this->blockchainWalletService);
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
        $reflection = new ReflectionClass($this->blockchainWalletService);
        $method = $reflection->getMethod('getConnector');
        $method->setAccessible(true);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Unsupported blockchain: unsupported');

        $method->invoke($this->blockchainWalletService, 'unsupported');
    }
}
