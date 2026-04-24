<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Seeders\CardSeeder;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates exactly one active virtual card and a cardholder for the user', function (): void {
    $user = User::factory()->create();

    app(CardSeeder::class)->seed($user);

    expect(Cardholder::where('user_id', $user->id)->count())->toBe(1);

    $cards = Card::where('user_id', $user->id)->get();
    expect($cards)->toHaveCount(1);

    $card = $cards->first();
    expect($card)->not->toBeNull();
    /** @var Card $card */
    expect($card->status)->toBe('active');
    expect($card->metadata['type'] ?? null)->toBe('virtual');
});

it('is idempotent when seeded twice for the same user', function (): void {
    $user = User::factory()->create();

    app(CardSeeder::class)->seed($user);
    app(CardSeeder::class)->seed($user);

    expect(Cardholder::where('user_id', $user->id)->count())->toBe(1);
    expect(Card::where('user_id', $user->id)->count())->toBe(1);
});
