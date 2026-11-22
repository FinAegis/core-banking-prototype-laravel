<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'          => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'           => 'test-key',
        'cardissuance.issuers.lepo.widget_script_url' => 'https://static.univisioncard.com/api/sdk/card/1.0.0/index.min.js',
        'cardissuance.providers.enabled'              => 'lepo,fincard',
        'cardissuance.providers.default'              => 'lepo',
        'cardissuance.providers.lepo_pilot_only'      => false,
    ]);
});

function detailsCard(string $status = 'active', string $provider = 'lepo', ?string $token = '777', ?User $user = null): Card
{
    $user ??= User::factory()->create();
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id' => $user->id, 'provider' => $provider, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345',
    ]);
    $cardholder->save();

    return Card::create([
        'user_id'           => $user->id, 'cardholder_id' => $cardholder->id, 'issuer' => $provider, 'status' => $status,
        'issuer_card_token' => $token, 'last4' => $token === null ? null : '1234', 'network' => $token === null ? null : 'visa',
    ]);
}

function detailsLink(Card $card, int $minutes = 5): string
{
    // A fresh nonce per link: CI shares one cache between tests.
    return URL::temporarySignedRoute('cards.details.hosted', now()->addMinutes($minutes), ['card' => $card->id, 'n' => bin2hex(random_bytes(16))], absolute: false);
}

it('shows the card with Interlace Widget.js and a fresh token', function (): void {
    Http::fake(['lepo.test/api/v2/cards/777/widget*' => Http::response(['success' => true, 'widgetToken' => 'widget-token-1', 'expiresIn' => 300])]);
    $card = detailsCard();

    $response = $this->get(detailsLink($card))
        ->assertOk()
        ->assertSee('https://static.univisioncard.com/api/sdk/card/1.0.0/index.min.js', false)
        ->assertSee('"widget-token-1"', false)
        ->assertSee('widget.bootstrap', false)
        ->assertSee('ReactNativeWebView', false)
        ->assertSee('Card details');

    expect((string) $response->headers->get('Content-Security-Policy'))
        ->toContain('https://static.univisioncard.com')
        ->toContain('frame-src https:')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('opens a link only once', function (): void {
    Http::fake(['lepo.test/api/v2/cards/777/widget*' => Http::response(['success' => true, 'widgetToken' => 'widget-token-1'])]);
    $card = detailsCard();
    $link = detailsLink($card);

    $this->get($link)->assertOk();
    $this->get($link)->assertForbidden()->assertSee('already been used')->assertSee('"used"', false);

    Http::assertSentCount(1);
});

it('refuses an expired or unsigned link', function (): void {
    Http::fake();
    $card = detailsCard();

    $this->get(detailsLink($card, -1))->assertForbidden()->assertSee('This link has expired')->assertSee('"expired"', false);
    $this->get(route('cards.details.hosted', ['card' => $card->id, 'n' => 'x']))->assertForbidden();

    Http::assertNothingSent();
});

it('does not open another provider\'s card', function (): void {
    Http::fake();

    $this->get(detailsLink(detailsCard('active', 'fincard')))->assertNotFound();
    Http::assertNothingSent();
});

it('asks to try again when Lepo gives no token', function (): void {
    Http::fake(['lepo.test/api/v2/cards/777/widget*' => Http::response(['success' => false, 'errorCode' => 'X'])]);

    $this->get(detailsLink(detailsCard()))->assertStatus(502)->assertSee('could not be shown right now')->assertSee('"unavailable"', false);
});

it('hands out a card-details link for an active card', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $card = detailsCard('active', 'lepo', '777', $user);
    Http::fake();

    $response = $this->getJson("/api/v1/cards/issued/{$card->id}/sensitive")
        ->assertOk()
        ->assertJsonPath('data.display_mode', 'widget');

    expect((string) $response->json('data.url'))->toContain('/cards/details/' . $card->id . '?')->toContain('signature=')->toContain('n=')
        ->and($response->json('data.expires_at'))->toBeString();
    Http::assertNothingSent();
});

it('gives each card-details link its own nonce', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $card = detailsCard('active', 'lepo', '777', $user);

    $first = (string) $this->getJson("/api/v1/cards/issued/{$card->id}/sensitive")->json('data.url');
    $second = (string) $this->getJson("/api/v1/cards/issued/{$card->id}/sensitive")->json('data.url');

    expect($first)->not->toBe($second);
});

it('refuses card details for a card that is not open', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $card = detailsCard('pending', 'lepo', null, $user);

    $this->getJson("/api/v1/cards/issued/{$card->id}/sensitive")->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
});
