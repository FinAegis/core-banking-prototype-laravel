<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

describe('SmsService DLR error-24 alerting', function (): void {
    it('logs critical when error code 24 (balance exhausted) arrives', function (): void {
        Log::shouldReceive('critical')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'balance')
                    && ($context['error_code'] ?? null) === 24;
            });
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'err24-msg-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Balance test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        app(SmsService::class)->handleDeliveryReport([
            'message_id' => 'err24-msg-001',
            'raw_status' => 2,
            'error_code' => 24,
        ]);
    });

    it('does not log critical for other error codes', function (): void {
        Log::shouldReceive('critical')->never();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'err42-msg-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Other error test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        app(SmsService::class)->handleDeliveryReport([
            'message_id' => 'err42-msg-001',
            'raw_status' => 2,
            'error_code' => 42,
        ]);
    });
});
