<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\Services\FallbackService;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Models\CustodianAccount;
use App\Models\CustodianTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FallbackServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private FallbackService $fallbackService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
        
        $this->fallbackService = new FallbackService();
    }
    
    public function test_get_fallback_balance_from_cache(): void
    {
        // Arrange
        $custodian = 'paysera';
        $accountId = 'ACC123';
        $assetCode = 'EUR';
        $expectedBalance = 10000; // €100.00
        
        // Put balance in cache
        $cacheKey = "custodian:fallback:{$custodian}:{$accountId}:{$assetCode}:balance";
        Cache::put($cacheKey, $expectedBalance, 300);
        
        // Act
        $balance = $this->fallbackService->getFallbackBalance($custodian, $accountId, $assetCode);
        
        // Assert
        $this->assertNotNull($balance);
        $this->assertInstanceOf(Money::class, $balance);
        $this->assertEquals($expectedBalance, $balance->getAmount());
    }
    
    public function test_get_fallback_balance_from_database(): void
    {
        // Arrange
        $custodian = 'paysera';
        $accountId = 'ACC123';
        $assetCode = 'EUR';
        $expectedBalance = 20000; // €200.00
        
        // Create a test account first
        $account = \App\Models\Account::factory()->create();
        
        CustodianAccount::create([
            'id' => \Str::uuid()->toString(),
            'custodian_name' => $custodian,
            'custodian_account_id' => $accountId,
            'account_uuid' => $account->uuid,
            'last_known_balance' => $expectedBalance,
            'last_synced_at' => now(),
        ]);
        
        // Act
        $balance = $this->fallbackService->getFallbackBalance($custodian, $accountId, $assetCode);
        
        // Assert
        $this->assertNotNull($balance);
        $this->assertInstanceOf(Money::class, $balance);
        $this->assertEquals($expectedBalance, $balance->getAmount());
    }
    
    public function test_get_fallback_balance_returns_null_when_no_data(): void
    {
        // Act
        $balance = $this->fallbackService->getFallbackBalance('paysera', 'ACC123', 'EUR');
        
        // Assert
        $this->assertNull($balance);
    }
    
    public function test_cache_balance_stores_in_cache_and_database(): void
    {
        // Arrange
        $custodian = 'paysera';
        $accountId = 'ACC123';
        $assetCode = 'EUR';
        $balance = new Money(30000); // €300.00
        
        // Act
        $this->fallbackService->cacheBalance($custodian, $accountId, $assetCode, $balance);
        
        // Assert - Cache
        $cacheKey = "custodian:fallback:{$custodian}:{$accountId}:{$assetCode}:balance";
        $this->assertEquals(30000, Cache::get($cacheKey));
        
        // Assert - Database
        $account = CustodianAccount::where('custodian_account_id', $accountId)
            ->where('custodian_name', $custodian)
            ->first();
            
        $this->assertNotNull($account);
        $this->assertEquals(30000, $account->last_known_balance);
        $this->assertNotNull($account->last_synced_at);
    }
    
    public function test_get_fallback_account_info_from_cache(): void
    {
        // Arrange
        $custodian = 'paysera';
        $accountId = 'ACC123';
        $accountInfo = new AccountInfo(
            accountId: $accountId,
            name: 'Test Account',
            status: 'active',
            balances: ['EUR' => 10000, 'USD' => 5000],
            currency: 'EUR',
            type: 'business',
            createdAt: now(),
            metadata: ['iban' => 'LT123456789012345678']
        );
        
        // Put in cache
        $cacheKey = "custodian:fallback:{$custodian}:{$accountId}:info";
        Cache::put($cacheKey, serialize($accountInfo), 3600);
        
        // Act
        $retrieved = $this->fallbackService->getFallbackAccountInfo($custodian, $accountId);
        
        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals($accountId, $retrieved->accountId);
        $this->assertEquals('Test Account', $retrieved->name);
        $this->assertEquals(['EUR' => 10000, 'USD' => 5000], $retrieved->balances);
    }
    
    public function test_cache_account_info(): void
    {
        // Arrange
        $custodian = 'paysera';
        $accountId = 'ACC123';
        $accountInfo = new AccountInfo(
            accountId: $accountId,
            name: 'Test Account',
            status: 'active',
            balances: ['EUR' => 20000],
            currency: 'EUR',
            type: 'business',
            createdAt: now(),
            metadata: []
        );
        
        // Act
        $this->fallbackService->cacheAccountInfo($custodian, $accountId, $accountInfo);
        
        // Assert
        $cacheKey = "custodian:fallback:{$custodian}:{$accountId}:info";
        $cached = unserialize(Cache::get($cacheKey));
        
        $this->assertNotNull($cached);
        $this->assertEquals($accountId, $cached->accountId);
        $this->assertEquals('Test Account', $cached->name);
    }
    
    public function test_get_fallback_transfer_status_from_database(): void
    {
        // Arrange
        $custodian = 'paysera';
        $transferId = 'TRF123';
        
        // Create test accounts and custodian accounts
        $fromAccount = \App\Models\Account::factory()->create();
        $toAccount = \App\Models\Account::factory()->create();
        
        $fromCustodianAccount = CustodianAccount::create([
            'id' => 1,
            'custodian_name' => $custodian,
            'custodian_account_id' => 'FROM123',
            'account_uuid' => $fromAccount->uuid,
        ]);
        
        $toCustodianAccount = CustodianAccount::create([
            'id' => 2,
            'custodian_name' => $custodian,
            'custodian_account_id' => 'TO123',
            'account_uuid' => $toAccount->uuid,
        ]);
        
        CustodianTransfer::create([
            'id' => $transferId,
            'from_account_uuid' => $fromAccount->uuid,
            'to_account_uuid' => $toAccount->uuid,
            'from_custodian_account_id' => $fromCustodianAccount->id,
            'to_custodian_account_id' => $toCustodianAccount->id,
            'amount' => 50000,
            'asset_code' => 'EUR',
            'status' => 'completed',
            'transfer_type' => 'internal',
            'reference' => 'REF123',
            'completed_at' => now(),
        ]);
        
        // Act
        $status = $this->fallbackService->getFallbackTransferStatus($custodian, $transferId);
        
        // Assert
        $this->assertNotNull($status);
        $this->assertInstanceOf(TransactionReceipt::class, $status);
        $this->assertEquals($transferId, $status->id);
        $this->assertEquals('completed', $status->status);
        $this->assertEquals(50000, $status->amount);
    }
    
    public function test_queue_transfer_for_retry(): void
    {
        // Arrange
        $custodian = 'paysera';
        $fromAccount = \Str::uuid()->toString();
        $toAccount = \Str::uuid()->toString();
        $amount = new Money(75000);
        $assetCode = 'EUR';
        $reference = 'REF456';
        $description = 'Test transfer';
        
        // Act
        $receipt = $this->fallbackService->queueTransferForRetry(
            $custodian,
            $fromAccount,
            $toAccount,
            $amount,
            $assetCode,
            $reference,
            $description
        );
        
        // Assert
        $this->assertEquals('pending', $receipt->status);
        $this->assertEquals(75000, $receipt->amount);
        $this->assertTrue($receipt->metadata['queued']);
        $this->assertArrayHasKey('retry_after', $receipt->metadata);
        
        // Verify database record
        $transfer = CustodianTransfer::where('reference', $reference)->first();
        $this->assertNotNull($transfer);
        $this->assertEquals('pending', $transfer->status);
        $this->assertEquals(75000, $transfer->amount);
    }
    
    public function test_get_alternative_custodian_with_available_alternative(): void
    {
        // Mock the registry
        $mockRegistry = $this->createMock(CustodianRegistry::class);
        
        // Mock connector that is available
        $mockConnector = $this->createMock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
        $mockConnector->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);
            
        $mockRegistry->expects($this->once())
            ->method('getConnector')
            ->with('deutsche_bank')
            ->willReturn($mockConnector);
            
        $this->app->instance(CustodianRegistry::class, $mockRegistry);
        
        // Act
        $alternative = $this->fallbackService->getAlternativeCustodian('paysera', 'EUR');
        
        // Assert
        $this->assertEquals('deutsche_bank', $alternative);
    }
    
    public function test_get_alternative_custodian_with_no_available_alternatives(): void
    {
        // Mock the registry
        $mockRegistry = $this->createMock(CustodianRegistry::class);
        
        // Mock connectors that are not available
        $mockConnector1 = $this->createMock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
        $mockConnector1->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);
            
        $mockConnector2 = $this->createMock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
        $mockConnector2->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);
            
        $mockRegistry->expects($this->exactly(2))
            ->method('getConnector')
            ->willReturnOnConsecutiveCalls($mockConnector1, $mockConnector2);
            
        $this->app->instance(CustodianRegistry::class, $mockRegistry);
        
        // Act
        $alternative = $this->fallbackService->getAlternativeCustodian('paysera', 'EUR');
        
        // Assert
        $this->assertNull($alternative);
    }
    
    public function test_cache_ttl_values(): void
    {
        // Verify that cache TTL constants are properly set
        $reflection = new \ReflectionClass(FallbackService::class);
        
        $this->assertEquals(300, $reflection->getConstant('BALANCE_CACHE_TTL'));
        $this->assertEquals(3600, $reflection->getConstant('ACCOUNT_INFO_CACHE_TTL'));
        $this->assertEquals(600, $reflection->getConstant('TRANSFER_STATUS_CACHE_TTL'));
    }
}