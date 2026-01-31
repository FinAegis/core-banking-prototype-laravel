<?php

declare(strict_types=1);

namespace Tests\Domain\Wallet\Services\MultiSig;

use App\Domain\Wallet\Events\MultiSigApprovalCompleted;
use App\Domain\Wallet\Events\MultiSigApprovalCreated;
use App\Domain\Wallet\Events\MultiSigSignatureSubmitted;
use App\Domain\Wallet\Models\MultiSigApprovalRequest;
use App\Domain\Wallet\Models\MultiSigSignerApproval;
use App\Domain\Wallet\Models\MultiSigWallet;
use App\Domain\Wallet\Models\MultiSigWalletSigner;
use App\Domain\Wallet\Services\MultiSigApprovalService;
use App\Domain\Wallet\Services\MultiSigWalletService;
use App\Domain\Wallet\ValueObjects\MultiSigConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MultiSigApprovalServiceTest extends TestCase
{
    private MultiSigApprovalService $approvalService;

    private MultiSigWalletService $walletService;

    private User $owner;

    private MultiSigWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approvalService = new MultiSigApprovalService();
        $this->walletService = new MultiSigWalletService();
        $this->owner = User::factory()->create();
        $this->wallet = $this->createActiveWallet();
    }

    #[Test]
    public function it_creates_an_approval_request(): void
    {
        Event::fake([MultiSigApprovalCreated::class]);

        $transactionData = [
            'to'       => '0x1234567890123456789012345678901234567890',
            'amount'   => 100,
            'currency' => 'ETH',
        ];

        $request = $this->approvalService->createApprovalRequest(
            wallet: $this->wallet,
            initiator: $this->owner,
            transactionData: $transactionData,
        );

        $this->assertInstanceOf(MultiSigApprovalRequest::class, $request);
        $this->assertEquals($this->wallet->id, $request->multi_sig_wallet_id);
        $this->assertEquals($this->owner->id, $request->initiator_user_id);
        $this->assertEquals(MultiSigApprovalRequest::STATUS_PENDING, $request->status);
        $this->assertEquals($transactionData, $request->transaction_data);
        $this->assertEquals($this->wallet->required_signatures, $request->required_signatures);
        $this->assertEquals(0, $request->current_signatures);
        $this->assertFalse($request->isExpired());

        // Should have signer approvals created for each signer
        $this->assertCount(2, $request->signerApprovals);

        Event::assertDispatched(MultiSigApprovalCreated::class);
    }

    #[Test]
    public function it_submits_a_signature(): void
    {
        Event::fake([MultiSigSignatureSubmitted::class]);

        $request = $this->createApprovalRequest();
        $signer = $this->wallet->signers()->first();
        $this->assertNotNull($signer);
        $user = $signer->user;
        $this->assertNotNull($user);

        $approval = $this->approvalService->submitSignature(
            request: $request,
            user: $user,
            signature: str_repeat('a', 128),
            publicKey: str_repeat('b', 64),
        );

        $this->assertInstanceOf(MultiSigSignerApproval::class, $approval);
        $this->assertEquals(MultiSigSignerApproval::DECISION_APPROVED, $approval->decision);
        $this->assertNotNull($approval->signature);
        $this->assertNotNull($approval->decided_at);

        $request->refresh();
        $this->assertEquals(1, $request->current_signatures);

        Event::assertDispatched(MultiSigSignatureSubmitted::class, function ($event) use ($request) {
            return $event->approvalRequestId === $request->id
                && $event->currentSignatures === 1;
        });
    }

    #[Test]
    public function it_reaches_quorum_after_required_signatures(): void
    {
        Event::fake([MultiSigSignatureSubmitted::class]);

        $request = $this->createApprovalRequest();
        $signers = $this->wallet->signers()->get();
        $this->assertCount(2, $signers);

        $signer0 = $signers->get(0);
        $signer1 = $signers->get(1);
        $this->assertNotNull($signer0);
        $this->assertNotNull($signer1);
        $signer0User = $signer0->user;
        $signer1User = $signer1->user;
        $this->assertNotNull($signer0User);
        $this->assertNotNull($signer1User);

        // Submit first signature
        $this->approvalService->submitSignature(
            request: $request,
            user: $signer0User,
            signature: str_repeat('a', 128),
            publicKey: str_repeat('b', 64),
        );

        $request->refresh();
        $this->assertFalse($request->hasReachedQuorum());
        $this->assertEquals(MultiSigApprovalRequest::STATUS_PENDING, $request->status);

        // Submit second signature (quorum reached for 2-of-2)
        $this->approvalService->submitSignature(
            request: $request,
            user: $signer1User,
            signature: str_repeat('c', 128),
            publicKey: str_repeat('d', 64),
        );

        $request->refresh();
        $this->assertTrue($request->hasReachedQuorum());
        $this->assertEquals(MultiSigApprovalRequest::STATUS_APPROVED, $request->status);
    }

    #[Test]
    public function it_prevents_duplicate_signatures(): void
    {
        $request = $this->createApprovalRequest();
        $signer = $this->wallet->signers()->first();
        $this->assertNotNull($signer);
        $signerUser = $signer->user;
        $this->assertNotNull($signerUser);

        // First signature
        $this->approvalService->submitSignature(
            request: $request,
            user: $signerUser,
            signature: str_repeat('a', 128),
            publicKey: str_repeat('b', 64),
        );

        // Try to submit again
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User has already submitted their decision');

        $this->approvalService->submitSignature(
            request: $request,
            user: $signerUser,
            signature: str_repeat('c', 128),
            publicKey: str_repeat('d', 64),
        );
    }

    #[Test]
    public function it_rejects_a_request(): void
    {
        $request = $this->createApprovalRequest();
        $signer = $this->wallet->signers()->first();
        $this->assertNotNull($signer);
        $signerUser = $signer->user;
        $this->assertNotNull($signerUser);

        $approval = $this->approvalService->rejectRequest(
            request: $request,
            user: $signerUser,
            reason: 'Amount too high',
        );

        $this->assertEquals(MultiSigSignerApproval::DECISION_REJECTED, $approval->decision);
        $this->assertEquals('Amount too high', $approval->rejection_reason);
        $this->assertNotNull($approval->decided_at);
    }

    #[Test]
    public function it_broadcasts_transaction_when_quorum_reached(): void
    {
        Event::fake([MultiSigApprovalCompleted::class]);

        $request = $this->createApprovalRequest();
        $signers = $this->wallet->signers()->get();

        // Collect all signatures
        foreach ($signers as $signer) {
            $signerUser = $signer->user;
            $this->assertNotNull($signerUser);
            $this->approvalService->submitSignature(
                request: $request,
                user: $signerUser,
                signature: str_repeat($signer->signer_order . 'a', 64),
                publicKey: str_repeat($signer->signer_order . 'b', 32),
            );
        }

        $request->refresh();
        $this->assertTrue($request->hasReachedQuorum());

        // Broadcast
        $this->approvalService->broadcastTransaction($request);

        $request->refresh();
        $this->assertEquals(MultiSigApprovalRequest::STATUS_COMPLETED, $request->status);
        $this->assertNotNull($request->transaction_hash);
        $this->assertNotNull($request->completed_at);

        Event::assertDispatched(MultiSigApprovalCompleted::class, function ($event) use ($request) {
            return $event->approvalRequestId === $request->id
                && $event->status === 'completed';
        });
    }

    #[Test]
    public function it_prevents_broadcast_without_quorum(): void
    {
        $request = $this->createApprovalRequest();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot broadcast: quorum not reached');

        $this->approvalService->broadcastTransaction($request);
    }

    #[Test]
    public function it_cancels_a_request(): void
    {
        $request = $this->createApprovalRequest();

        $this->approvalService->cancelRequest($request, $this->owner);

        $request->refresh();
        $this->assertEquals(MultiSigApprovalRequest::STATUS_CANCELLED, $request->status);
    }

    #[Test]
    public function it_prevents_non_owner_from_cancelling(): void
    {
        $request = $this->createApprovalRequest();
        $randomUser = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the initiator or wallet owner can cancel the request');

        $this->approvalService->cancelRequest($request, $randomUser);
    }

    #[Test]
    public function it_gets_approval_status(): void
    {
        $request = $this->createApprovalRequest();
        $signer = $this->wallet->signers()->first();
        $this->assertNotNull($signer);
        $signerUser = $signer->user;
        $this->assertNotNull($signerUser);

        // Submit one signature
        $this->approvalService->submitSignature(
            request: $request,
            user: $signerUser,
            signature: str_repeat('a', 128),
            publicKey: str_repeat('b', 64),
        );

        $request->refresh();
        $status = $this->approvalService->getApprovalStatus($request);

        $this->assertEquals($request->id, $status->requestId);
        $this->assertEquals(MultiSigApprovalRequest::STATUS_PENDING, $status->status);
        $this->assertEquals(2, $status->requiredSignatures);
        $this->assertEquals(1, $status->currentSignatures);
        $this->assertEquals(1, $status->remainingSignatures);
        $this->assertFalse($status->isExpired);
        $this->assertTrue($status->canAcceptSignature());
        $this->assertCount(2, $status->signerStatuses);
    }

    #[Test]
    public function it_gets_pending_requests_for_user(): void
    {
        $request = $this->createApprovalRequest();
        $signer = $this->wallet->signers()->first();
        $this->assertNotNull($signer);
        $signerUser = $signer->user;
        $this->assertNotNull($signerUser);

        $pendingRequests = $this->approvalService->getPendingRequestsForUser($signerUser);

        $this->assertCount(1, $pendingRequests);
        $firstPending = $pendingRequests->first();
        $this->assertNotNull($firstPending);
        $this->assertEquals($request->id, $firstPending->id);

        // After signing, should not appear in pending
        $this->approvalService->submitSignature(
            request: $request,
            user: $signerUser,
            signature: str_repeat('a', 128),
            publicKey: str_repeat('b', 64),
        );

        $pendingRequests = $this->approvalService->getPendingRequestsForUser($signerUser);
        $this->assertCount(0, $pendingRequests);
    }

    #[Test]
    public function it_expires_old_requests(): void
    {
        $request = $this->createApprovalRequest();

        // Manually expire the request
        $request->update(['expires_at' => now()->subHour()]);

        $expiredCount = $this->approvalService->expireOldRequests();

        $this->assertEquals(1, $expiredCount);

        $request->refresh();
        $this->assertEquals(MultiSigApprovalRequest::STATUS_EXPIRED, $request->status);
    }

    /**
     * Create an active multi-sig wallet with 2-of-2 signers.
     */
    private function createActiveWallet(): MultiSigWallet
    {
        $config = MultiSigConfiguration::create(
            requiredSignatures: 2,
            totalSigners: 2,
            chain: 'ethereum',
            name: 'Test Wallet',
        );

        $wallet = $this->walletService->createWallet($this->owner, $config);

        // Add first signer (owner)
        $this->walletService->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('a', 64),
            user: $this->owner,
            label: 'Owner',
        );

        // Add second signer
        $secondUser = User::factory()->create();
        $this->walletService->addSigner(
            wallet: $wallet,
            signerType: MultiSigWalletSigner::TYPE_INTERNAL,
            publicKey: str_repeat('b', 64),
            user: $secondUser,
            label: 'Second Signer',
        );

        return $wallet->refresh();
    }

    /**
     * Create an approval request.
     */
    private function createApprovalRequest(): MultiSigApprovalRequest
    {
        return $this->approvalService->createApprovalRequest(
            wallet: $this->wallet,
            initiator: $this->owner,
            transactionData: [
                'to'     => '0x1234567890123456789012345678901234567890',
                'amount' => 100,
            ],
        );
    }
}
