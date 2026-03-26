<?php

declare(strict_types=1);

use App\Domain\AgentProtocol\Enums\A2ATaskState;

describe('A2ATaskState', function (): void {
    it('has all required states from A2A spec', function (): void {
        expect(A2ATaskState::cases())->toHaveCount(6);
        expect(A2ATaskState::SUBMITTED->value)->toBe('submitted');
        expect(A2ATaskState::WORKING->value)->toBe('working');
        expect(A2ATaskState::INPUT_REQUIRED->value)->toBe('input-required');
        expect(A2ATaskState::COMPLETED->value)->toBe('completed');
        expect(A2ATaskState::CANCELED->value)->toBe('canceled');
        expect(A2ATaskState::FAILED->value)->toBe('failed');
    });

    it('identifies terminal states', function (): void {
        expect(A2ATaskState::COMPLETED->isTerminal())->toBeTrue();
        expect(A2ATaskState::CANCELED->isTerminal())->toBeTrue();
        expect(A2ATaskState::FAILED->isTerminal())->toBeTrue();
        expect(A2ATaskState::WORKING->isTerminal())->toBeFalse();
        expect(A2ATaskState::SUBMITTED->isTerminal())->toBeFalse();
    });

    it('validates allowed transitions', function (): void {
        expect(A2ATaskState::SUBMITTED->canTransitionTo(A2ATaskState::WORKING))->toBeTrue();
        expect(A2ATaskState::WORKING->canTransitionTo(A2ATaskState::COMPLETED))->toBeTrue();
        expect(A2ATaskState::COMPLETED->canTransitionTo(A2ATaskState::WORKING))->toBeFalse();
    });
});
