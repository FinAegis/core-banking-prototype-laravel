<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Gdpr;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\Compliance\Services\GdprService;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// Cardholders live on the TENANT connection while GdprService erases the rest
// inside a DEFAULT-connection transaction. If the card erasure ever moved into
// that transaction it would self-deadlock on real MySQL (see the CLAUDE.md
// multi-connection pitfall); SQLite would not notice.
it('erases cardholder data on the tenant connection without deadlock under real multi-session topology', function () {
    Http::fake();
    $user = User::factory()->create();

    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'   => 'ada@example.com', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345',
    ]);
    $cardholder->save();

    app(GdprService::class)->deleteUserData($user);

    $erased = Cardholder::query()->whereKey($cardholder->id)->first();
    expect($erased?->first_name)->toBe('Erased')
        ->and($erased?->email)->toBeNull()
        ->and($erased?->issuer_cardholder_id)->toBe('12345');
});
