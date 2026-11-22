<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\FinCardCardholderService;
use App\Models\User;
use Illuminate\Database\QueryException;

it('does not treat a Lepo cardholder as a FinCard cardholder', function (): void {
    $user = User::factory()->create();
    $lepo = new Cardholder();
    $lepo->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'A', 'last_name' => 'B', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $lepo->save();

    expect(app(FinCardCardholderService::class)->existingFor($user))->toBeNull();
});

it('allows one cardholder per user and provider', function (): void {
    $user = User::factory()->create();
    $make = function (?string $provider) use ($user): void {
        $cardholder = new Cardholder();
        $cardholder->forceFill(['user_id' => $user->id, 'provider' => $provider, 'first_name' => 'A', 'last_name' => 'B', 'kyc_status' => 'pending']);
        $cardholder->save();
    };

    $make(null);
    $make(null);
    $make('lepo');

    expect(fn () => $make('lepo'))->toThrow(QueryException::class);
});
