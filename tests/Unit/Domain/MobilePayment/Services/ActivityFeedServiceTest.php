<?php

declare(strict_types=1);

use App\Domain\MobilePayment\Enums\ActivityItemType;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Domain\MobilePayment\Services\ActivityFeedService;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function (): void {
    Illuminate\Support\Facades\Event::fake();
});

describe('ActivityFeedService', function (): void {
    it('can be instantiated', function (): void {
        $service = new ActivityFeedService();

        expect($service)->toBeInstanceOf(ActivityFeedService::class);
    });

    it('decodes invalid cursor gracefully', function (): void {
        $service = new ActivityFeedService();

        // Use reflection to test private decodeCursor
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('decodeCursor');
        $method->setAccessible(true);

        // Invalid base64
        expect($method->invoke($service, 'not-valid-base64!!!'))->toBeNull();

        // Valid base64 but invalid JSON structure
        expect($method->invoke($service, base64_encode('{}')))->toBeNull();
        expect($method->invoke($service, base64_encode('{"x":1}')))->toBeNull();
    });

    it('encodes and decodes cursor correctly', function (): void {
        $service = new ActivityFeedService();
        $reflection = new ReflectionClass($service);

        $encodeMethod = $reflection->getMethod('encodeCursor');
        $encodeMethod->setAccessible(true);

        $decodeMethod = $reflection->getMethod('decodeCursor');
        $decodeMethod->setAccessible(true);

        $item = new ActivityFeedItem();
        $item->id = 'test-uuid-123';
        $item->occurred_at = Carbon\Carbon::parse('2026-02-07T10:00:00Z');

        $cursor = $encodeMethod->invoke($service, $item);
        expect($cursor)->toBeString();

        $decoded = $decodeMethod->invoke($service, $cursor);
        expect($decoded)->toBeArray();
        expect($decoded['id'])->toBe('test-uuid-123');
        expect($decoded['occurred_at'])->toContain('2026-02-07');
    });
});

describe('ActivityItemType Enum', function (): void {
    it('correctly identifies outflow types', function (): void {
        expect(ActivityItemType::MERCHANT_PAYMENT->isOutflow())->toBeTrue();
        expect(ActivityItemType::TRANSFER_OUT->isOutflow())->toBeTrue();
        expect(ActivityItemType::SHIELD->isOutflow())->toBeTrue();
        expect(ActivityItemType::TRANSFER_IN->isOutflow())->toBeFalse();
        expect(ActivityItemType::UNSHIELD->isOutflow())->toBeFalse();
    });

    it('correctly identifies inflow types', function (): void {
        expect(ActivityItemType::TRANSFER_IN->isInflow())->toBeTrue();
        expect(ActivityItemType::UNSHIELD->isInflow())->toBeTrue();
        expect(ActivityItemType::MERCHANT_PAYMENT->isInflow())->toBeFalse();
    });

    it('returns correct filter groups', function (): void {
        expect(ActivityItemType::MERCHANT_PAYMENT->filterGroup())->toBe('expenses');
        expect(ActivityItemType::TRANSFER_OUT->filterGroup())->toBe('expenses');
        expect(ActivityItemType::TRANSFER_IN->filterGroup())->toBe('income');
        expect(ActivityItemType::UNSHIELD->filterGroup())->toBe('income');
    });
});
