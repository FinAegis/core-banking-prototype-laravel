<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402SpendingLimit;
use Database\Seeders\SmsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

it('creates the SMS monetized endpoint', function () {
    (new SmsDemoSeeder())->run();

    /** @var X402MonetizedEndpoint $endpoint */
    $endpoint = X402MonetizedEndpoint::where('path', '/api/v1/sms/send')->firstOrFail();

    expect($endpoint->method)->toBe('POST');
    expect($endpoint->price)->toBe('50000');
    expect($endpoint->network)->toBe('eip155:8453');
    expect($endpoint->asset)->toBe('USDC');
    expect($endpoint->scheme)->toBe('exact');
    expect($endpoint->is_active)->toBeTrue();
    expect($endpoint->extra)->toHaveKey('provider', 'vertexsms');
});

it('creates a demo SMS spending limit', function () {
    (new SmsDemoSeeder())->run();

    /** @var X402SpendingLimit $limit */
    $limit = X402SpendingLimit::where('agent_id', 'demo-sms-agent')
        ->where('agent_type', 'sms')
        ->firstOrFail();

    expect($limit->daily_limit)->toBe('10000000');
    expect($limit->per_transaction_limit)->toBe('500000');
    expect($limit->auto_pay_enabled)->toBeTrue();
    expect($limit->spent_today)->toBe('0');
});

it('seeds sample SMS messages in all four statuses', function () {
    (new SmsDemoSeeder())->run();

    $delivered = SmsMessage::where('status', SmsMessage::STATUS_DELIVERED)->count();
    $sent = SmsMessage::where('status', SmsMessage::STATUS_SENT)->count();
    $failed = SmsMessage::where('status', SmsMessage::STATUS_FAILED)->count();
    $pending = SmsMessage::where('status', SmsMessage::STATUS_PENDING)->count();

    expect($delivered)->toBeGreaterThanOrEqual(2);
    expect($sent)->toBeGreaterThanOrEqual(2);
    expect($failed)->toBeGreaterThanOrEqual(1);
    expect($pending)->toBeGreaterThanOrEqual(1);
});

it('includes a multi-part SMS message', function () {
    (new SmsDemoSeeder())->run();

    /** @var SmsMessage $multiPart */
    $multiPart = SmsMessage::where('parts', '>', 1)->firstOrFail();

    expect($multiPart->parts)->toBe(2);
    expect((int) $multiPart->price_usdc)->toBeGreaterThan(50000);
});

it('is idempotent on repeated runs', function () {
    $seeder = new SmsDemoSeeder();
    $seeder->run();
    $seeder->run();

    $endpointCount = X402MonetizedEndpoint::where('path', '/api/v1/sms/send')->count();
    $limitCount = X402SpendingLimit::where('agent_id', 'demo-sms-agent')->count();
    $msgCount = SmsMessage::where('provider_id', 'like', 'demo-vtx-%')->count();

    expect($endpointCount)->toBe(1);
    expect($limitCount)->toBe(1);
    expect($msgCount)->toBe(7);
});
