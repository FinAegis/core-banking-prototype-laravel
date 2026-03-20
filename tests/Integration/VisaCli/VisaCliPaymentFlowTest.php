<?php

declare(strict_types=1);

use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Models\VisaCliPayment;
use App\Domain\VisaCli\Services\DemoVisaCliClient;
use App\Domain\VisaCli\Services\VisaCliPaymentService;
use App\Domain\VisaCli\Services\VisaCliSpendingLimitService;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['visacli.enabled' => true]);
    config(['visacli.spending_limits.daily' => 50000]);
    config(['visacli.spending_limits.per_tx' => 10000]);

    $this->client = new DemoVisaCliClient();
    $this->spendingService = new VisaCliSpendingLimitService();
    $this->paymentService = new VisaCliPaymentService($this->client, $this->spendingService);
});

it('completes an end-to-end payment flow', function (): void {
    $request = new VisaCliPaymentRequest(
        agentId: 'flow-agent',
        url: 'https://api.imagegeneration.com/generate',
        amountCents: 250,
        purpose: 'image_generation',
    );

    $result = $this->paymentService->executePayment($request);

    // Payment succeeded
    expect($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(250)
        ->and($result->url)->toBe('https://api.imagegeneration.com/generate');

    // Payment recorded in database
    $payment = VisaCliPayment::where('agent_id', 'flow-agent')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->url)->toBe('https://api.imagegeneration.com/generate')
        ->and($payment->isCompleted())->toBeTrue();

    // Spending limit updated
    $limit = $this->spendingService->getOrCreateLimit('flow-agent');
    expect($limit->spent_today)->toBe(250)
        ->and($limit->remainingDailyBudget())->toBe(49750);
});

it('handles multiple sequential payments', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $request = new VisaCliPaymentRequest(
            agentId: 'multi-agent',
            url: "https://api.example.com/resource/{$i}",
            amountCents: 100,
        );

        $this->paymentService->executePayment($request);
    }

    $payments = $this->paymentService->getAgentPayments('multi-agent');
    expect($payments)->toHaveCount(5);

    $limit = $this->spendingService->getOrCreateLimit('multi-agent');
    expect($limit->spent_today)->toBe(500);
});

it('blocks payment when daily limit reached', function (): void {
    // Use up most of the budget
    config(['visacli.spending_limits.daily' => 1000]);

    $request1 = new VisaCliPaymentRequest(
        agentId: 'limited-agent',
        url: 'https://api.example.com/1',
        amountCents: 800,
    );
    $this->paymentService->executePayment($request1);

    // This should fail — only 200 left, requesting 500
    $request2 = new VisaCliPaymentRequest(
        agentId: 'limited-agent',
        url: 'https://api.example.com/2',
        amountCents: 500,
    );

    expect(fn () => $this->paymentService->executePayment($request2))
        ->toThrow(App\Domain\VisaCli\Exceptions\VisaCliPaymentException::class);
});
