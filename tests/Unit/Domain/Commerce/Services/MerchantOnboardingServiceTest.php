<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\MerchantStatus;
use App\Domain\Commerce\Events\MerchantOnboarded;
use App\Domain\Commerce\Services\MerchantOnboardingService;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Event::fake();
    $this->service = new MerchantOnboardingService();
});

describe('MerchantOnboardingService', function (): void {
    describe('submitApplication', function (): void {
        it('creates a merchant application', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );

            expect($result['merchant_id'])->not->toBeEmpty();
            expect($result['status'])->toBe('pending');
        });

        it('stores business details', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
                businessDetails: ['registration_number' => '123456'],
            );

            $merchant = $this->service->getMerchant($result['merchant_id']);

            expect($merchant['business_name'])->toBe('Test Shop');
            expect($merchant['business_details']['registration_number'])->toBe('123456');
        });
    });

    describe('status transitions', function (): void {
        it('transitions through onboarding flow', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );
            $merchantId = $result['merchant_id'];

            // Start review
            $this->service->startReview($merchantId, 'reviewer-1');
            expect($this->service->getMerchantStatus($merchantId))->toBe(MerchantStatus::UNDER_REVIEW);

            // Approve
            $this->service->approve($merchantId, 'approver-1');
            expect($this->service->getMerchantStatus($merchantId))->toBe(MerchantStatus::APPROVED);

            // Activate
            $this->service->activate($merchantId);
            expect($this->service->getMerchantStatus($merchantId))->toBe(MerchantStatus::ACTIVE);

            Event::assertDispatched(MerchantOnboarded::class, function ($event) use ($merchantId): bool {
                return $event->merchantId === $merchantId
                    && $event->status === MerchantStatus::ACTIVE;
            });
        });

        it('allows suspension and reactivation', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );
            $merchantId = $result['merchant_id'];

            // Get to active state
            $this->service->startReview($merchantId, 'reviewer-1');
            $this->service->approve($merchantId, 'approver-1');
            $this->service->activate($merchantId);

            // Suspend
            $this->service->suspend($merchantId, 'Policy violation');
            expect($this->service->getMerchantStatus($merchantId))->toBe(MerchantStatus::SUSPENDED);
            expect($this->service->canAcceptPayments($merchantId))->toBeFalse();

            // Reactivate
            $this->service->reactivate($merchantId, 'Issue resolved');
            expect($this->service->getMerchantStatus($merchantId))->toBe(MerchantStatus::ACTIVE);
            expect($this->service->canAcceptPayments($merchantId))->toBeTrue();
        });

        it('throws on invalid transition', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );
            $merchantId = $result['merchant_id'];

            // Try to activate directly from pending (invalid)
            expect(fn () => $this->service->activate($merchantId))
                ->toThrow(RuntimeException::class, 'Cannot transition');
        });
    });

    describe('canAcceptPayments', function (): void {
        it('returns true only for active merchants', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );
            $merchantId = $result['merchant_id'];

            expect($this->service->canAcceptPayments($merchantId))->toBeFalse();

            $this->service->startReview($merchantId, 'reviewer-1');
            expect($this->service->canAcceptPayments($merchantId))->toBeFalse();

            $this->service->approve($merchantId, 'approver-1');
            expect($this->service->canAcceptPayments($merchantId))->toBeFalse();

            $this->service->activate($merchantId);
            expect($this->service->canAcceptPayments($merchantId))->toBeTrue();
        });
    });

    describe('getStatusHistory', function (): void {
        it('tracks status changes', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );
            $merchantId = $result['merchant_id'];

            $this->service->startReview($merchantId, 'reviewer-1');
            $this->service->approve($merchantId, 'approver-1');

            $history = $this->service->getStatusHistory($merchantId);

            expect($history)->toHaveCount(3);
            expect($history[0]['status'])->toBe('pending');
            expect($history[1]['status'])->toBe('under_review');
            expect($history[2]['status'])->toBe('approved');
        });
    });

    describe('assessRisk', function (): void {
        it('identifies high-risk business categories', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Crypto Exchange',
                businessType: 'crypto',
                country: 'US',
                contactEmail: 'test@crypto.com',
            );

            $assessment = $this->service->assessRisk($result['merchant_id']);

            expect($assessment['risk_score'])->toBeGreaterThan(0);
            expect($assessment['risk_factors'])->toContain('High-risk business category');
        });

        it('identifies high-risk jurisdictions', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Test Shop',
                businessType: 'retail',
                country: 'IR', // Iran
                contactEmail: 'test@shop.com',
            );

            $assessment = $this->service->assessRisk($result['merchant_id']);

            expect($assessment['risk_score'])->toBeGreaterThanOrEqual(0.4);
            expect($assessment['risk_factors'])->toContain('High-risk jurisdiction');
        });

        it('recommends approval for low-risk merchants', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Normal Shop',
                businessType: 'retail',
                country: 'US',
                contactEmail: 'test@shop.com',
            );

            $assessment = $this->service->assessRisk($result['merchant_id']);

            expect($assessment['risk_score'])->toBe(0.0);
            expect($assessment['recommendation'])->toBe('approve');
        });

        it('recommends rejection for very high-risk merchants', function (): void {
            $result = $this->service->submitApplication(
                businessName: 'Gambling Site',
                businessType: 'gambling',
                country: 'KP', // North Korea
                contactEmail: 'test@gambling.com',
            );

            $assessment = $this->service->assessRisk($result['merchant_id']);

            expect($assessment['risk_score'])->toBeGreaterThanOrEqual(0.7);
            expect($assessment['recommendation'])->toBe('reject');
        });
    });

    describe('getMerchant', function (): void {
        it('throws for non-existent merchant', function (): void {
            expect(fn () => $this->service->getMerchant('non-existent'))
                ->toThrow(InvalidArgumentException::class, 'Merchant not found');
        });
    });
});
