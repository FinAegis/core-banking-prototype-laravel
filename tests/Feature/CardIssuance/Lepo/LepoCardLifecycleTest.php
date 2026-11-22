<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardStateChanged;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardLifecycle;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'             => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'              => 'test-key',
        'cardissuance.issuers.lepo.card_type_id_virtual' => '145',
        'cardissuance.providers.lepo_max_cards'          => 1,
    ]);
});

function lifecycleCardholder(): Cardholder
{
    $user = User::factory()->create();
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'    => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+37060000000',
        'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345',
    ]);
    $cardholder->save();
    CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);

    return $cardholder;
}

function openLifecycleCard(string $status = 'active'): Card
{
    $cardholder = lifecycleCardholder();

    return Card::create([
        'user_id'           => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'issuer' => 'lepo', 'status' => $status,
        'issuer_card_token' => '777', 'last4' => '1234', 'network' => 'visa',
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lepoCard(array $overrides = []): array
{
    return array_merge([
        'id'         => 777, 'userId' => 12345, 'externalCardId' => null, 'cardType' => 'VIRTUAL', 'cardNetwork' => 'Visa',
        'cardNumber' => '****-****-****-1234', 'expiryDate' => '2029-12-31', 'status' => 'ACTIVE',
        'balance'    => ['available' => '0', 'pending' => '0', 'frozen' => '0', 'currency' => 'USD'], 'currency' => 'USD',
    ], $overrides);
}

it('creates a pending card row once per user', function (): void {
    Http::fake();
    $cardholder = lifecycleCardholder();
    $lifecycle = app(LepoCardLifecycle::class);

    $card = $lifecycle->createPending($cardholder);

    expect($card->status)->toBe(Card::STATUS_PENDING)
        ->and($card->issuer)->toBe('lepo')
        ->and($card->isOpenAtProvider())->toBeFalse()
        ->and($card->funding_account_id)->toBe($cardholder->fundingAccount?->id);

    try {
        $lifecycle->createPending($cardholder);
        $this->fail('A second card was created.');
    } catch (CardProviderException $e) {
        expect($e->reason())->toBe('card_limit');
    }
    Http::assertNothingSent();
});

it('opens the card at Lepo with our card id', function (): void {
    $cardholder = lifecycleCardholder();
    $card = app(LepoCardLifecycle::class)->createPending($cardholder);
    Http::fake(['lepo.test/api/v2/cards' => Http::response(lepoCard(['externalCardId' => $card->id]), 201)]);

    $opened = app(LepoCardLifecycle::class)->open($card);

    expect($opened->issuer_card_token)->toBe('777')
        ->and($opened->status)->toBe(Card::STATUS_ACTIVE)
        ->and($opened->last4)->toBe('1234')
        ->and($opened->network)->toBe('visa');
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r['userId'] === 12345 && $r['cardTypeId'] === 145
        && $r['phone'] === '+37060000000' && $r['externalCardId'] === $card->id);
});

it('adopts the card Lepo created when the create call fails', function (): void {
    $cardholder = lifecycleCardholder();
    $card = app(LepoCardLifecycle::class)->createPending($cardholder);
    $lists = 0;
    Http::fake(function (Request $r) use (&$lists, $card) {
        if ($r->method() === 'POST') {
            return Http::response(['message' => 'timeout upstream'], 500);
        }
        $lists++;

        return Http::response(['cards' => $lists === 1 ? [] : [lepoCard(['id' => 900, 'externalCardId' => 'someone-else']), lepoCard(['externalCardId' => $card->id])], 'total' => 2]);
    });

    $opened = app(LepoCardLifecycle::class)->open($card);

    expect($opened->issuer_card_token)->toBe('777')->and($opened->status)->toBe(Card::STATUS_ACTIVE);
    Http::assertSentCount(3);
});

it('adopts a card Lepo already holds for this row without creating one', function (): void {
    $cardholder = lifecycleCardholder();
    $card = app(LepoCardLifecycle::class)->createPending($cardholder);
    Http::fake(['lepo.test/api/v2/cards*' => Http::response(['cards' => [lepoCard(['externalCardId' => $card->id])]])]);

    expect(app(LepoCardLifecycle::class)->open($card)->issuer_card_token)->toBe('777');
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'POST');
});

it('does not open a card twice', function (): void {
    Http::fake();
    $card = openLifecycleCard();

    app(LepoCardLifecycle::class)->open($card);

    Http::assertNothingSent();
});

it('stores payment required as a status', function (): void {
    $cardholder = lifecycleCardholder();
    $card = app(LepoCardLifecycle::class)->createPending($cardholder);
    Http::fake(['lepo.test/api/v2/cards' => Http::response(lepoCard(['status' => 'PAYMENT_REQUIRED', 'cardNumber' => '', 'requiredAmount' => '1.00']), 201)]);

    $opened = app(LepoCardLifecycle::class)->open($card);

    expect($opened->status)->toBe(Card::STATUS_PAYMENT_REQUIRED)
        ->and($opened->issuer_card_token)->toBe('777')
        ->and($opened->last4)->toBeNull();
});

it('maps every Lepo status', function (string $lepo, string $ours): void {
    $card = openLifecycleCard('pending');

    expect(app(LepoCardLifecycle::class)->sync($card, lepoCard(['status' => $lepo]))->status)->toBe($ours);
})->with([
    ['ACTIVE', 'active'], ['PENDING', 'pending'], ['UNACTIVATED', 'pending'], ['SHIPMENTPENDING', 'pending'], ['SHIPPED', 'pending'],
    ['FROZEN', 'frozen'], ['SUSPENDED', 'frozen'], ['PAYMENT_REQUIRED', 'payment_required'], ['BLOCKED', 'blocked'],
    ['FAILED', 'failed'], ['EXPIRED', 'expired'], ['CANCELLED', 'cancelled'],
]);

it('reads the balance with bcmath', function (): void {
    $card = openLifecycleCard();

    $synced = app(LepoCardLifecycle::class)->sync($card, lepoCard(['balance' => ['available' => '12.345', 'pending' => '0', 'frozen' => '0']]));

    expect($synced->balance_cents)->toBe(1234);
});

it('announces a status change once', function (): void {
    Event::fake([CardStateChanged::class]);
    $card = openLifecycleCard('pending');
    $lifecycle = app(LepoCardLifecycle::class);

    $lifecycle->sync($card, lepoCard());
    $lifecycle->sync($card, lepoCard());

    Event::assertDispatchedTimes(CardStateChanged::class, 1);
    Event::assertDispatched(CardStateChanged::class, fn (CardStateChanged $e): bool => $e->cardId === $card->id && $e->status === 'active'
        && $e->broadcastAs() === 'card.state_changed' && $e->broadcastWith() === ['card_id' => $card->id, 'status' => 'active', 'balance_cents' => 0]);
});

it('refuses to cancel a card that holds money', function (): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(lepoCard(['balance' => ['available' => '0', 'pending' => '0.50', 'frozen' => '0']]))]);

    try {
        app(LepoCardLifecycle::class)->cancel($card);
        $this->fail('A card with money was cancelled.');
    } catch (CardProviderException $e) {
        expect($e->reason())->toBe('holds_money');
    }
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'DELETE');
});

it('refuses to cancel when the balance cannot be read', function (): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/*' => Http::response(['message' => 'down'], 500)]);

    try {
        app(LepoCardLifecycle::class)->cancel($card);
        $this->fail('The card was cancelled without a balance.');
    } catch (CardProviderException $e) {
        expect($e->reason())->toBe('provider_unavailable');
    }
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'DELETE');
});

it('refuses to cancel when Lepo gives no balance', function (array $answer): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response($answer)]);

    try {
        app(LepoCardLifecycle::class)->cancel($card);
        $this->fail('The card was cancelled without a readable balance.');
    } catch (CardProviderException $e) {
        expect($e->reason())->toBe('provider_unavailable');
    }
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'DELETE');
})->with([
    'no balance object' => [['id' => 777, 'status' => 'ACTIVE']],
    'balance is null'   => [['id' => 777, 'status' => 'ACTIVE', 'balance' => null]],
    'a part is missing' => [['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '0', 'frozen' => '0']]],
    'a part is null'    => [['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '0', 'pending' => null, 'frozen' => '0']]],
]);

it('records an issuer suspension or block as a support freeze', function (string $lepoStatus): void {
    $card = openLifecycleCard();
    $lifecycle = app(LepoCardLifecycle::class);

    $lifecycle->sync($card, lepoCard(['status' => $lepoStatus]));
    expect(CardStaffFreeze::isHeld('777'))->toBeTrue();

    $lifecycle->sync($card, lepoCard(['status' => 'ACTIVE']));
    expect(CardStaffFreeze::isHeld('777'))->toBeFalse();
})->with(['SUSPENDED', 'BLOCKED']);

it('keeps a support freeze when Lepo reports the card active', function (): void {
    $card = openLifecycleCard();
    CardStaffFreeze::place('777', null);

    app(LepoCardLifecycle::class)->sync($card, lepoCard(['status' => 'ACTIVE']));

    expect(CardStaffFreeze::isHeld('777'))->toBeTrue();
});

it('does not create a second card when a retry finds the first one', function (): void {
    $cardholder = lifecycleCardholder();
    $card = app(LepoCardLifecycle::class)->createPending($cardholder);
    $lists = 0;
    Http::fake(function (Request $r) use (&$lists, $card) {
        if ($r->method() === 'POST') {
            return Http::response(['message' => 'timeout upstream'], 504);
        }
        $lists++;

        // Lepo commits the card only after the first attempt has given up.
        return Http::response(['cards' => $lists > 2 ? [lepoCard(['externalCardId' => $card->id])] : []]);
    });
    $lifecycle = app(LepoCardLifecycle::class);

    expect(fn () => $lifecycle->open($card))->toThrow(CardProviderException::class);
    $opened = $lifecycle->open($card->fresh() ?? $card);

    expect($opened->issuer_card_token)->toBe('777');
    Http::assertSentCount(4);
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === 'POST')->count())->toBe(1);
});

it('reads the expiry from Lepo\'s month and year', function (): void {
    $card = openLifecycleCard();

    $synced = app(LepoCardLifecycle::class)->sync($card, lepoCard(['expiryDate' => '12/28', 'expMonth' => '12', 'expYear' => '2028']));

    expect($synced->expires_at?->toDateString())->toBe('2028-12-31');
});

it('reads an expiry date written as month and short year', function (string $written): void {
    $card = openLifecycleCard();

    $synced = app(LepoCardLifecycle::class)->sync($card, lepoCard(['expiryDate' => $written]));

    expect($synced->expires_at?->toDateString())->toBe('2028-12-31');
})->with(['12/28', '12/2028', '2028-12-31']);

it('ignores an expiry date it cannot read', function (): void {
    $card = openLifecycleCard();
    $card->forceFill(['expires_at' => '2027-06-30'])->save();

    $synced = app(LepoCardLifecycle::class)->sync($card, lepoCard(['expiryDate' => '1228']));

    expect($synced->expires_at?->toDateString())->toBe('2027-06-30');
});

it('cancels an empty card', function (): void {
    $card = openLifecycleCard();
    $deleted = false;
    Http::fake(function (Request $r) use (&$deleted) {
        if ($r->method() === 'DELETE') {
            $deleted = true;

            return Http::response(['success' => true, 'message' => 'deleted']);
        }

        return Http::response(lepoCard(['status' => $deleted ? 'CANCELLED' : 'ACTIVE']));
    });

    $cancelled = app(LepoCardLifecycle::class)->cancel($card);

    expect($deleted)->toBeTrue()
        ->and($cancelled->status)->toBe(Card::STATUS_CANCELLED)
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

it('freezes and unfreezes a card at Lepo', function (): void {
    $card = openLifecycleCard();
    $frozen = false;
    Http::fake(function (Request $r) use (&$frozen) {
        if (str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/freeze')) {
            $frozen = true;
        }
        if (str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/enable')) {
            $frozen = false;
        }

        return $r->method() === 'POST' ? Http::response(['success' => true]) : Http::response(lepoCard(['status' => $frozen ? 'FROZEN' : 'ACTIVE']));
    });
    $lifecycle = app(LepoCardLifecycle::class);

    expect($lifecycle->freeze($card)->status)->toBe(Card::STATUS_FROZEN)
        ->and($card->frozen_at)->not->toBeNull()
        ->and($lifecycle->unfreeze($card)->status)->toBe(Card::STATUS_ACTIVE)
        ->and($card->frozen_at)->toBeNull();
});

it('lists transactions in one shape the app can show', function (): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/api/v2/cards/777/transactions*' => Http::response(['transactions' => [
        ['id'              => 't1', 'amount' => 42.5, 'currency' => 'USD', 'fee' => 0.1, 'type' => 'card_payment', 'status' => 'completed',
            'merchantName' => 'Coffee Shop', 'merchantCountry' => 'LT', 'transactionTime' => '2026-11-22T10:00:00Z'],
        ['id' => 't2', 'amount' => '-12.505', 'currency' => 'USD', 'fee' => '0', 'type' => 'CONSUMPTION', 'status' => 'SUCCESS'],
        ['id' => 't3', 'amount' => '25', 'currency' => 'USD', 'type' => 'card_topup', 'status' => 'processing'],
        ['id' => 't4', 'amount' => '3.10', 'currency' => 'USD', 'type' => 'card_payment_reversal', 'status' => 'on_hold'],
        ['id' => 't5', 'amount' => '1', 'currency' => 'USD', 'type' => 'card_issuance_fee', 'status' => 'failed'],
        ['id' => 't6', 'amount' => 1.0E-5, 'currency' => 'USD', 'type' => 'something_new', 'status' => 'weird'],
    ], 'total' => 6, 'hasMorePages' => false])]);

    $page = app(LepoCardLifecycle::class)->transactions($card, 0, 20);

    expect($page['has_more'])->toBeFalse()
        ->and($page['total'])->toBe(6)
        ->and($page['items'][0])->toBe([
            'id'            => 't1', 'type' => 'payment', 'direction' => 'debit', 'amount' => '42.50', 'currency' => 'USD', 'fee' => '0.10',
            'status'        => 'completed', 'merchant' => 'Coffee Shop', 'merchant_country' => 'LT', 'occurred_at' => '2026-11-22T10:00:00Z',
            'provider_type' => 'card_payment',
        ])
        ->and(array_map(fn (array $i): array => [$i['type'], $i['direction'], $i['amount'], $i['status']], $page['items']))->toBe([
            ['payment', 'debit', '42.50', 'completed'],
            ['payment', 'debit', '12.50', 'completed'],
            ['top_up', 'credit', '25.00', 'pending'],
            ['refund', 'credit', '3.10', 'pending'],
            ['fee', 'debit', '1.00', 'failed'],
            ['other', 'credit', '0.00', 'unknown'],
        ]);
});

it('asks Lepo for pages counted from zero, like ours', function (): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/api/v2/cards/777/transactions*' => Http::response(['transactions' => [], 'hasMorePages' => false])]);

    app(LepoCardLifecycle::class)->transactions($card, 0, 20);
    app(LepoCardLifecycle::class)->transactions($card, 2, 20);

    // Lepo documents the page parameter as "starting from 0, default: 0".
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'page=0&'));
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'page=2&'));
    Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'page=1&') || str_contains($r->url(), 'page=3&'));
});

it('gets a widget token for the card details page', function (): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/api/v2/cards/777/widget*' => Http::response(['success' => true, 'widgetToken' => 'widget-token-1', 'expiresIn' => 300])]);

    expect(app(LepoCardLifecycle::class)->widgetToken($card))->toBe(['token' => 'widget-token-1', 'expires_in' => 300]);
});

it('refuses a widget answer without a token', function (): void {
    $card = openLifecycleCard();
    Http::fake(['lepo.test/api/v2/cards/777/widget*' => Http::response(['success' => true, 'widgetToken' => ''])]);

    expect(fn () => app(LepoCardLifecycle::class)->widgetToken($card))->toThrow(CardProviderException::class);
});
