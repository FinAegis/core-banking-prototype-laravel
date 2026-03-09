<?php

declare(strict_types=1);

namespace Tests\Feature\HardwareWallet;

use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\PendingSigningRequest;
use App\Domain\Wallet\Services\HardwareWallet\HardwareWalletManager;
use App\Domain\Wallet\ValueObjects\PendingSigningRequest as PendingSigningRequestVO;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for Hardware Wallet Signing.
 *
 * Tests the complete signing flow for hardware wallet transactions
 * via the API endpoints.
 */
class HardwareWalletSigningTest extends TestCase
{
    private HardwareWalletAssociation $association;

    private string $associationPublicKey;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Feature::define('sub_product.blockchain', true);
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $this->associationPublicKey = '04' . str_repeat('ab', 64);

        $this->association = HardwareWalletAssociation::create([
            'user_id'          => $this->user->id,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'ledger_' . time(),
            'device_label'     => 'Test Ledger',
            'public_key'       => $this->associationPublicKey,
            'address'          => '0x1234567890123456789012345678901234567890',
            'chain'            => 'ethereum',
            'derivation_path'  => "m/44'/60'/0'/0/0",
            'supported_chains' => ['ethereum', 'polygon'],
            'is_active'        => true,
        ]);
    }

    /**
     * Build a valid transaction payload for signing requests.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTransaction(array $overrides = []): array
    {
        return array_merge([
            'from'      => '0x1234567890123456789012345678901234567890',
            'to'        => '0x0987654321098765432109876543210987654321',
            'value'     => '1000000000000000000',
            'chain'     => 'ethereum',
            'gas_limit' => '21000',
            'gas_price' => '50000000000',
            'nonce'     => 5,
        ], $overrides);
    }

    #[Test]
    public function it_creates_signing_request(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => $this->validTransaction(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'request_id',
                'status',
                'raw_data_to_sign',
                'expires_at',
            ],
        ]);

        $this->assertDatabaseHas('pending_signing_requests', [
            'association_id' => $this->association->id,
            'status'         => PendingSigningRequestVO::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function it_submits_signature(): void
    {
        $request = PendingSigningRequest::create([
            'user_id'          => $this->user->id,
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode([
                'from'  => '0x1234567890123456789012345678901234567890',
                'to'    => '0x0987654321098765432109876543210987654321',
                'value' => '1000000000000000000',
                'chain' => 'ethereum',
            ]),
            'raw_data_to_sign' => '0x' . str_repeat('ef', 100),
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $signature = '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b';

        // Mock the HardwareWalletManager to bypass real ECDSA validation
        $mockManager = Mockery::mock(HardwareWalletManager::class);
        $mockManager->shouldReceive('submitSignature')
            ->once()
            ->andReturn(new SignedTransaction(
                rawTransaction: '0x' . str_repeat('ff', 100),
                hash: '0x' . str_repeat('aa', 32),
                transactionData: new TransactionData(
                    from: '0x1234567890123456789012345678901234567890',
                    to: '0x0987654321098765432109876543210987654321',
                    value: '1000000000000000000',
                    chain: 'ethereum',
                ),
            ));
        $this->app->instance(HardwareWalletManager::class, $mockManager);

        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/submit', [
            'signature'  => $signature,
            'public_key' => $this->associationPublicKey,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'completed');
    }

    #[Test]
    public function it_gets_signing_request_status(): void
    {
        $request = PendingSigningRequest::create([
            'user_id'          => $this->user->id,
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/hardware-wallet/signing-request/' . $request->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', PendingSigningRequestVO::STATUS_PENDING);
        $response->assertJsonStructure([
            'data' => [
                'request_id',
                'status',
                'expires_at',
                'is_expired',
            ],
        ]);
    }

    #[Test]
    public function it_cancels_signing_request(): void
    {
        $request = PendingSigningRequest::create([
            'user_id'          => $this->user->id,
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        // Cancel route is POST, not DELETE
        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/cancel');

        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals(PendingSigningRequestVO::STATUS_CANCELLED, $request->status);
    }

    #[Test]
    public function it_cannot_submit_to_expired_request(): void
    {
        $request = PendingSigningRequest::create([
            'user_id'          => $this->user->id,
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_EXPIRED,
            'expires_at'       => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/submit', [
            'signature'  => '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b',
            'public_key' => $this->associationPublicKey,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_cannot_submit_to_completed_request(): void
    {
        $request = PendingSigningRequest::create([
            'user_id'          => $this->user->id,
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_COMPLETED,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/submit', [
            'signature'  => '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b',
            'public_key' => $this->associationPublicKey,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_cannot_access_other_users_signing_request(): void
    {
        $otherUser = User::factory()->create();
        $otherAssociation = HardwareWalletAssociation::create([
            'user_id'          => $otherUser->id,
            'device_type'      => 'ledger_nano_x',
            'device_id'        => 'other_ledger',
            'device_label'     => 'Other Ledger',
            'public_key'       => '04' . str_repeat('ff', 64),
            'address'          => '0x9999999999999999999999999999999999999999',
            'chain'            => 'ethereum',
            'derivation_path'  => "m/44'/60'/0'/0/0",
            'supported_chains' => ['ethereum'],
            'is_active'        => true,
        ]);

        $request = PendingSigningRequest::create([
            'user_id'          => $otherUser->id,
            'association_id'   => $otherAssociation->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/hardware-wallet/signing-request/' . $request->id);

        // Controller scopes query to authenticated user, so other user's request returns 404
        $response->assertStatus(404);
    }

    #[Test]
    public function it_rejects_signing_request_for_inactive_association(): void
    {
        $this->association->update(['is_active' => false]);

        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => $this->validTransaction(),
        ]);

        // Returns 404 because inactive association is not found by the scoped query
        $response->assertStatus(404);
    }

    #[Test]
    public function it_validates_transaction_data(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => [
                // Missing required 'from', 'to', 'value', 'chain' fields
                'gas_limit' => '21000',
                'gas_price' => '50000000000',
            ],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_handles_token_transfer_transaction(): void
    {
        // DAI contract address (valid 40-char hex Ethereum address)
        $daiContract = '0x6B175474E89094C44Da98b954EeDeaD30d6eB03a';

        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => $this->validTransaction([
                'to'    => $daiContract,
                'value' => '0',
                'nonce' => 10,
                'data'  => '0xa9059cbb' . str_repeat('0', 56) . 'abcdef1234567890' . str_repeat('0', 56) . '01',
            ]),
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function it_returns_device_type_in_response(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => $this->validTransaction(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'device_type',
            ],
        ]);
    }

    #[Test]
    public function it_marks_expired_requests_correctly(): void
    {
        $request = PendingSigningRequest::create([
            'user_id'          => $this->user->id,
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'chain'            => 'ethereum',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->subMinutes(1), // Already expired
        ]);

        $response = $this->getJson('/api/hardware-wallet/signing-request/' . $request->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_expired', true);
    }
}
