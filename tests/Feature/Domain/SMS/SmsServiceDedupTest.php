<?php

declare(strict_types=1);

use App\Domain\SMS\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.enabled'                       => true,
        'sms.default_provider'              => 'vertexsms',
        'sms.defaults.test_mode'            => true,
        'sms.defaults.send_interval_ms'     => 0,
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.webhook.dlr_url'               => 'https://example.com/dlr',
        'sms.webhook.dlr_url_token'         => '',
        'cache.default'                     => 'array',
    ]);
    Cache::flush();
});

describe('SmsService dedup guard', function (): void {
    it('rejects duplicate send within 60s window', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response([[
                'parts'        => 1, 'countryISO' => 'LT', 'mccmnc' => '24601',
                'pricePerPart' => 0.035, 'totalPrice' => 0.035, 'currency' => 'EUR',
            ]], 200),
            'kube-api.vertexsms.com/sms' => Http::response(['dedup-msg-1'], 200),
        ]);

        $service = app(SmsService::class);

        // First send succeeds
        $result = $service->send('+37069912345', 'Zelta', 'Hello dedup');
        expect($result['message_id'])->toBe('dedup-msg-1');

        // Duplicate within 60s throws
        expect(fn () => $service->send('+37069912345', 'Zelta', 'Hello dedup'))
            ->toThrow(RuntimeException::class, 'Duplicate SMS detected');
    });

    it('allows same message to different recipients', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response([[
                'parts'        => 1, 'countryISO' => 'LT', 'mccmnc' => '24601',
                'pricePerPart' => 0.035, 'totalPrice' => 0.035, 'currency' => 'EUR',
            ]], 200),
            'kube-api.vertexsms.com/sms' => Http::sequence()
                ->push(['msg-a'], 200)
                ->push(['msg-b'], 200),
        ]);

        $service = app(SmsService::class);

        $a = $service->send('+37069912345', 'Zelta', 'Same message');
        $b = $service->send('+37069900000', 'Zelta', 'Same message');

        expect($a['message_id'])->toBe('msg-a');
        expect($b['message_id'])->toBe('msg-b');
    });
});
