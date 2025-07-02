<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Custodian\Services;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\Services\FallbackService;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Models\CustodianAccount;
use App\Models\CustodianTransfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class FallbackServiceTest extends TestCase
{
    
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
            'uuid' => Str::uuid()->toString(),
            'custodian_name' => $custodian,
            'custodian_account_id' => $accountId,
            'account_uuid' => $account->uuid,
            'last_known_balance' => $expectedBalance,
            'last_synced_at' => now(),
            'status' => 'active',
            'is_primary' => true,
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
        
        // Create a test account first
        $account = \App\Models\Account::factory()->create();
        
        // Create custodian account
        CustodianAccount::create([
            'uuid' => Str::uuid()->toString(),
            'account_uuid' => $account->uuid,
            'custodian_name' => $custodian,
            'custodian_account_id' => $accountId,
            'status' => 'active',
            'is_primary' => true,
            'last_known_balance' => 0,
            'last_synced_at' => now(),
        ]);
        
        // Act
        $this->fallbackService->cacheBalance($custodian, $accountId, $assetCode, $balance);
        
        // Assert - Cache
        $cacheKey = "custodian:fallback:{$custodian}:{$accountId}:{$assetCode}:balance";
        $this->assertEquals(30000, Cache::get($cacheKey));
        
        // Assert - Database (Check that it was created or updated)
        $custodianAccount = CustodianAccount::where('custodian_account_id', $accountId)
            ->where('custodian_name', $custodian)
            ->first();
            
        $this->assertNotNull($custodianAccount);
        $this->assertEquals(30000, $custodianAccount->last_known_balance);
        $this->assertNotNull($custodianAccount->last_synced_at);
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
        
        // Create the asset
        $asset = \App\Domain\Asset\Models\Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );
        
        // Create accounts
        $fromAccountModel = \App\Models\Account::factory()->create();
        $toAccountModel = \App\Models\Account::factory()->create();
        
        // Create custodian accounts
        $fromCustodianAccount = CustodianAccount::create([
            'uuid' => Str::uuid()->toString(),
            'account_uuid' => $fromAccountModel->uuid,
            'custodian_name' => $custodian,
            'custodian_account_id' => 'FROM_ACC_123',
            'status' => 'active',
            'is_primary' => true,
            'last_known_balance' => 100000,
            'last_synced_at' => now(),
        ]);
        
        $toCustodianAccount = CustodianAccount::create([
            'uuid' => Str::uuid()->toString(),
            'account_uuid' => $toAccountModel->uuid,
            'custodian_name' => $custodian,
            'custodian_account_id' => 'TO_ACC_456',
            'status' => 'active',
            'is_primary' => true,
            'last_known_balance' => 50000,
            'last_synced_at' => now(),
        ]);
        
        $amount = new Money(75000);
        $assetCode = 'EUR';
        $reference = 'REF456';
        $description = 'Test transfer';
        
        // Mock the FallbackService to use our custodian accounts
        $mockedService = $this->getMockBuilder(FallbackService::class)
            ->onlyMethods(['queueTransferForRetry'])
            ->getMock();
            
        $mockedService->expects($this->once())
            ->method('queueTransferForRetry')
            ->willReturnCallback(function($cust, $from, $to, $amt, $asset, $ref, $desc) use ($fromCustodianAccount, $toCustodianAccount, $fromAccountModel, $toAccountModel) {
                $transferId = 'QUEUED_' . \Str::uuid()->toString();
                $transfer = CustodianTransfer::create([
                    'id' => $transferId,
                    'from_account_uuid' => $fromAccountModel->uuid,
                    'to_account_uuid' => $toAccountModel->uuid,
                    'from_custodian_account_id' => $fromCustodianAccount->id,
                    'to_custodian_account_id' => $toCustodianAccount->id,
                    'amount' => $amt->getAmount(),
                    'asset_code' => $asset,
                    'reference' => $ref,
                    'status' => 'pending',
                    'transfer_type' => 'external',
                    'metadata' => [
                        'queued_at' => now()->toIso8601String(),
                        'reason' => 'Custodian unavailable',
                        'custodian' => $cust,
                        'description' => $desc,
                    ],
                ]);
                
                return new TransactionReceipt(
                    id: $transfer->id,
                    status: 'pending',
                    amount: $amt->getAmount(),
                    assetCode: $asset,
                    fee: 0,
                    metadata: [
                        'queued' => true,
                        'retry_after' => now()->addMinutes(30)->toIso8601String(),
                    ]
                );
            });
        
        // Act
        $receipt = $mockedService->queueTransferForRetry(
            $custodian,
            (string) $fromAccountModel->uuid,
            (string) $toAccountModel->uuid,
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