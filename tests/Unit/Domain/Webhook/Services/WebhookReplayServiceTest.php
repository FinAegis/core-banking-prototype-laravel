<?php

declare(strict_types=1);

use App\Domain\Webhook\Services\WebhookReplayService;
use App\Domain\Webhook\Services\WebhookService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('signs a replay exactly like a first delivery', function (): void {
    Http::fake(['https://partner.example.com/*' => Http::response('', 200)]);

    $payload = '{"event":"payment.completed","id":"evt_1"}';
    $secret = 'whsec_test';

    $result = app(WebhookReplayService::class)->replay('dlv_1', 'https://partner.example.com/hook', $payload, $secret);

    $expected = app(WebhookService::class)->generateSignature($payload, $secret);

    expect($result['replayed'])->toBeTrue()
        ->and($expected)->toStartWith('sha256=');

    Http::assertSent(fn (Request $request): bool => $request->header('X-Webhook-Signature') === [$expected]
        && $request->body() === $payload);
});

it('sends no signature header without a secret', function (): void {
    Http::fake(['https://partner.example.com/*' => Http::response('', 200)]);

    app(WebhookReplayService::class)->replay('dlv_2', 'https://partner.example.com/hook', '{}');

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('X-Webhook-Signature'));
});
