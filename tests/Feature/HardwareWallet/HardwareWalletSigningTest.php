<?php

declare(strict_types=1);

namespace Tests\Feature\HardwareWallet;

use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\PendingSigningRequest;
use App\Domain\Wallet\ValueObjects\PendingSigningRequest as PendingSigningRequestVO;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
    use RefreshDatabase;

    private HardwareWalletAssociation $association;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->user);

        $this->association = HardwareWalletAssociation::create([
            'user_id'         => $this->user->id,
            'device_type'     => 'ledger_nano_x',
            'device_id'       => 'ledger_' . time(),
            'device_label'    => 'Test Ledger',
            'public_key'      => '04' . str_repeat('ab', 64),
            'address'         => '0x1234567890123456789012345678901234567890',
            'chain'           => 'ethereum',
            'derivation_path' => "44'/60'/0'/0/0",
            'is_active'       => true,
        ]);
    }

    #[Test]
    public function it_creates_signing_request(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => [
                'to'        => '0x0987654321098765432109876543210987654321',
                'value'     => '1000000000000000000',
                'gas_limit' => '21000',
                'gas_price' => '50000000000',
                'nonce'     => 5,
                'data'      => null,
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'request_id',
                'raw_data_to_sign',
                'expires_at',
                'status',
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
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode([
                'from'     => '0x1234567890123456789012345678901234567890',
                'to'       => '0x0987654321098765432109876543210987654321',
                'value'    => '1000000000000000000',
                'chain'    => 'ethereum',
                'gasLimit' => '21000',
                'gasPrice' => '50000000000',
                'nonce'    => 5,
            ]),
            'raw_data_to_sign' => '0x' . str_repeat('ef', 100),
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $signature = '0x' . str_repeat('ab', 32) . str_repeat('cd', 32) . '1b';
        $publicKey = '04' . str_repeat('ef', 64);

        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/submit', [
            'signature'  => $signature,
            'public_key' => $publicKey,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', PendingSigningRequestVO::STATUS_COMPLETED);

        $request->refresh();
        $this->assertEquals(PendingSigningRequestVO::STATUS_COMPLETED, $request->status);
        $this->assertEquals($signature, $request->signature);
    }

    #[Test]
    public function it_gets_signing_request_status(): void
    {
        $request = PendingSigningRequest::create([
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/hardware-wallet/signing-request/' . $request->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', PendingSigningRequestVO::STATUS_PENDING);
        $response->assertJsonStructure([
            'data' => [
                'id',
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
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->deleteJson('/api/hardware-wallet/signing-request/' . $request->id);

        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals(PendingSigningRequestVO::STATUS_CANCELLED, $request->status);
    }

    #[Test]
    public function it_cannot_submit_to_expired_request(): void
    {
        $request = PendingSigningRequest::create([
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_EXPIRED,
            'expires_at'       => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/submit', [
            'signature'  => '0x' . str_repeat('ab', 65),
            'public_key' => '04' . str_repeat('cd', 64),
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_cannot_submit_to_completed_request(): void
    {
        $request = PendingSigningRequest::create([
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_COMPLETED,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/hardware-wallet/signing-request/' . $request->id . '/submit', [
            'signature'  => '0x' . str_repeat('ab', 65),
            'public_key' => '04' . str_repeat('cd', 64),
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_cannot_access_other_users_signing_request(): void
    {
        $otherUser = User::factory()->create();
        $otherAssociation = HardwareWalletAssociation::create([
            'user_id'         => $otherUser->id,
            'device_type'     => 'ledger_nano_x',
            'device_id'       => 'other_ledger',
            'device_label'    => 'Other Ledger',
            'public_key'      => '04' . str_repeat('ff', 64),
            'address'         => '0x9999999999999999999999999999999999999999',
            'chain'           => 'ethereum',
            'derivation_path' => "44'/60'/0'/0/0",
            'is_active'       => true,
        ]);

        $request = PendingSigningRequest::create([
            'association_id'   => $otherAssociation->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/hardware-wallet/signing-request/' . $request->id);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_rejects_signing_request_for_inactive_association(): void
    {
        $this->association->update(['is_active' => false]);

        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => [
                'to'        => '0x0987654321098765432109876543210987654321',
                'value'     => '1000000000000000000',
                'gas_limit' => '21000',
                'gas_price' => '50000000000',
                'nonce'     => 5,
            ],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_validates_transaction_data(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => [
                // Missing required 'to' field
                'value'     => '1000000000000000000',
                'gas_limit' => '21000',
                'gas_price' => '50000000000',
            ],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_handles_token_transfer_transaction(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => [
                'to'        => '0x6B175474E89094C44Da98b954EesdeadD30323B76',
                'value'     => '0',
                'gas_limit' => '100000',
                'gas_price' => '50000000000',
                'nonce'     => 10,
                'data'      => '0xa9059cbb000000000000000000000000abcdef1234567890000000000000000001',
            ],
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function it_returns_confirmation_steps_in_response(): void
    {
        $response = $this->postJson('/api/hardware-wallet/signing-request', [
            'association_id' => $this->association->id,
            'transaction'    => [
                'to'        => '0x0987654321098765432109876543210987654321',
                'value'     => '1000000000000000000',
                'gas_limit' => '21000',
                'gas_price' => '50000000000',
                'nonce'     => 5,
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'confirmation_steps',
            ],
        ]);
    }

    #[Test]
    public function it_marks_expired_requests_correctly(): void
    {
        $request = PendingSigningRequest::create([
            'association_id'   => $this->association->id,
            'transaction_data' => json_encode(['test' => 'data']),
            'raw_data_to_sign' => '0x123',
            'status'           => PendingSigningRequestVO::STATUS_PENDING,
            'expires_at'       => now()->subMinutes(1), // Already expired
        ]);

        $response = $this->getJson('/api/hardware-wallet/signing-request/' . $request->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_expired', true);
    }
}
