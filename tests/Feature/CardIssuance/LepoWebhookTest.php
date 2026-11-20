<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Jobs\ProcessLepoWebhook;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['cardissuance.issuers.lepo.webhook_secret' => 'whsec_test']);
    Queue::fake();
});

/**
 * @param  array<string, mixed>|string  $payload
 * @return TestResponse<Illuminate\Http\Response>
 */
function postLepoWebhook(mixed $test, array|string $payload, ?string $signature = null): TestResponse
{
    $body = is_string($payload) ? $payload : (string) json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, 'whsec_test');

    return $test->call('POST', '/api/v1/webhooks/lepo', [], [], [], [
        'CONTENT_TYPE'                 => 'application/json',
        'HTTP_X_LEPOFINANCE_SIGNATURE' => $signature,
    ], $body);
}

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function lepoEvent(string $id, string $type, array $data = []): array
{
    return ['id' => $id, 'type' => $type, 'domain' => 'card', 'provider' => 'interlace', 'timestamp' => '2026-11-20T12:00:00Z', 'data' => $data];
}

it('accepts a signed event and queues it once', function (): void {
    postLepoWebhook($this, lepoEvent('evt_1', 'card.created', ['cardId' => '42']))
        ->assertOk()
        ->assertJson(['success' => true]);

    Queue::assertPushed(ProcessLepoWebhook::class, 1);
    expect(ProcessedWebhookEvent::query()->where('provider', 'lepo')->where('event_id', 'evt_1')->count())->toBe(1);
});

it('dispatches one job for a duplicate delivery', function (): void {
    $event = lepoEvent('evt_dup', 'card.topup', ['cardId' => '42']);

    postLepoWebhook($this, $event)->assertOk();
    postLepoWebhook($this, $event)->assertOk();

    Queue::assertPushed(ProcessLepoWebhook::class, 1);
});

it('rejects a bad signature', function (): void {
    postLepoWebhook($this, lepoEvent('evt_bad', 'card.created'), str_repeat('0', 64))->assertStatus(401);

    Queue::assertNothingPushed();
    expect(ProcessedWebhookEvent::query()->where('provider', 'lepo')->count())->toBe(0);
});

it('rejects an unsigned event in production', function (): void {
    config(['cardissuance.issuers.lepo.webhook_secret' => '']);
    app()->detectEnvironment(fn (): string => 'production');

    postLepoWebhook($this, lepoEvent('evt_prod', 'card.created'), '')->assertStatus(401);

    Queue::assertNothingPushed();
});

it('rejects malformed bodies', function (string $body): void {
    postLepoWebhook($this, $body)->assertStatus(400);

    Queue::assertNothingPushed();
    expect(ProcessedWebhookEvent::query()->where('provider', 'lepo')->count())->toBe(0);
})->with([
    'not json'     => ['{"id": "evt_x", '],
    'missing id'   => ['{"type":"card.created","data":{}}'],
    'missing type' => ['{"id":"evt_y","data":{}}'],
]);

it('never writes the 3-D Secure code to the log or the job payload', function (): void {
    $log = Log::spy();

    postLepoWebhook($this, lepoEvent('evt_otp', 'card.3ds.otp', ['cardId' => '42', 'otp' => '654321', 'merchant' => 'Shop']))
        ->assertOk();

    Queue::assertPushed(ProcessLepoWebhook::class, function (ProcessLepoWebhook $job): bool {
        return ! str_contains(serialize($job), '654321');
    });
    $log->shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains((string) json_encode($context), '654321'));
    $log->shouldNotHaveReceived('warning', fn (string $message, array $context = []): bool => str_contains((string) json_encode($context), '654321'));
    expect(str_contains((string) json_encode(ProcessedWebhookEvent::query()->where('event_id', 'evt_otp')->first()?->toArray()), '654321'))->toBeFalse();
});

it('forgets the event when it cannot be queued, so the retry is processed', function (): void {
    $dispatcher = Mockery::mock(Illuminate\Contracts\Bus\Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('queue down'));
    app()->instance(Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

    postLepoWebhook($this, lepoEvent('evt_lost', 'wallet.topped_up', ['amount' => '10']))->assertStatus(500);

    expect(ProcessedWebhookEvent::query()->where('provider', 'lepo')->where('event_id', 'evt_lost')->count())->toBe(0);
});

it('logs rejected deliveries without their payload', function (): void {
    $log = Log::spy();

    postLepoWebhook($this, lepoEvent('evt_sig', 'kyc.verified', ['email' => 'person@example.com']), str_repeat('0', 64))->assertStatus(401);

    $log->shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'Lepo webhook')
        && ! str_contains((string) json_encode($context), 'person@example.com'))->once();
});

it('removes card secrets from every event type before queueing', function (): void {
    postLepoWebhook($this, lepoEvent('evt_card', 'card.created', ['card' => ['cardNumber' => '4111111111111111', 'cvv' => '321']]))->assertOk();

    Queue::assertPushed(ProcessLepoWebhook::class, function (ProcessLepoWebhook $job): bool {
        $payload = serialize($job);

        return ! str_contains($payload, '4111111111111111') && ! str_contains($payload, '321');
    });
});

it('removes a nested 3-D Secure code from an auth request', function (): void {
    postLepoWebhook($this, lepoEvent('evt_auth', 'card.3ds_auth_request', ['challenge' => ['passcode' => '778899']]))->assertOk();

    Queue::assertPushed(ProcessLepoWebhook::class, fn (ProcessLepoWebhook $job): bool => ! str_contains(serialize($job), '778899'));
});

it('keeps lepo webhooks out of telescope', function (): void {
    expect(config('telescope.ignore_paths'))->toContain('api/v1/webhooks/lepo');
});
