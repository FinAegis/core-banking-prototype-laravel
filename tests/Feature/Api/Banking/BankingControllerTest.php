<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Banking;

use App\Domain\Banking\Services\AccountVerificationService;
use App\Domain\Banking\Services\BankTransferService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class BankingControllerTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ---------------------------------------------------------------
    // BankingController — transfer status (unique route)
    // ---------------------------------------------------------------

    public function test_transfer_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/banks/transfer/bt_test/status');

        $response->assertUnauthorized();
    }

    public function test_transfer_status_returns_not_found_for_unknown_id(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Unknown ids are not owned by the caller: 404 before any status lookup.
        $this->mock(BankTransferService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getStatus');
        });

        $response = $this->getJson('/api/v2/banks/transfer/bt_nonexistent/status');

        $response->assertNotFound()
            ->assertJsonPath('error', 'Not found.');
    }

    public function test_transfer_status_returns_data_for_existing_transfer(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // The status is readable by the user who initiated the transfer.
        // bank_transfers.id is a uuid column (MariaDB rejects other values).
        $transferId = (string) Str::uuid();
        DB::table('bank_transfers')->insert([
            'id'              => $transferId,
            'user_uuid'       => $this->user->uuid,
            'from_bank_code'  => 'PAYSERA',
            'from_account_id' => 'acc-1',
            'to_bank_code'    => 'REVOLUT',
            'to_account_id'   => 'acc-2',
            'amount'          => 500,
            'currency'        => 'EUR',
            'type'            => 'SEPA',
            'status'          => 'initiated',
            'metadata'        => json_encode(['reference' => 'REF456', 'status_history' => []]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->mock(BankTransferService::class, function (MockInterface $mock) use ($transferId): void {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($transferId)
                ->andReturn([
                    'transfer_id'    => $transferId,
                    'status'         => 'initiated',
                    'amount'         => 500.0,
                    'currency'       => 'EUR',
                    'reference'      => 'REF456',
                    'type'           => 'SEPA',
                    'status_history' => [],
                    'created_at'     => now()->toIso8601String(),
                    'updated_at'     => now()->toIso8601String(),
                ]);
        });

        $response = $this->getJson("/api/v2/banks/transfer/{$transferId}/status");

        $response->assertOk()
            ->assertJsonPath('data.transfer_id', $transferId)
            ->assertJsonPath('data.status', 'initiated')
            ->assertJsonPath('data.amount', 500)
            ->assertJsonPath('data.currency', 'EUR');
    }

    // ---------------------------------------------------------------
    // AccountVerificationController — micro-deposit initiate
    // ---------------------------------------------------------------

    public function test_initiate_micro_deposit_requires_authentication(): void
    {
        $response = $this->postJson('/api/v2/banks/verify/micro-deposit/initiate', []);

        $response->assertUnauthorized();
    }

    public function test_initiate_micro_deposit_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/v2/banks/verify/micro-deposit/initiate', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'iban']);
    }

    public function test_initiate_micro_deposit_succeeds(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $this->mock(AccountVerificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiateMicroDeposit')
                ->once()
                ->andReturn([
                    'verification_id' => 'mdv_test-uuid',
                    'status'          => 'pending',
                    'expires_at'      => now()->addHours(72)->toIso8601String(),
                    'message'         => 'Two micro-deposits in EUR will be sent to the account.',
                ]);
        });

        $response = $this->postJson('/api/v2/banks/verify/micro-deposit/initiate', [
            'account_id' => 'acc_123',
            'iban'       => 'DE89370400440532013000',
            'currency'   => 'EUR',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.verification_id', 'mdv_test-uuid')
            ->assertJsonPath('data.status', 'pending');
    }

    // ---------------------------------------------------------------
    // AccountVerificationController — micro-deposit confirm
    // ---------------------------------------------------------------

    public function test_confirm_micro_deposit_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/v2/banks/verify/micro-deposit/confirm', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_id', 'amount_1', 'amount_2']);
    }

    public function test_confirm_micro_deposit_succeeds(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $this->mock(AccountVerificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyMicroDeposit')
                ->once()
                ->andReturn([
                    'verification_id' => 'mdv_test-uuid',
                    'status'          => 'verified',
                    'verified'        => true,
                    'message'         => 'Account successfully verified via micro-deposits.',
                ]);
        });

        $response = $this->postJson('/api/v2/banks/verify/micro-deposit/confirm', [
            'verification_id' => 'mdv_test-uuid',
            'amount_1'        => 42,
            'amount_2'        => 17,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.status', 'verified');
    }

    // ---------------------------------------------------------------
    // AccountVerificationController — instant verification
    // ---------------------------------------------------------------

    public function test_instant_verify_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/v2/banks/verify/instant', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'iban']);
    }

    public function test_instant_verify_succeeds(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $this->mock(AccountVerificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiateInstantVerification')
                ->once()
                ->andReturn([
                    'verification_id' => 'ivf_test-uuid',
                    'status'          => 'awaiting_consent',
                    'redirect_url'    => 'https://bank.example.com/consent/abc',
                    'expires_at'      => now()->addMinutes(30)->toIso8601String(),
                ]);
        });

        $response = $this->postJson('/api/v2/banks/verify/instant', [
            'account_id' => 'acc_456',
            'iban'       => 'DE89370400440532013000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.verification_id', 'ivf_test-uuid')
            ->assertJsonPath('data.status', 'awaiting_consent');
    }

    // ---------------------------------------------------------------
    // BankWebhookController — transfer update
    // ---------------------------------------------------------------

    public function test_webhook_transfer_update_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/webhooks/bank/paysera/transfer-update', [
            'transfer_id' => 'bt_123',
            'status'      => 'completed',
        ]);

        $response->assertUnauthorized();
    }

    public function test_webhook_transfer_update_accepts_valid_payload(): void
    {
        // In non-production without configured secret, signature check is lenient
        $payload = [
            'transfer_id' => 'bt_123',
            'status'      => 'completed',
            'message'     => 'Transfer completed successfully',
        ];

        $secret = 'test-webhook-secret';
        config(['services.banking.webhooks.paysera.secret' => $secret]);

        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $this->mock(BankTransferService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('advanceStatus')
                ->once()
                ->with('bt_123', 'completed', 'Transfer completed successfully')
                ->andReturn(true);
        });

        $response = $this->postJson(
            '/api/webhooks/bank/paysera/transfer-update',
            $payload,
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(202)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('applied', true);
    }

    public function test_webhook_transfer_update_rejects_empty_payload(): void
    {
        config(['services.banking.webhooks.test.secret' => '']);

        $response = $this->postJson(
            '/api/webhooks/bank/test/transfer-update',
            [],
            ['X-Webhook-Signature' => 'any-sig'],
        );

        // In non-production, no secret means signature passes, but empty payload is rejected
        $response->assertStatus(400);
    }

    // ---------------------------------------------------------------
    // BankWebhookController — account update
    // ---------------------------------------------------------------

    public function test_webhook_account_update_accepts_valid_payload(): void
    {
        $payload = [
            'event_type' => 'account.updated',
            'account_id' => 'acc_789',
            'status'     => 'active',
        ];

        $secret = 'test-webhook-secret';
        config(['services.banking.webhooks.test.secret' => $secret]);

        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $response = $this->postJson(
            '/api/webhooks/bank/test/account-update',
            $payload,
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(202)
            ->assertJsonPath('status', 'accepted');
    }
}
