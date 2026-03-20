<?php

declare(strict_types=1);

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use App\Domain\VisaCli\Models\VisaCliPayment;
use App\Domain\VisaCli\Services\VisaCliPaymentService;
use App\Domain\VisaCli\Services\VisaCliSpendingLimitService;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['visacli.enabled' => true]);

    /** @var VisaCliClientInterface&Mockery\MockInterface $client */
    $this->client = Mockery::mock(VisaCliClientInterface::class);

    $this->spendingService = new VisaCliSpendingLimitService();
    $this->service = new VisaCliPaymentService($this->client, $this->spendingService);
});

it('executes a payment successfully', function (): void {
    $this->client->shouldReceive('pay')
        ->once()
        ->andReturn(new VisaCliPaymentResult(
            paymentReference: 'ref_123',
            status: VisaCliPaymentStatus::COMPLETED,
            amountCents: 500,
            currency: 'USD',
            url: 'https://api.example.com',
            cardLast4: '4242',
        ));

    $request = new VisaCliPaymentRequest(
        agentId: 'test-agent',
        url: 'https://api.example.com',
        amountCents: 500,
    );

    $result = $this->service->executePayment($request);

    expect($result->paymentReference)->toBe('ref_123')
        ->and($result->status)->toBe(VisaCliPaymentStatus::COMPLETED)
        ->and($result->amountCents)->toBe(500);

    // Verify payment was recorded
    $payment = VisaCliPayment::where('agent_id', 'test-agent')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->payment_reference)->toBe('ref_123');
});

it('throws when visa cli is disabled', function (): void {
    config(['visacli.enabled' => false]);

    $request = new VisaCliPaymentRequest(
        agentId: 'test-agent',
        url: 'https://api.example.com',
        amountCents: 500,
    );

    $this->service->executePayment($request);
})->throws(VisaCliPaymentException::class, 'not enabled');

it('throws when spending limit exceeded', function (): void {
    // Set a very low limit
    $this->spendingService->updateLimit('test-agent', dailyLimit: 100);

    $request = new VisaCliPaymentRequest(
        agentId: 'test-agent',
        url: 'https://api.example.com',
        amountCents: 500,
    );

    $this->service->executePayment($request);
})->throws(VisaCliPaymentException::class, 'Spending limit exceeded');

it('records spending after successful payment', function (): void {
    $this->client->shouldReceive('pay')
        ->once()
        ->andReturn(new VisaCliPaymentResult(
            paymentReference: 'ref_456',
            status: VisaCliPaymentStatus::COMPLETED,
            amountCents: 300,
            currency: 'USD',
            url: 'https://api.example.com',
        ));

    $request = new VisaCliPaymentRequest(
        agentId: 'spending-agent',
        url: 'https://api.example.com',
        amountCents: 300,
    );

    $this->service->executePayment($request);

    $limit = $this->spendingService->getOrCreateLimit('spending-agent');
    expect($limit->spent_today)->toBe(300);
});

it('retrieves agent payments', function (): void {
    VisaCliPayment::create([
        'agent_id'          => 'test-agent',
        'url'               => 'https://example.com',
        'amount_cents'      => 500,
        'currency'          => 'USD',
        'status'            => VisaCliPaymentStatus::COMPLETED,
        'payment_reference' => 'ref_test',
    ]);

    $payments = $this->service->getAgentPayments('test-agent');

    expect($payments)->toHaveCount(1)
        ->and($payments->first()->agent_id)->toBe('test-agent');
});
