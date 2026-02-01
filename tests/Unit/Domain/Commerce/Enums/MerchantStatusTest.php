<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\MerchantStatus;

describe('MerchantStatus Enum', function (): void {
    it('has all expected statuses', function (): void {
        $statuses = MerchantStatus::cases();

        expect($statuses)->toHaveCount(6);
        expect(MerchantStatus::PENDING->value)->toBe('pending');
        expect(MerchantStatus::UNDER_REVIEW->value)->toBe('under_review');
        expect(MerchantStatus::APPROVED->value)->toBe('approved');
        expect(MerchantStatus::ACTIVE->value)->toBe('active');
        expect(MerchantStatus::SUSPENDED->value)->toBe('suspended');
        expect(MerchantStatus::TERMINATED->value)->toBe('terminated');
    });

    it('returns correct labels', function (): void {
        expect(MerchantStatus::PENDING->label())->toBe('Pending');
        expect(MerchantStatus::UNDER_REVIEW->label())->toBe('Under Review');
        expect(MerchantStatus::ACTIVE->label())->toBe('Active');
    });

    it('correctly identifies payment capability', function (): void {
        expect(MerchantStatus::PENDING->canAcceptPayments())->toBeFalse();
        expect(MerchantStatus::UNDER_REVIEW->canAcceptPayments())->toBeFalse();
        expect(MerchantStatus::APPROVED->canAcceptPayments())->toBeFalse();
        expect(MerchantStatus::ACTIVE->canAcceptPayments())->toBeTrue();
        expect(MerchantStatus::SUSPENDED->canAcceptPayments())->toBeFalse();
        expect(MerchantStatus::TERMINATED->canAcceptPayments())->toBeFalse();
    });

    it('validates state transitions correctly', function (): void {
        // Pending can go to under_review or terminated
        expect(MerchantStatus::PENDING->canTransitionTo(MerchantStatus::UNDER_REVIEW))->toBeTrue();
        expect(MerchantStatus::PENDING->canTransitionTo(MerchantStatus::TERMINATED))->toBeTrue();
        expect(MerchantStatus::PENDING->canTransitionTo(MerchantStatus::ACTIVE))->toBeFalse();

        // Active can go to suspended or terminated
        expect(MerchantStatus::ACTIVE->canTransitionTo(MerchantStatus::SUSPENDED))->toBeTrue();
        expect(MerchantStatus::ACTIVE->canTransitionTo(MerchantStatus::TERMINATED))->toBeTrue();
        expect(MerchantStatus::ACTIVE->canTransitionTo(MerchantStatus::PENDING))->toBeFalse();

        // Terminated cannot transition
        expect(MerchantStatus::TERMINATED->canTransitionTo(MerchantStatus::ACTIVE))->toBeFalse();
    });

    it('returns allowed transitions', function (): void {
        $pendingTransitions = MerchantStatus::PENDING->allowedTransitions();
        expect($pendingTransitions)->toContain(MerchantStatus::UNDER_REVIEW);
        expect($pendingTransitions)->toContain(MerchantStatus::TERMINATED);

        $terminatedTransitions = MerchantStatus::TERMINATED->allowedTransitions();
        expect($terminatedTransitions)->toBeEmpty();
    });
});
