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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundAggregateTest extends TestCase
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
                )
            ]);
    }

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
                )
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
                )
            ]);
    }

    public function test_cannot_approve_non_pending_refund()
    {
        $this->expectException(\DomainException::class);
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
                )
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->approve(
                    'another-admin',
                    'Trying to approve again'
                );
            });
    }

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
                )
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
                )
            ]);
    }

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
                )
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
                )
            ]);
    }

    public function test_can_complete_processing_refund()
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
                new RefundProcessed(
                    $this->refundId,
                    'stripe',
                    're_test123',
                    'succeeded',
                    []
                )
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->complete(now()->toIso8601String());
            })
            ->assertRecorded([
                new RefundCompleted(
                    $this->refundId,
                    $this->investmentId,
                    10000,
                    now()->toIso8601String(),
                    []
                )
            ]);
    }

    public function test_can_fail_refund()
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
                )
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->fail(
                    'Payment processor error: Insufficient funds',
                    now()->toIso8601String()
                );
            })
            ->assertRecorded([
                new RefundFailed(
                    $this->refundId,
                    'Payment processor error: Insufficient funds',
                    now()->toIso8601String(),
                    []
                )
            ]);
    }

    public function test_can_cancel_refund()
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
                )
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->cancel(
                    'Customer requested cancellation',
                    $this->userId,
                    now()->toIso8601String()
                );
            })
            ->assertRecorded([
                new RefundCancelled(
                    $this->refundId,
                    'Customer requested cancellation',
                    $this->userId,
                    now()->toIso8601String(),
                    []
                )
            ]);
    }

    public function test_cannot_cancel_completed_refund()
    {
        $this->expectException(\DomainException::class);
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
                    now()->toIso8601String()
                )
            ])
            ->when(function (RefundAggregate $aggregate) {
                $aggregate->cancel(
                    'Trying to cancel completed refund',
                    $this->userId,
                    now()->toIso8601String()
                );
            });
    }
}