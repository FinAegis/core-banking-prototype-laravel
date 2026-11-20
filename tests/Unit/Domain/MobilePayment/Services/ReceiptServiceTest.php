<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

use App\Domain\MobilePayment\Services\ReceiptService;

describe('ReceiptService', function (): void {
    it('can be instantiated', function (): void {
        $service = new ReceiptService();

        expect($service)->toBeInstanceOf(ReceiptService::class);
    });

    it('has generateReceipt method with correct parameters', function (): void {
        $service = new ReceiptService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateReceipt');

        expect($method->isPublic())->toBeTrue();
        expect($method->getNumberOfParameters())->toBe(2);

        $params = $method->getParameters();
        expect($params[0]->getName())->toBe('txId');
        expect($params[0]->getType()->getName())->toBe('string');
        expect($params[1]->getName())->toBe('userId');
        expect($params[1]->getType()->getName())->toBe('int');
    });

    it('has getReceipt method', function (): void {
        $service = new ReceiptService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getReceipt');

        expect($method->isPublic())->toBeTrue();
        expect($method->getNumberOfParameters())->toBe(2);
    });

    it('has getReceiptByShareToken method', function (): void {
        $service = new ReceiptService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getReceiptByShareToken');

        expect($method->isPublic())->toBeTrue();
        expect($method->getNumberOfParameters())->toBe(1);

        $params = $method->getParameters();
        expect($params[0]->getName())->toBe('shareToken');
    });

    it('formats network fee with default when no estimate', function (): void {
        $service = new ReceiptService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatNetworkFee');

        // Create a mock intent with no fees_estimate
        $intent = new App\Domain\MobilePayment\Models\PaymentIntent();
        $intent->fees_estimate = null;

        $fee = $method->invoke($service, $intent);

        expect($fee)->toBe('0.01 USD');
    });

    it('formats network fee from usdApprox estimate', function (): void {
        $service = new ReceiptService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatNetworkFee');

        $intent = new App\Domain\MobilePayment\Models\PaymentIntent();
        $intent->fees_estimate = ['usdApprox' => '0.50'];

        $fee = $method->invoke($service, $intent);

        expect($fee)->toBe('0.50 USD');
    });

    it('formats network fee without usdApprox key as default', function (): void {
        $service = new ReceiptService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatNetworkFee');

        $intent = new App\Domain\MobilePayment\Models\PaymentIntent();
        $intent->fees_estimate = ['total' => '0.01'];

        $fee = $method->invoke($service, $intent);

        expect($fee)->toBe('0.01 USD');
    });
});
