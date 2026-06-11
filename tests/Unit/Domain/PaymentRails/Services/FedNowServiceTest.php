<?php

declare(strict_types=1);

use App\Domain\PaymentRails\Services\FedNowService;
use Tests\TestCase;

uses(TestCase::class);

describe('FedNowService structural tests', function (): void {
    it('class exists', function (): void {
        expect(class_exists(FedNowService::class))->toBeTrue();
    });

    it('has a sendInstantPayment method', function (): void {
        expect((new ReflectionClass(FedNowService::class))->hasMethod('sendInstantPayment'))->toBeTrue();
    });

    it('sendInstantPayment has 7 parameters with last one nullable', function (): void {
        $reflection = new ReflectionMethod(FedNowService::class, 'sendInstantPayment');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(7);

        $lastParam = $params[6];
        expect($lastParam->allowsNull())->toBeTrue();
        expect($lastParam->isOptional())->toBeTrue();
        expect($lastParam->getDefaultValue())->toBeNull();
    });

    it('has a processStatusReport method', function (): void {
        expect((new ReflectionClass(FedNowService::class))->hasMethod('processStatusReport'))->toBeTrue();
    });

    it('processStatusReport has 1 parameter', function (): void {
        $reflection = new ReflectionMethod(FedNowService::class, 'processStatusReport');
        expect($reflection->getParameters())->toHaveCount(1);
    });

    it('has a getPaymentStatus method', function (): void {
        expect((new ReflectionClass(FedNowService::class))->hasMethod('getPaymentStatus'))->toBeTrue();
    });

    it('is buildable by the container without arguments', function (): void {
        // Regression guard: the old constructor injected Pacs008/Pacs002 value
        // objects as "templates", which the container cannot build (Pacs008
        // requires a string $messageId). That broke every consumer resolved
        // through the container — including GraphQL schema introspection,
        // where Lighthouse constructs all @field resolvers.
        expect(app(FedNowService::class))->toBeInstanceOf(FedNowService::class);
    });
});
