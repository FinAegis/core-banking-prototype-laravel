<?php

declare(strict_types=1);

use App\Domain\VisaCli\Models\VisaCliSpendingLimit;
use App\Domain\VisaCli\Services\VisaCliSpendingLimitService;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['visacli.spending_limits.daily' => 10000]);
    config(['visacli.spending_limits.per_tx' => 1000]);
    config(['visacli.spending_limits.auto_pay' => false]);

    $this->service = new VisaCliSpendingLimitService();
});

it('creates a default limit for a new agent', function (): void {
    $limit = $this->service->getOrCreateLimit('new-agent');

    expect($limit->agent_id)->toBe('new-agent')
        ->and($limit->daily_limit)->toBe(10000)
        ->and($limit->per_transaction_limit)->toBe(1000)
        ->and($limit->spent_today)->toBe(0)
        ->and($limit->auto_pay_enabled)->toBeFalse();
});

it('allows spending within budget', function (): void {
    expect($this->service->canSpend('agent-1', 500))->toBeTrue()
        ->and($this->service->canSpend('agent-1', 10001))->toBeFalse();
});

it('records spending and reduces budget', function (): void {
    $this->service->recordSpending('agent-2', 3000);

    $limit = $this->service->getOrCreateLimit('agent-2');
    expect($limit->spent_today)->toBe(3000)
        ->and($limit->remainingDailyBudget())->toBe(7000);
});

it('denies spending when budget exhausted', function (): void {
    $this->service->recordSpending('agent-3', 9500);

    expect($this->service->canSpend('agent-3', 600))->toBeFalse()
        ->and($this->service->canSpend('agent-3', 500))->toBeTrue();
});

it('updates spending limits', function (): void {
    $limit = $this->service->updateLimit(
        agentId: 'agent-4',
        dailyLimit: 50000,
        perTransactionLimit: 5000,
        autoPayEnabled: true,
    );

    expect($limit->daily_limit)->toBe(50000)
        ->and($limit->per_transaction_limit)->toBe(5000)
        ->and($limit->auto_pay_enabled)->toBeTrue();
});

it('auto-pay respects per-transaction limit', function (): void {
    $this->service->updateLimit('agent-5', autoPayEnabled: true, perTransactionLimit: 500);

    expect($this->service->canAutoPay('agent-5', 400))->toBeTrue()
        ->and($this->service->canAutoPay('agent-5', 600))->toBeFalse();
});

it('auto-pay disabled returns false', function (): void {
    expect($this->service->canAutoPay('agent-6', 100))->toBeFalse();
});

it('resets daily budget after reset time', function (): void {
    $this->service->recordSpending('agent-7', 5000);

    // Set reset time to the past
    VisaCliSpendingLimit::where('agent_id', 'agent-7')
        ->update(['limit_resets_at' => now()->subHour()]);

    expect($this->service->canSpend('agent-7', 10000))->toBeTrue();
});

it('reports spent percentage correctly', function (): void {
    $this->service->recordSpending('agent-8', 2500);

    $limit = $this->service->getOrCreateLimit('agent-8');
    expect($limit->spentPercentage())->toBe(25.0);
});
