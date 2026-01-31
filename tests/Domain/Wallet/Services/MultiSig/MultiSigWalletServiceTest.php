<?php

declare(strict_types=1);

namespace Tests\Domain\Wallet\Services\MultiSig;

use App\Domain\Wallet\Events\MultiSigWalletCreated;
use App\Domain\Wallet\Models\MultiSigWallet;
use App\Domain\Wallet\Models\MultiSigWalletSigner;
use App\Domain\Wallet\Services\MultiSigWalletService;
use App\Domain\Wallet\ValueObjects\MultiSigConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MultiSigWalletServiceTest extends TestCase
{
    protected MultiSigWalletService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MultiSigWalletService();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_creates_a_multi_sig_wallet(): void
    {
        Event::fake([MultiSigWalletCreated::class]);

        $config = MultiSigConfiguration::create(
            requiredSignatures: 2,
            totalSigners: 3,
            chain: 'ethereum',
            name: 'Test Multi-Sig Wallet',
        );

        $wallet = $this->service->createWallet($this->user, $config);

        $this->assertInstanceOf(MultiSigWallet::class, $wallet);
        $this->assertEquals('Test Multi-Sig Wallet', $wallet->name);
        $this->assertEquals('ethereum', $wallet->chain);
        $this->assertEquals(2, $wallet->required_signatures);
        $this->assertEquals(3, $wallet->total_signers);
        $this->assertEquals(MultiSigWallet::STATUS_AWAITING_SIGNERS, $wallet->status);
        $this->assertEquals($this->user->id, $wallet->user_id);

        Event::assertDispatched(MultiSigWalletCreated::class, function ($event) use ($wallet) {
            return $event->walletId === $wallet->id
                && $event->userId === $this->user->id;
        });
    }

    #[Test]
    public function it_adds_signers_to_a_wallet(): void
    {
        $wallet = $this->createTestWallet();

        $signer = $this->service->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('a', 64),
            address: '0x1234567890123456789012345678901234567890',
            user: $this->user,
            label: 'First Signer',
        );

        $this->assertInstanceOf(MultiSigWalletSigner::class, $signer);
        $this->assertEquals($wallet->id, $signer->multi_sig_wallet_id);
        $this->assertEquals(MultiSigWalletSigner::TYPE_INTERNAL, $signer->signer_type);
        $this->assertEquals('First Signer', $signer->label);
        $this->assertEquals(1, $signer->signer_order);
        $this->assertTrue($signer->is_active);
    }

    #[Test]
    public function it_activates_wallet_when_all_signers_added(): void
    {
        $wallet = $this->createTestWallet(requiredSignatures: 2, totalSigners: 2);

        // Add first signer
        $this->service->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('a', 64),
            user: $this->user,
        );

        $wallet->refresh();
        $this->assertEquals(MultiSigWallet::STATUS_AWAITING_SIGNERS, $wallet->status);

        // Add second signer
        $user2 = User::factory()->create();
        $this->service->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('b', 64),
            user: $user2,
        );

        $wallet->refresh();
        $this->assertEquals(MultiSigWallet::STATUS_ACTIVE, $wallet->status);
        $this->assertNotNull($wallet->address);
    }

    #[Test]
    public function it_prevents_adding_more_signers_than_total(): void
    {
        $wallet = $this->createTestWallet(requiredSignatures: 2, totalSigners: 2);

        // Add two signers to fill the wallet
        $this->service->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('a', 64),
            user: $this->user,
        );

        $user2 = User::factory()->create();
        $this->service->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('b', 64),
            user: $user2,
        );

        $wallet->refresh();

        // Try to add a third signer
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallet already has all signers');

        $user3 = User::factory()->create();
        $this->service->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('c', 64),
            user: $user3,
        );
    }

    #[Test]
    public function it_retrieves_user_wallets(): void
    {
        // Create wallet owned by user
        $ownedWallet = $this->createTestWallet();

        // Create wallet where user is a signer
        $otherUser = User::factory()->create();
        $signerWallet = $this->createTestWallet(owner: $otherUser);

        // Add current user as signer
        $this->service->addSigner(
            wallet: $signerWallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('a', 64),
            user: $this->user,
        );

        $wallets = $this->service->getUserWallets($this->user);

        $this->assertCount(2, $wallets);
        $this->assertTrue($wallets->contains('id', $ownedWallet->id));
        $this->assertTrue($wallets->contains('id', $signerWallet->id));
    }

    #[Test]
    public function it_filters_wallets_by_chain(): void
    {
        $this->createTestWallet(chain: 'ethereum');
        $this->createTestWallet(chain: 'bitcoin');

        $ethereumWallets = $this->service->getUserWallets($this->user, 'ethereum');
        $bitcoinWallets = $this->service->getUserWallets($this->user, 'bitcoin');

        $this->assertCount(1, $ethereumWallets);
        $this->assertCount(1, $bitcoinWallets);
        $firstEthereum = $ethereumWallets->first();
        $firstBitcoin = $bitcoinWallets->first();
        $this->assertNotNull($firstEthereum);
        $this->assertNotNull($firstBitcoin);
        $this->assertEquals('ethereum', $firstEthereum->chain);
        $this->assertEquals('bitcoin', $firstBitcoin->chain);
    }

    #[Test]
    public function it_returns_supported_schemes(): void
    {
        $schemes = $this->service->getSupportedSchemes();

        $this->assertIsArray($schemes);
        $this->assertContains('2-of-3', $schemes);
        $this->assertContains('3-of-5', $schemes);
    }

    #[Test]
    public function it_validates_supported_chains(): void
    {
        $this->assertTrue($this->service->isSupportedChain('ethereum'));
        $this->assertTrue($this->service->isSupportedChain('bitcoin'));
        $this->assertFalse($this->service->isSupportedChain('unsupported_chain'));
    }

    #[Test]
    public function it_returns_configuration_limits(): void
    {
        $limits = $this->service->getConfigurationLimits();

        $this->assertArrayHasKey('max_signers', $limits);
        $this->assertArrayHasKey('min_signers', $limits);
        $this->assertArrayHasKey('approval_ttl_seconds', $limits);
        $this->assertGreaterThan(0, $limits['max_signers']);
        $this->assertGreaterThan(0, $limits['min_signers']);
    }

    #[Test]
    public function it_suspends_a_wallet(): void
    {
        $wallet = $this->createTestWallet();

        $this->service->suspendWallet($wallet);

        $wallet->refresh();
        $this->assertEquals(MultiSigWallet::STATUS_SUSPENDED, $wallet->status);
    }

    #[Test]
    public function it_archives_a_wallet(): void
    {
        $wallet = $this->createTestWallet();

        $this->service->archiveWallet($wallet);

        $wallet->refresh();
        $this->assertEquals(MultiSigWallet::STATUS_ARCHIVED, $wallet->status);
    }

    /**
     * Create a test multi-sig wallet.
     */
    private function createTestWallet(
        int $requiredSignatures = 2,
        int $totalSigners = 3,
        string $chain = 'ethereum',
        ?User $owner = null,
    ): MultiSigWallet {
        $config = MultiSigConfiguration::create(
            requiredSignatures: $requiredSignatures,
            totalSigners: $totalSigners,
            chain: $chain,
            name: 'Test Wallet',
        );

        return $this->service->createWallet($owner ?? $this->user, $config);
    }
}
