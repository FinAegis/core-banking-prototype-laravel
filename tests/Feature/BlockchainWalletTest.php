<?php

namespace Tests\Feature;

use App\Domain\Wallet\Aggregates\BlockchainWallet;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Domain\Wallet\Exceptions\WalletException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockchainWalletTest extends TestCase
{
    use RefreshDatabase;
    
    protected BlockchainWalletService $walletService;
    protected KeyManagementService $keyManager;
    protected User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->keyManager = new KeyManagementService();
        $this->walletService = new BlockchainWalletService($this->keyManager);
        $this->user = User::factory()->create();
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_custodial_wallet()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial',
            settings: ['daily_limit' => '1000']
        );
        
        $this->assertInstanceOf(BlockchainWallet::class, $wallet);
        $this->assertEquals('custodial', $wallet->getType());
        $this->assertEquals('active', $wallet->getStatus());
        $this->assertEquals($this->user->id, $wallet->getUserId());
        
        // Check database projection
        $this->assertDatabaseHas('blockchain_wallets', [
            'wallet_id' => $wallet->getWalletId(),
            'user_id' => $this->user->id,
            'type' => 'custodial',
            'status' => 'active',
        ]);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_non_custodial_wallet()
    {
        $mnemonic = $this->keyManager->generateMnemonic();
        
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'non-custodial',
            mnemonic: $mnemonic
        );
        
        $this->assertEquals('non-custodial', $wallet->getType());
        
        // Check that seed is stored encrypted
        $this->assertDatabaseHas('wallet_seeds', [
            'wallet_id' => $wallet->getWalletId(),
        ]);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_initial_addresses_on_wallet_creation()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial'
        );
        
        // Check that addresses were generated for major chains
        $this->assertDatabaseHas('wallet_addresses', [
            'wallet_id' => $wallet->getWalletId(),
            'chain' => 'ethereum',
        ]);
        
        $this->assertDatabaseHas('wallet_addresses', [
            'wallet_id' => $wallet->getWalletId(),
            'chain' => 'polygon',
        ]);
        
        $this->assertDatabaseHas('wallet_addresses', [
            'wallet_id' => $wallet->getWalletId(),
            'chain' => 'bsc',
        ]);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_generate_additional_addresses()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial'
        );
        
        $address = $this->walletService->generateAddress(
            walletId: $wallet->getWalletId(),
            chain: 'ethereum',
            label: 'Trading Address'
        );
        
        $this->assertArrayHasKey('address', $address);
        $this->assertArrayHasKey('chain', $address);
        $this->assertEquals('ethereum', $address['chain']);
        $this->assertEquals('Trading Address', $address['label']);
        
        // Check database
        $this->assertDatabaseHas('wallet_addresses', [
            'wallet_id' => $wallet->getWalletId(),
            'chain' => 'ethereum',
            'label' => 'Trading Address',
        ]);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_wallet_settings()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial'
        );
        
        $updatedWallet = $this->walletService->updateSettings(
            walletId: $wallet->getWalletId(),
            settings: [
                'daily_limit' => '5000',
                'requires_2fa' => true,
            ]
        );
        
        $settings = $updatedWallet->getSettings();
        $this->assertEquals('5000', $settings['daily_limit']);
        $this->assertTrue($settings['requires_2fa']);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_freeze_and_unfreeze_wallet()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial'
        );
        
        // Freeze wallet
        $frozenWallet = $this->walletService->freezeWallet(
            walletId: $wallet->getWalletId(),
            reason: 'Suspicious activity',
            frozenBy: 'admin@finaegis.com'
        );
        
        $this->assertEquals('frozen', $frozenWallet->getStatus());
        
        $this->assertDatabaseHas('blockchain_wallets', [
            'wallet_id' => $wallet->getWalletId(),
            'status' => 'frozen',
        ]);
        
        // Try to generate address on frozen wallet
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Cannot generate address for inactive wallet');
        
        $this->walletService->generateAddress(
            walletId: $wallet->getWalletId(),
            chain: 'ethereum'
        );
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_unfreeze_wallet()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial'
        );
        
        // Freeze then unfreeze
        $this->walletService->freezeWallet(
            walletId: $wallet->getWalletId(),
            reason: 'Test freeze',
            frozenBy: 'admin@finaegis.com'
        );
        
        $unfrozenWallet = $this->walletService->unfreezeWallet(
            walletId: $wallet->getWalletId(),
            unfrozenBy: 'admin@finaegis.com'
        );
        
        $this->assertEquals('active', $unfrozenWallet->getStatus());
        
        // Should be able to generate address again
        $address = $this->walletService->generateAddress(
            walletId: $wallet->getWalletId(),
            chain: 'ethereum'
        );
        
        $this->assertNotEmpty($address);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_mnemonic_for_non_custodial_wallet()
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Invalid mnemonic phrase');
        
        $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'non-custodial',
            mnemonic: 'invalid mnemonic phrase'
        );
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_generate_valid_mnemonic()
    {
        $mnemonic = $this->keyManager->generateMnemonic();
        
        $this->assertTrue($this->keyManager->validateMnemonic($mnemonic));
        
        // Should be 12 words by default
        $words = explode(' ', $mnemonic);
        $this->assertCount(12, $words);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_wallet_backup()
    {
        $mnemonic = $this->keyManager->generateMnemonic();
        
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'non-custodial',
            mnemonic: $mnemonic
        );
        
        $walletAggregate = BlockchainWallet::retrieve($wallet->getWalletId());
        
        $backup = $this->keyManager->generateBackup(
            walletId: $wallet->getWalletId(),
            data: ['mnemonic' => $mnemonic]
        );
        
        $walletAggregate->createBackup(
            backupId: $backup['backup_id'],
            encryptedData: $backup['encrypted_data'],
            backupMethod: 'encrypted_json',
            createdBy: $this->user->email
        );
        
        $walletAggregate->persist();
        
        $this->assertDatabaseHas('wallet_backups', [
            'wallet_id' => $wallet->getWalletId(),
            'backup_id' => $backup['backup_id'],
            'backup_method' => 'encrypted_json',
        ]);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_transaction_history()
    {
        $wallet = $this->walletService->createWallet(
            userId: $this->user->id,
            type: 'custodial'
        );
        
        // Simulate some transactions
        DB::table('blockchain_transactions')->insert([
            'wallet_id' => $wallet->getWalletId(),
            'chain' => 'ethereum',
            'transaction_hash' => '0x123',
            'from_address' => '0xabc',
            'to_address' => '0xdef',
            'amount' => '1000000000000000000', // 1 ETH
            'status' => 'confirmed',
            'created_at' => now(),
        ]);
        
        $history = $this->walletService->getTransactionHistory($wallet->getWalletId());
        
        $this->assertCount(1, $history);
        $this->assertEquals('0x123', $history[0]->transaction_hash);
        $this->assertEquals('confirmed', $history[0]->status);
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_wallet_type()
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Invalid wallet type: invalid-type');
        
        BlockchainWallet::create(
            walletId: 'test_wallet',
            userId: $this->user->id,
            type: 'invalid-type'
        );
    }
    
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_encrypts_and_decrypts_private_keys()
    {
        $privateKey = bin2hex(random_bytes(32));
        $userId = (string) $this->user->id;
        
        $encrypted = $this->keyManager->encryptPrivateKey($privateKey, $userId);
        $decrypted = $this->keyManager->decryptPrivateKey($encrypted, $userId);
        
        $this->assertNotEquals($privateKey, $encrypted);
        $this->assertEquals($privateKey, $decrypted);
    }
}