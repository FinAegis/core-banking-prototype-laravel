<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.defaults.expire_seconds'       => 259200,
        'sms.defaults.send_interval_ms'     => 0,
        'sms.webhook.dlr_url'               => '',
        'sms.webhook.dlr_url_token'         => '',
        'cache.default'                     => 'array',
    ]);
});

describe('sms:reconcile command', function (): void {
    it('reconciles stale sent messages past expiration window', function (): void {
        $sms = SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'recon-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Reconcile test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);
        $sms->forceFill(['created_at' => now()->subDays(4)])->saveQuietly();

        Http::fake([
            'kube-api.vertexsms.com/sms/status/recon-001*' => Http::response([
                'id'     => 'recon-001',
                'status' => 1,
                'error'  => 0,
            ], 200),
        ]);

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        $sms->refresh();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });

    it('marks undelivered messages as failed', function (): void {
        $sms = SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'recon-002',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Failed reconcile',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);
        $sms->forceFill(['created_at' => now()->subDays(4)])->saveQuietly();

        Http::fake([
            'kube-api.vertexsms.com/sms/status/recon-002*' => Http::response([
                'id'     => 'recon-002',
                'status' => 2,
                'error'  => 2,
            ], 200),
        ]);

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        $sms->refresh();
        expect($sms->status)->toBe(SmsMessage::STATUS_FAILED);
        expect($sms->error_code)->toBe(2);
    });

    it('skips messages within the expiration window', function (): void {
        $sms = SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'recon-003',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Too recent',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);
        $sms->forceFill(['created_at' => now()->subHours(1)])->saveQuietly();

        Http::fake();

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        Http::assertNothingSent();
    });

    it('does nothing when no stale messages exist', function (): void {
        Http::fake();

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        Http::assertNothingSent();
    });
});
