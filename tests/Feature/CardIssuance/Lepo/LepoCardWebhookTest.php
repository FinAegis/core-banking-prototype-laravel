<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardStateChanged;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Webhooks\LepoWebhookRouter;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url' => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'  => 'test-key',
    ]);
    Event::fake([CardStateChanged::class]);
});

function webhookCard(string $status = 'active', ?string $token = '777', string $provider = 'lepo'): Card
{
    $user = User::factory()->create();
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webhookLepoCard(array $overrides = []): array
{
    return array_merge(['id' => 777, 'userId' => 12345, 'cardNumber' => '****-****-****-1234', 'cardNetwork' => 'Visa', 'status' => 'ACTIVE',
        'balance'            => ['available' => '3.00', 'pending' => '0', 'frozen' => '0']], $overrides);
}

it('refreshes the card on a state webhook', function (): void {
    $card = webhookCard();
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(webhookLepoCard(['status' => 'FROZEN']))]);

    app(LepoWebhookRouter::class)->route('card.state_updated', ['cardId' => 777, 'userId' => 12345, 'currentState' => 'FROZEN'], 'evt_c1');

    expect($card->fresh()?->status)->toBe('frozen');
    Event::assertDispatched(CardStateChanged::class, fn (CardStateChanged $e): bool => $e->cardId === $card->id && $e->status === 'frozen');
});

it('links a card by our card id', function (): void {
    $card = webhookCard('pending', null);
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(webhookLepoCard())]);

    app(LepoWebhookRouter::class)->route('card.state_updated', ['cardId' => 777, 'externalCardId' => $card->id, 'userId' => 12345], 'evt_c2');

    expect($card->fresh()?->issuer_card_token)->toBe('777')->and($card->fresh()?->status)->toBe('active');
});

it('links a created card to the user\'s card that was waiting for it', function (): void {
    $card = webhookCard('pending', null);
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(webhookLepoCard())]);

    app(LepoWebhookRouter::class)->route('card.created', ['cardId' => 777, 'userId' => 12345, 'lastFour' => '1234', 'status' => 'ACTIVE'], 'evt_c3');

    expect($card->fresh()?->issuer_card_token)->toBe('777')->and($card->fresh()?->status)->toBe('active');
});

it('marks a deleted card cancelled without reading it', function (): void {
    $card = webhookCard();
    Http::fake();

    app(LepoWebhookRouter::class)->route('card.deleted', ['cardId' => 777, 'userId' => 12345, 'newStatus' => 'CANCELLED'], 'evt_c4');

    expect($card->fresh()?->status)->toBe('cancelled')->and($card->fresh()?->cancelled_at)->not->toBeNull();
    Http::assertNothingSent();
});

it('ignores a webhook for an unknown card', function (): void {
    Http::fake();

    app(LepoWebhookRouter::class)->route('card.updated', ['cardId' => 999, 'userId' => 4242], 'evt_c5');

    Http::assertNothingSent();
    Event::assertNotDispatched(CardStateChanged::class);
});

it('ignores a FinCard card with the same id', function (): void {
    $card = webhookCard('active', '777', 'fincard');
    Http::fake();

    app(LepoWebhookRouter::class)->route('card.state_updated', ['cardId' => 777, 'userId' => 12345], 'evt_c6');

    expect($card->fresh()?->status)->toBe('active');
    Http::assertNothingSent();
});
