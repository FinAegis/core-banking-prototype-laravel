<?php

/**
 * Endpoint tests for POST /api/v1/webhooks/fincard.
 *
 * Verifies the Phase-1 webhook contract: RSA-signature auth, JSON validation,
 * idempotent dedupe on processed_webhook_events, and the `{"success": true}`
 * acknowledgement FinCard expects to stop retrying.
 */

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Infrastructure\FinCard\FinCardWebhookVerifier;
use App\Models\User;

/**
 * @return array{0: string, 1: string} [privatePem, publicPem]
 */
function fincardEndpointKeypair(): array
{
    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($res === false) {
        throw new RuntimeException('openssl_pkey_new failed');
    }
    openssl_pkey_export($res, $priv);
    $details = openssl_pkey_get_details($res);

    return [(string) $priv, (string) ($details['key'] ?? '')];
}

function fincardEndpointSign(string $body, string $priv): string
{
    openssl_sign($body, $sig, $priv, OPENSSL_ALGO_SHA256);

    return base64_encode($sig);
}

/**
 * Build the PHP `$server` array carrying a signature header for the call().
 *
 * @return array<string, string>
 */
function fincardWebhookServer(string $header, string $value): array
{
    return [
        'CONTENT_TYPE'                                       => 'application/json',
        'HTTP_' . str_replace('-', '_', strtoupper($header)) => $value,
    ];
}

beforeEach(function () {
    [$priv, $pub] = fincardEndpointKeypair();
    $this->finCardPriv = $priv;
    // Inject a verifier with a known key so the signed fixture verifies.
    $this->app->instance(FinCardWebhookVerifier::class, new FinCardWebhookVerifier($pub));
});

it('accepts a validly signed event, records it, and acknowledges success', function () {
    $body = (string) json_encode(['eventType' => 'create', 'data' => ['orderNo' => 'ord-123', 'cardId' => 'c1']]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-FC-SIGNATURE', $sig), $body)
        ->assertOk()
        ->assertExactJson(['success' => true]);

    expect(ProcessedWebhookEvent::where('provider', 'fincard')->where('event_id', 'ord-123')->count())->toBe(1);
});

it('accepts the signature under the legacy X-WSB-SIGNATURE header too', function () {
    $body = (string) json_encode(['eventType' => 'deposit', 'data' => ['orderNo' => 'ord-wsb']]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-WSB-SIGNATURE', $sig), $body)
        ->assertOk()
        ->assertExactJson(['success' => true]);
});

it('is idempotent across duplicate deliveries', function () {
    $body = (string) json_encode(['eventType' => 'create', 'data' => ['orderNo' => 'ord-dup']]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);
    $server = fincardWebhookServer('X-FC-SIGNATURE', $sig);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], $server, $body)->assertOk()->assertExactJson(['success' => true]);
    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], $server, $body)->assertOk()->assertExactJson(['success' => true]);

    expect(ProcessedWebhookEvent::where('provider', 'fincard')->where('event_id', 'ord-dup')->count())->toBe(1);
});

it('rejects an invalid signature with 401', function () {
    $body = (string) json_encode(['eventType' => 'create', 'data' => ['orderNo' => 'ord-x']]);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-FC-SIGNATURE', base64_encode('garbage')), $body)
        ->assertStatus(401);

    expect(ProcessedWebhookEvent::where('event_id', 'ord-x')->exists())->toBeFalse();
});

it('rejects a validly signed but identifier-less payload with 400', function () {
    $body = (string) json_encode(['data' => []]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-FC-SIGNATURE', $sig), $body)
        ->assertStatus(400);
});

it('applies a cardholder pass_audit event to the local KYC state', function () {
    $user = User::factory()->create();
    Cardholder::create([
        'user_id'    => $user->id, 'first_name' => 'Jane', 'last_name' => 'Smith',
        'kyc_status' => 'in_review', 'kyc_stage' => 'admin', 'issuer_cardholder_id' => 'h-web',
    ]);

    $body = (string) json_encode(['eventType' => 'pass_audit', 'data' => ['holderId' => 'h-web', 'orderNo' => 'ord-kyc']]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-FC-SIGNATURE', $sig), $body)
        ->assertOk()
        ->assertExactJson(['success' => true]);

    $fresh = Cardholder::query()->where('issuer_cardholder_id', 'h-web')->firstOrFail();
    expect($fresh->kyc_status)->toBe('verified')
        ->and($fresh->verified_at)->not->toBeNull();
});

it('credits the funding account on a wallet DEPOSIT event', function () {
    $user = User::factory()->create();
    $account = new FinCardAccount();
    $account->user_id = (string) $user->id;
    $account->fincard_account_id = 'acc-web';
    $account->currency = 'USD';
    $account->balance_cents = 1000;
    $account->status = 'active';
    $account->save();

    $body = (string) json_encode(['eventType' => 'DEPOSIT', 'data' => ['accountId' => 'acc-web', 'amount' => '8.86', 'orderNo' => 'ord-dep']]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-FC-SIGNATURE', $sig), $body)
        ->assertOk()
        ->assertExactJson(['success' => true]);

    $fresh = FinCardAccount::query()->where('fincard_account_id', 'acc-web')->firstOrFail();
    expect($fresh->balance_cents)->toBe(1886);
});

it('activates a card on a card-op create event', function () {
    $user = User::factory()->create();
    $ch = new Cardholder();
    $ch->user_id = (string) $user->id;
    $ch->first_name = 'Jane';
    $ch->last_name = 'Smith';
    $ch->kyc_status = 'verified';
    $ch->issuer_cardholder_id = 'h-c';
    $ch->save();

    $card = new Card();
    $card->user_id = (string) $user->id;
    $card->cardholder_id = $ch->id;
    $card->issuer_card_token = 'card-web';
    $card->issuer = 'fincard';
    $card->last4 = '0000';
    $card->network = 'visa';
    $card->status = 'pending';
    $card->currency = 'USD';
    $card->save();

    $body = (string) json_encode(['eventType' => 'create', 'data' => ['cardId' => 'card-web', 'status' => 'Normal', 'orderNo' => 'ord-cardcreate']]);
    $sig = fincardEndpointSign($body, $this->finCardPriv);

    $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], fincardWebhookServer('X-FC-SIGNATURE', $sig), $body)
        ->assertOk()
        ->assertExactJson(['success' => true]);

    expect(Card::query()->where('issuer_card_token', 'card-web')->firstOrFail()->status)->toBe('active');
});
