<?php

declare(strict_types=1);

namespace Tests\Domain\Wallet\Services\HardwareWallet;

use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\PendingSigningRequest;
use App\Domain\Wallet\Services\HardwareWallet\HardwareWalletManager;
use App\Domain\Wallet\Services\HardwareWallet\LedgerSignerService;
use App\Domain\Wallet\Services\HardwareWallet\TrezorSignerService;
use App\Domain\Wallet\ValueObjects\HardwareWalletDevice;
use App\Domain\Wallet\ValueObjects\PendingSigningRequest as PendingSigningRequestVO;
use App\Domain\Wallet\ValueObjects\TransactionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for HardwareWalletManager.
 *
 * Tests hardware wallet coordination including device registration,
 * signing request creation, and signature submission.
 */
class HardwareWalletManagerTest extends TestCase
{
    use RefreshDatabase;

    private HardwareWalletManager $manager;

    private LedgerSignerService $ledgerService;

    private TrezorSignerService $trezorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = new LedgerSignerService();
        $this->trezorService = new TrezorSignerService();
        $this->manager = new HardwareWalletManager(
            $this->ledgerService,
            $this->trezorService
        );
    }

    #[Test]
    public function it_registers_ledger_device(): void
    {
        $device = HardwareWalletDevice::create(
            type: HardwareWalletDevice::TYPE_LEDGER_NANO_X,
            deviceId: 'ledger_123456',
            label: 'My Ledger Nano X',
            firmwareVersion: '2.0.0',
            supportedChains: ['ethereum', 'polygon'],
            publicKey: '04' . str_repeat('ab', 64),
            address: '0x1234567890123456789012345678901234567890'
        );

        $association = $this->manager->registerDevice(
            userId: 1,
            device: $device,
            chain: 'ethereum',
            derivationPath: "44'/60'/0'/0/0"
        );

        $this->assertInstanceOf(HardwareWalletAssociation::class, $association);
        $this->assertEquals(1, $association->user_id);
        $this->assertEquals('ledger_nano_x', $association->device_type);
        $this->assertEquals('ethereum', $association->chain);
        $this->assertEquals('0x1234567890123456789012345678901234567890', $association->address);
        $this->assertTrue($association->is_active);
    }

    #[Test]
    public function it_registers_trezor_device(): void
    {
        $device = HardwareWalletDevice::create(
            type: HardwareWalletDevice::TYPE_TREZOR_MODEL_T,
            deviceId: 'trezor_789012',
            label: 'My Trezor Model T',
            firmwareVersion: '2.5.0',
            supportedChains: ['ethereum', 'bitcoin'],
            publicKey: '04' . str_repeat('cd', 64),
            address: '0x0987654321098765432109876543210987654321'
        );

        $association = $this->manager->registerDevice(
            userId: 2,
            device: $device,
            chain: 'ethereum',
            derivationPath: "m/44'/60'/0'/0/0"
        );

        $this->assertInstanceOf(HardwareWalletAssociation::class, $association);
        $this->assertEquals('trezor_model_t', $association->device_type);
    }

    #[Test]
    public function it_creates_signing_request(): void
    {
        $association = HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_123',
            'device_label'     => 'Test Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);

        $transaction = new TransactionData(
            from: '0x1234567890123456789012345678901234567890',
            to: '0x0987654321098765432109876543210987654321',
            value: '1000000000000000000',
            chain: 'ethereum',
            gasLimit: '21000',
            gasPrice: '50000000000',
            nonce: 5
        );

        $request = $this->manager->createSigningRequest($association, $transaction);

        $this->assertInstanceOf(PendingSigningRequest::class, $request);
        $this->assertEquals($association->id, $request->association_id);
        $this->assertEquals(PendingSigningRequestVO::STATUS_PENDING, $request->status);
        $this->assertNotEmpty($request->raw_data_to_sign);
    }

    #[Test]
    public function it_submits_signature_successfully(): void
    {
        // Create a mock Ledger service that accepts any signature (for testing flow)
        $mockLedgerService = $this->createMock(LedgerSignerService::class);
        $mockLedgerService->method('getType')->willReturn('hardware_ledger');
        $mockLedgerService->method('validateSignature')->willReturn(true);
        $mockLedgerService->method('constructSignedTransaction')
            ->willReturn(new \App\Domain\Wallet\ValueObjects\SignedTransaction(
                rawTransaction: '0xf86c058502540be40082520894' . str_repeat('0', 40) . '880de0b6b3a764000080',
                hash: '0x' . str_repeat('ab', 32),
                transactionData: new TransactionData(
                    from: '0x1234567890123456789012345678901234567890',
                    to: '0x0987654321098765432109876543210987654321',
                    value: '1000000000000000000',
                    chain: 'ethereum',
                    gasLimit: '21000',
                    gasPrice: '50000000000',
                    nonce: 5
                )
            ));

        $manager = new HardwareWalletManager(
            $mockLedgerService,
            $this->trezorService
        );

        $association = HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_123',
            'device_label'     => 'Test Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);

        $request = PendingSigningRequest::create([
            'user_id'          => 1,
            'association_id'   => $association->id,
            'transaction_data' => json_encode([
                'from'     => '0x1234567890123456789012345678901234567890',
                'to'       => '0x0987654321098765432109876543210987654321',
                'value'    => '1000000000000000000',
                'chain'    => 'ethereum',
                'gasLimit' => '21000',
                'gasPrice' => '50000000000',
                'nonce'    => 5,
            ]),
            'chain'            => 'ethereum',
            'raw_data_to_sign' => '0x' . str_repeat('ef', 100),
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $signature = '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b';
        $publicKey = '04' . str_repeat('ef', 64);

        $signedTx = $manager->submitSignature($request, $signature, $publicKey);

        $request->refresh();

        $this->assertEquals(PendingSigningRequestVO::STATUS_COMPLETED, $request->status);
        $this->assertEquals($signature, $request->signature);
        $this->assertNotEmpty($signedTx->hash);
    }

    #[Test]
    public function it_cancels_signing_request(): void
    {
        $association = HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_123',
            'device_label'     => 'Test Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);

        $request = PendingSigningRequest::create([
            'user_id'          => 1,
            'association_id'   => $association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'chain'            => 'ethereum',
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $this->manager->cancelSigningRequest($request);

        $request->refresh();
        $this->assertEquals(PendingSigningRequestVO::STATUS_CANCELLED, $request->status);
    }

    #[Test]
    public function it_cannot_cancel_completed_request(): void
    {
        $association = HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_123',
            'device_label'     => 'Test Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);

        $request = PendingSigningRequest::create([
            'user_id'          => 1,
            'association_id'   => $association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'chain'            => 'ethereum',
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_COMPLETED,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $this->manager->cancelSigningRequest($request);

        $request->refresh();
        $this->assertEquals(PendingSigningRequestVO::STATUS_COMPLETED, $request->status);
    }

    #[Test]
    public function it_gets_user_associations(): void
    {
        HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_123',
            'device_label'     => 'Ledger 1',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1111111111111111111111111111111111111111',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);

        HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'trezor_model_t',
            'device_id'        => 'trezor_456',
            'device_label'     => 'Trezor 1',
            'public_key'       => '04' . str_repeat('cd', 64),
            'address'          => '0x2222222222222222222222222222222222222222',
            'chain'            => 'polygon',
            'derivation_path'  => "m/44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon', 'bitcoin'],
            'is_active'        => true,
        ]);

        HardwareWalletAssociation::create([
            'user_id'          => 2,
            'device_type'      => 'ledger_nano_s',
            'device_id'        => 'ledger_789',
            'device_label'     => 'Other User Ledger',
            'public_key'       => '04' . str_repeat('ef', 64),
            'address'          => '0x3333333333333333333333333333333333333333',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum'],
            'is_active'        => true,
        ]);

        $associations = $this->manager->getUserAssociations(1);

        $this->assertCount(2, $associations);
    }

    #[Test]
    public function it_removes_association(): void
    {
        $association = HardwareWalletAssociation::create([
            'user_id'          => 1,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_123',
            'device_label'     => 'Test Ledger',
            'public_key'       => '04' . str_repeat('ab', 64),
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);

        $this->manager->removeAssociation($association);

        $association->refresh();
        $this->assertFalse($association->is_active);
    }

    #[Test]
    public function it_returns_supported_chains_for_device_type(): void
    {
        $chains = $this->manager->getSupportedChains('ledger_nano_x');

        $this->assertContains('ethereum', $chains);
        $this->assertContains('polygon', $chains);
        $this->assertContains('bitcoin', $chains);
    }

    #[Test]
    public function it_returns_empty_array_for_unknown_device_type(): void
    {
        $chains = $this->manager->getSupportedChains('unknown_device');

        $this->assertEmpty($chains);
    }
}
