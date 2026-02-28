<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Domain\Commerce\Events\Broadcast\CommercePaymentConfirmed;
use App\Domain\Privacy\Events\Broadcast\PrivacyOperationCompleted;
use App\Domain\Privacy\Models\DelegatedProofJob;
use App\Domain\Privacy\Services\DelegatedProofService;
use App\Domain\TrustCert\Events\Broadcast\TrustCertStatusChanged;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\TestCase;

class MobileChannelRegistrationTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;
    }

    // ---- Channel callback tests ----

    public function test_user_channel_is_registered(): void
    {
        // Verify that the user channel is registered in routes/channels.php
        // by checking that broadcasting auth returns 200 for own user with log driver
        $response = $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-user.{$this->user->id}",
            ]);

        $response->assertOk();
    }

    public function test_privacy_channel_auth_returns_200(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-privacy.{$this->user->id}",
            ]);

        $response->assertOk();
    }

    public function test_privacy_proof_channel_auth_returns_200(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-privacy.proof.{$this->user->id}",
            ]);

        $response->assertOk();
    }

    public function test_commerce_channel_auth_returns_200_in_testing(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-commerce.merchant_123',
            ]);

        $response->assertOk();
    }

    public function test_trustcert_channel_auth_returns_200(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-trustcert.{$this->user->id}",
            ]);

        $response->assertOk();
    }

    // ---- Broadcast event class tests ----

    public function test_privacy_operation_completed_broadcasts_on_correct_channel(): void
    {
        $event = new PrivacyOperationCompleted(
            userId: $this->user->id,
            operation: 'shield',
            token: 'USDC',
            amount: '100.00',
            network: 'polygon',
            status: 'completed',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('privacy.operation.completed', $event->broadcastAs());
    }

    public function test_commerce_payment_confirmed_broadcasts_on_correct_channel(): void
    {
        $event = new CommercePaymentConfirmed(
            merchantId: 'merchant_123',
            attestationId: 'att_123',
            attestationType: 'payment',
            attestationHash: 'hash_abc',
            attestedAt: '2026-03-01T12:00:00+00:00',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('commerce.payment.confirmed', $event->broadcastAs());
    }

    public function test_trust_cert_status_changed_broadcasts_on_correct_channel(): void
    {
        $event = new TrustCertStatusChanged(
            userId: (string) $this->user->id,
            certificateId: 'cert_123',
            status: 'active',
            subjectId: 'subject_123',
            changedAt: '2026-03-01T12:00:00+00:00',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('trustcert.status.changed', $event->broadcastAs());
    }

    public function test_privacy_event_broadcast_data(): void
    {
        $event = new PrivacyOperationCompleted(
            userId: $this->user->id,
            operation: 'unshield',
            token: 'DAI',
            amount: '50.00',
            network: 'base',
            status: 'pending',
        );

        $data = $event->broadcastWith();
        $this->assertEquals('unshield', $data['operation']);
        $this->assertEquals('DAI', $data['token']);
        $this->assertEquals('50.00', $data['amount']);
        $this->assertEquals('base', $data['network']);
        $this->assertEquals('pending', $data['status']);
    }

    public function test_commerce_event_broadcast_data(): void
    {
        $event = new CommercePaymentConfirmed(
            merchantId: 'merchant_abc',
            attestationId: 'att_xyz',
            attestationType: 'payment',
            attestationHash: 'hash_123',
            attestedAt: '2026-03-01T10:00:00+00:00',
        );

        $data = $event->broadcastWith();
        $this->assertEquals('att_xyz', $data['attestation_id']);
        $this->assertEquals('payment', $data['attestation_type']);
        $this->assertEquals('hash_123', $data['attestation_hash']);
        $this->assertEquals('2026-03-01T10:00:00+00:00', $data['attested_at']);
    }

    public function test_trustcert_event_broadcast_data(): void
    {
        $event = new TrustCertStatusChanged(
            userId: '42',
            certificateId: 'cert_abc',
            status: 'revoked',
            subjectId: 'subject_42',
            changedAt: '2026-03-01T14:00:00+00:00',
        );

        $data = $event->broadcastWith();
        $this->assertEquals('cert_abc', $data['certificate_id']);
        $this->assertEquals('revoked', $data['status']);
        $this->assertEquals('subject_42', $data['subject_id']);
        $this->assertEquals('2026-03-01T14:00:00+00:00', $data['changed_at']);
    }

    public function test_broadcast_events_respect_websocket_enabled_config(): void
    {
        config(['websocket.enabled' => false]);

        $event = new PrivacyOperationCompleted(
            userId: $this->user->id,
            operation: 'shield',
            token: 'USDC',
            amount: '100.00',
            network: 'polygon',
            status: 'completed',
        );

        $this->assertFalse($event->broadcastWhen());
    }

    public function test_broadcast_events_use_broadcasts_queue(): void
    {
        $event = new PrivacyOperationCompleted(
            userId: 1,
            operation: 'shield',
            token: 'USDC',
            amount: '100.00',
            network: 'polygon',
            status: 'completed',
        );

        $this->assertEquals('broadcasts', $event->broadcastQueue());
    }

    private function mockDelegatedProofService(): void
    {
        /** @var DelegatedProofJob&MockInterface $mockJob */
        $mockJob = $this->mock(DelegatedProofJob::class, function (MockInterface $mock) {
            $mock->shouldReceive('toApiResponse')
                ->andReturn(['job_id' => 'job_123', 'status' => 'pending']);
        });

        $this->mock(DelegatedProofService::class, function (MockInterface $mock) use ($mockJob) {
            $mock->shouldReceive('requestProof')
                ->andReturn($mockJob);
        });
    }

    public function test_privacy_shield_dispatches_broadcast_event(): void
    {
        Event::fake([PrivacyOperationCompleted::class]);
        $this->mockDelegatedProofService();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/shield', [
                'amount'  => '100.00',
                'token'   => 'USDC',
                'network' => 'polygon',
            ]);

        $response->assertCreated();

        Event::assertDispatched(PrivacyOperationCompleted::class, function ($event) {
            return $event->userId === $this->user->id
                && $event->operation === 'shield'
                && $event->token === 'USDC';
        });
    }

    public function test_privacy_unshield_dispatches_broadcast_event(): void
    {
        Event::fake([PrivacyOperationCompleted::class]);
        $this->mockDelegatedProofService();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/unshield', [
                'amount'    => '50.00',
                'token'     => 'USDC',
                'network'   => 'polygon',
                'recipient' => '0x1234567890abcdef',
            ]);

        $response->assertCreated();

        Event::assertDispatched(PrivacyOperationCompleted::class, function ($event) {
            return $event->operation === 'unshield';
        });
    }

    public function test_privacy_transfer_dispatches_broadcast_event(): void
    {
        Event::fake([PrivacyOperationCompleted::class]);
        $this->mockDelegatedProofService();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/transfer', [
                'amount'  => '25.00',
                'token'   => 'USDC',
                'network' => 'polygon',
            ]);

        $response->assertCreated();

        Event::assertDispatched(PrivacyOperationCompleted::class, function ($event) {
            return $event->operation === 'transfer';
        });
    }
}
