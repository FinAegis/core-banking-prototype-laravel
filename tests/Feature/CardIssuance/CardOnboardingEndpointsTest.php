<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'           => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'            => 'test-key',
        'cardissuance.providers.enabled'               => 'lepo,fincard',
        'cardissuance.providers.default'               => 'lepo',
        'cardissuance.providers.lepo_pilot_only'       => true,
        'cardissuance.providers.lepo_deposit_networks' => 'arbitrum,polygon',
    ]);
});

function pilotUser(): User
{
    $user = User::factory()->create();
    config(['cardissuance.providers.lepo_pilot_users' => $user->uuid]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    return $user;
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function cardholderPayload(array $overrides = []): array
{
    return array_merge([
        'first_name'      => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'phone' => '+37060000000',
        'birthday'        => '1990-01-01', 'address' => 'Gedimino 1', 'city' => 'Vilnius', 'zip_code' => '01103',
        'country'         => 'LT', 'nationality' => 'LT', 'occupation' => 'engineer', 'annual_salary' => '30000-60000',
        'account_purpose' => 'spending', 'expected_monthly_volume' => '1000-5000', 'document_issue_date' => '2020-05-01',
    ], $overrides);
}

function endpointCardholder(User $user, string $status, string $provider = 'lepo'): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'    => $user->id, 'provider' => $provider, 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'kyc_status' => $status, 'issuer_cardholder_id' => '12345', 'verification_data' => ['questionnaire' => ['occupation' => 'engineer']],
    ]);
    $cardholder->save();

    return $cardholder;
}

it('reports cards as not available outside the pilot', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    $this->getJson('/api/v1/cards/onboarding')
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.reason', 'not_available');
});

it('refuses onboarding outside the pilot', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
    Http::fake();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload())
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'ERR_CARDS_022');
    Http::assertNothingSent();
});

it('describes the first onboarding step to a pilot user', function (): void {
    pilotUser();

    $this->getJson('/api/v1/cards/onboarding')
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.provider', 'lepo')
        ->assertJsonPath('data.step', 'cardholder')
        ->assertJsonPath('data.kyc_method', 'sumsub_sdk')
        ->assertJsonPath('data.deposit_networks', ['arbitrum', 'polygon']);
});

it('creates a lepo cardholder for a pilot user', function (): void {
    pilotUser();
    Http::fake(['lepo.test/api/v2/users' => Http::response(['id' => 12345], 201)]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload())
        ->assertCreated()
        ->assertJsonPath('data.provider', 'lepo')
        ->assertJsonPath('data.kyc_status', 'pending');
});

it('refuses US nationals', function (): void {
    pilotUser();
    Http::fake();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload(['nationality' => 'US']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_CARDS_023');
    Http::assertNothingSent();
});

it('validates the cardholder form', function (): void {
    pilotUser();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload(['birthday' => now()->subYears(17)->format('Y-m-d'), 'country' => 'LTU']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['birthday', 'country']);
});

it('starts the identity check', function (): void {
    $user = pilotUser();
    endpointCardholder($user, 'pending');
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'sdk-token'])]);

    $this->postJson('/api/v1/cards/kyc/session')
        ->assertOk()
        ->assertJsonPath('data.method', 'sumsub_sdk')
        ->assertJsonPath('data.token', 'sdk-token');
});

it('asks for the cardholder before the identity check', function (): void {
    pilotUser();

    $this->postJson('/api/v1/cards/kyc/session')->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
});

it('shows the funding account after verification', function (): void {
    $user = pilotUser();
    $cardholder = endpointCardholder($user, 'verified');
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0xarb', 'is_active' => true]);

    $this->getJson('/api/v1/cards/account')
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.deposit_addresses.0.network', 'arbitrum')
        ->assertJsonPath('data.deposit_addresses.0.address', '0xarb');

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'ready');
});

it('refuses the funding account before verification', function (): void {
    $user = pilotUser();
    endpointCardholder($user, 'in_review');

    $this->getJson('/api/v1/cards/account')->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'kyc_review');
});

it('keeps FinCard users on the FinCard flow', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    endpointCardholder($user, 'verified', 'fincard');

    // FinCard's own onboarding refuses a user who already has a cardholder.
    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('error.code', 'ERR_CARDS_009');
});
