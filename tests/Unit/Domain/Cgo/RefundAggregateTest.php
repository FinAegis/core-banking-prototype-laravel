<?php

namespace Tests\Unit\Domain\Cgo;

use App\Domain\Cgo\Aggregates\RefundAggregate;
use App\Domain\Cgo\Events\RefundApproved;
use App\Domain\Cgo\Events\RefundCancelled;
use App\Domain\Cgo\Events\RefundCompleted;
use App\Domain\Cgo\Events\RefundFailed;
use App\Domain\Cgo\Events\RefundProcessed;
use App\Domain\Cgo\Events\RefundRejected;
use App\Domain\Cgo\Events\RefundRequested;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class RefundAggregateTest extends DomainTestCase
{
    use RefreshDatabase;

    private string $refundId;

    private string $investmentId;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundId = Str::uuid()->toString();
        $this->investmentId = Str::uuid()->toString();
        $this->userId = Str::uuid()->toString();
    }

    #[Test]
    public function test_can_request_refund()
    {
        RefundAggregate::fake()
            ->given([])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->requestRefund(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000, // $100
                    'USD',
                    'customer_request',
                    'Customer changed their mind',
                    $this->userId
                );
            })
            ->assertRecorded([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    'Customer changed their mind',
                    $this->userId,
                    []
                ),
            ]);
    }

    #[Test]
    public function test_can_approve_pending_refund()
    {
        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    'Customer changed their mind',
                    $this->userId
                ),
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->approve(
                    'admin-user-id',
                    'Refund approved per company policy'
                );
            })
            ->assertRecorded([
                new RefundApproved(
                    $this->refundId,
                    'admin-user-id',
                    'Refund approved per company policy',
                    []
                ),
            ]);
    }

    #[Test]
    public function test_cannot_approve_non_pending_refund()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Can only approve pending refunds');

        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
                new RefundApproved(
                    $this->refundId,
                    'admin-user-id',
                    'Already approved'
                ),
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->approve(
                    'another-admin',
                    'Trying to approve again'
                );
            });
    }

    #[Test]
    public function test_can_reject_pending_refund()
    {
        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->reject(
                    'admin-user-id',
                    'Refund not eligible per terms and conditions'
                );
            })
            ->assertRecorded([
                new RefundRejected(
                    $this->refundId,
                    'admin-user-id',
                    'Refund not eligible per terms and conditions',
                    []
                ),
            ]);
    }

    #[Test]
    public function test_can_process_approved_refund()
    {
        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
                new RefundApproved(
                    $this->refundId,
                    'admin-user-id',
                    'Approved'
                ),
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->process(
                    'stripe',
                    're_test123',
                    'succeeded',
                    ['stripe_response' => 'data']
                );
            })
            ->assertRecorded([
                new RefundProcessed(
                    $this->refundId,
                    'stripe',
                    're_test123',
                    'succeeded',
                    ['stripe_response' => 'data'],
                    []
                ),
            ]);
    }

    #[Test]
    public function test_can_complete_processing_refund()
    {
        $this->freezeTime();
        $completedAt = now()->toIso8601String();

        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
                new RefundApproved(
                    $this->refundId,
                    'admin-user-id',
                    'Approved'
                ),
                new RefundProcessed(
                    $this->refundId,
                    'stripe',
                    're_test123',
                    'succeeded',
                    []
                ),
            ])
            ->when(function (RefundAggregate $aggregate) use ($completedAt) {
                $aggregate->complete($completedAt);
            })
            ->assertRecorded([
                new RefundCompleted(
                    $this->refundId,
                    $this->investmentId,
                    10000,
                    $completedAt,
                    []
                ),
            ]);
    }

    #[Test]
    public function test_can_fail_refund()
    {
        $this->freezeTime();
        $failedAt = now()->toIso8601String();

        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
                new RefundApproved(
                    $this->refundId,
                    'admin-user-id',
                    'Approved'
                ),
            ])
            ->when(function (RefundAggregate $aggregate) use ($failedAt) {
                $aggregate->fail(
                    'Payment processor error: Insufficient funds',
                    $failedAt
                );
            })
            ->assertRecorded([
                new RefundFailed(
                    $this->refundId,
                    'Payment processor error: Insufficient funds',
                    $failedAt,
                    []
                ),
            ]);
    }

    #[Test]
    public function test_can_cancel_refund()
    {
        $this->freezeTime();
        $cancelledAt = now()->toIso8601String();

        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
            ])
            ->when(function (RefundAggregate $aggregate) use ($cancelledAt) {
                $aggregate->cancel(
                    'Customer requested cancellation',
                    $this->userId,
                    $cancelledAt
                );
            })
            ->assertRecorded([
                new RefundCancelled(
                    $this->refundId,
                    'Customer requested cancellation',
                    $this->userId,
                    $cancelledAt,
                    []
                ),
            ]);
    }

    #[Test]
    public function test_cannot_cancel_completed_refund()
    {
        $this->freezeTime();
        $timestamp = now()->toIso8601String();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot cancel refunds in status: completed');

        RefundAggregate::fake()
            ->given([
                new RefundRequested(
                    $this->refundId,
                    $this->investmentId,
                    $this->userId,
                    10000,
                    'USD',
                    'customer_request',
                    null,
                    $this->userId
                ),
                new RefundApproved(
                    $this->refundId,
                    'admin-user-id',
                    'Approved'
                ),
                new RefundProcessed(
                    $this->refundId,
                    'stripe',
                    're_test123',
                    'succeeded',
                    []
                ),
                new RefundCompleted(
                    $this->refundId,
                    $this->investmentId,
                    10000,
                    $timestamp
                ),
            ])
            ->when(function (RefundAggregate $aggregate) use ($timestamp) {
                $aggregate->cancel(
                    'Trying to cancel completed refund',
                    $this->userId,
                    $timestamp
                );
            });
    }
}
