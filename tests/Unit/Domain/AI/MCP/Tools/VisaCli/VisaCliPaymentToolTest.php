<?php

declare(strict_types=1);

use App\Domain\AI\MCP\Tools\VisaCli\VisaCliPaymentTool;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Services\VisaCliPaymentService;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['visacli.enabled' => true]);

    /** @var VisaCliPaymentService&Mockery\MockInterface $paymentService */
    $this->paymentService = Mockery::mock(VisaCliPaymentService::class);
    $this->tool = new VisaCliPaymentTool($this->paymentService);
});

it('has correct name and category', function (): void {
    expect($this->tool->getName())->toBe('visacli.payment')
        ->and($this->tool->getCategory())->toBe('visacli')
        ->and($this->tool->isCacheable())->toBeFalse();
});

it('validates input with required fields', function (): void {
    expect($this->tool->validateInput([]))->toBeFalse()
        ->and($this->tool->validateInput(['agent_id' => 'a']))->toBeFalse()
        ->and($this->tool->validateInput([
            'agent_id'         => 'agent-1',
            'url'              => 'https://api.example.com',
            'max_amount_cents' => 500,
        ]))->toBeTrue();
});

it('rejects invalid URL in validation', function (): void {
    expect($this->tool->validateInput([
        'agent_id'         => 'agent-1',
        'url'              => 'not-a-url',
        'max_amount_cents' => 500,
    ]))->toBeFalse();
});

it('executes payment and returns success', function (): void {
    $this->paymentService->shouldReceive('executePayment')
        ->once()
        ->andReturn(new VisaCliPaymentResult(
            paymentReference: 'mcp_ref_001',
            status: VisaCliPaymentStatus::COMPLETED,
            amountCents: 500,
            currency: 'USD',
            url: 'https://api.example.com',
            cardLast4: '4242',
        ));

    $result = $this->tool->execute([
        'agent_id'         => 'test-agent',
        'url'              => 'https://api.example.com',
        'max_amount_cents' => 500,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['payment_reference'])->toBe('mcp_ref_001')
        ->and($result->getData()['status'])->toBe('completed');
});

it('returns failure on payment exception', function (): void {
    $this->paymentService->shouldReceive('executePayment')
        ->once()
        ->andThrow(new App\Domain\VisaCli\Exceptions\VisaCliPaymentException('Spending limit exceeded'));

    $result = $this->tool->execute([
        'agent_id'         => 'test-agent',
        'url'              => 'https://api.example.com',
        'max_amount_cents' => 50000,
    ]);

    expect($result->isFailure())->toBeTrue()
        ->and($result->getError())->toContain('Spending limit exceeded');
});

it('authorizes all users', function (): void {
    expect($this->tool->authorize(null))->toBeTrue()
        ->and($this->tool->authorize('user-1'))->toBeTrue();
});

it('has correct capabilities', function (): void {
    $caps = $this->tool->getCapabilities();

    expect($caps)->toContain('write')
        ->and($caps)->toContain('payment')
        ->and($caps)->toContain('visa-cli');
});
