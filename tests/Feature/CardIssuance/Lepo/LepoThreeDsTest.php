<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardThreeDsCodeReady;
use App\Domain\CardIssuance\Jobs\ProcessLepoWebhook;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['cardissuance.issuers.lepo.webhook_secret' => 'whsec_3ds', 'cardissuance.issuers.lepo.base_url' => 'https://lepo.test/api/v2', 'cardissuance.issuers.lepo.api_key' => 'k']);
    $this->push = Mockery::mock(PushNotificationService::class);
    $this->push->shouldReceive('sendToUser')->byDefault()->andReturn([]);
    app()->instance(PushNotificationService::class, $this->push);
});

function tdsCard(?User $user = null): Card
{
    $user ??= User::factory()->create();
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();

    return Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'issuer' => 'lepo', 'status' => 'active', 'issuer_card_token' => '555', 'last4' => '1234', 'network' => 'visa']);
}

/**
 * @param  array<string, mixed>  $data
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function tdsPost(PHPUnit\Framework\TestCase $test, string $eventId, array $data, string $type = 'card.3ds.otp'): TestResponse
{
    assert($test instanceof Tests\TestCase);
    $body = (string) json_encode(['id' => $eventId, 'type' => $type, 'domain' => 'card', 'provider' => 'lepofinance', 'timestamp' => now()->toIso8601String(), 'data' => $data]);

    return $test->call('POST', '/api/v1/webhooks/lepo', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_LEPOFINANCE_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_3ds'),
    ], $body);
}

/**
 * @return array<string, mixed>
 */
function tdsData(string $otp = '493817', ?string $expiresAt = null): array
{
    return ['userId' => 12345, 'cardId' => 555, 'externalCardId' => null, 'otp' => $otp, 'expiresAt' => $expiresAt ?? now()->addMinutes(5)->toIso8601String()];
}

it('keeps the code encrypted until it expires', function (): void {
    $card = tdsCard();

    tdsPost($this, 'evt_3ds_1', tdsData())->assertOk();

    $raw = (string) Cache::get("cards:3ds:{$card->id}");
    expect($raw)->not->toBe('')->not->toContain('493817');
});

it('gives the owner the waiting code', function (): void {
    $user = User::factory()->create();
    $card = tdsCard($user);
    tdsPost($this, 'evt_3ds_2', tdsData('112233'));
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->getJson("/api/v1/cards/issued/{$card->id}/3ds/code")
        ->assertOk()
        ->assertJsonPath('data.code', '112233')
        ->assertJsonPath('data.merchant', null)
        ->assertJsonPath('data.amount', null)
        ->assertJsonPath('data.currency', null)
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('answers 404 when no code is waiting or it expired', function (): void {
    $user = User::factory()->create();
    $card = tdsCard($user);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->getJson("/api/v1/cards/issued/{$card->id}/3ds/code")->assertNotFound()->assertJsonPath('error.code', 'ERR_CARDS_033');

    tdsPost($this, 'evt_3ds_3', tdsData('445566', now()->addMinute()->toIso8601String()));
    $this->travel(2)->minutes();

    $this->getJson("/api/v1/cards/issued/{$card->id}/3ds/code")->assertNotFound()->assertJsonPath('error.code', 'ERR_CARDS_033');
});

it('replaces an older code', function (): void {
    $user = User::factory()->create();
    $card = tdsCard($user);
    tdsPost($this, 'evt_3ds_4', tdsData('111111'));
    tdsPost($this, 'evt_3ds_5', tdsData('222222'));
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->getJson("/api/v1/cards/issued/{$card->id}/3ds/code")->assertJsonPath('data.code', '222222');
});

it('never puts the code in the event, the push or the logs', function (): void {
    Event::fake([CardThreeDsCodeReady::class]);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged): void {
        $logged[] = $e->message . json_encode($e->context);
    });
    $card = tdsCard();
    $this->push->shouldReceive('sendToUser')->once()->withArgs(function ($user, string $type, string $title, string $body, array $data) use ($card): bool {
        return $type === 'cards.3ds.code' && $body === 'Your card verification code is ready.' && $data === ['type' => 'cards.3ds.code', 'card_id' => $card->id];
    })->andReturn([]);

    tdsPost($this, 'evt_3ds_6', tdsData('987654'))->assertOk();

    Event::assertDispatched(CardThreeDsCodeReady::class, fn (CardThreeDsCodeReady $e): bool => $e->cardId === $card->id
        && $e->broadcastAs() === 'cards.3ds.code' && array_keys($e->broadcastWith()) === ['card_id', 'expires_at']
        && ! str_contains((string) json_encode($e->broadcastWith()), '987654'));
    expect(implode("\n", $logged))->not->toContain('987654');
});

it('keeps the code out of the queued webhook job', function (): void {
    Queue::fake();
    tdsCard();

    tdsPost($this, 'evt_3ds_7', tdsData('135790'))->assertOk();

    Queue::assertPushed(ProcessLepoWebhook::class, fn (ProcessLepoWebhook $job): bool => ! str_contains(serialize($job), '135790'));
});

it('ignores a code for an unknown card', function (): void {
    $this->push->shouldNotReceive('sendToUser');

    tdsPost($this, 'evt_3ds_8', ['userId' => 1, 'cardId' => 999, 'otp' => '000111', 'expiresAt' => now()->addMinutes(5)->toIso8601String()])->assertOk();

    expect(Cache::get('cards:3ds:unknown'))->toBeNull();
});

it('does not show another user\'s code', function (): void {
    $card = tdsCard();
    tdsPost($this, 'evt_3ds_9', tdsData('246802'));
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    $this->getJson("/api/v1/cards/issued/{$card->id}/3ds/code")->assertNotFound()->assertJsonPath('error.code', 'ERR_CARDS_015');
});
