<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardholderForm;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
    Cache::put(LepoCardholderForm::OCCUPATIONS_CACHE_KEY, [['value' => '15-1252', 'label' => 'Software Developers']], 3600);
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
        'country'         => 'LT', 'nationality' => 'LT', 'occupation' => '15-1252', 'annual_salary' => '30001-50000',
        'account_purpose' => 'Everyday spending', 'expected_monthly_volume' => '1001-5000', 'document_issue_date' => '2020-05-01',
    ], $overrides);
}

function endpointCardholder(User $user, string $status, string $provider = 'lepo'): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'    => $user->id, 'provider' => $provider, 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'kyc_status' => $status, 'issuer_cardholder_id' => '12345', 'verification_data' => ['questionnaire' => ['occupation' => '15-1252', 'annualSalary' => '30001-50000']],
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
    $user = pilotUser();
    $user->forceFill(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])->save();
    Http::fake();

    $response = $this->getJson('/api/v1/cards/onboarding')
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.provider', 'lepo')
        ->assertJsonPath('data.step', 'cardholder')
        ->assertJsonPath('data.kyc_method', 'hosted_url')
        ->assertJsonPath('data.deposit_networks', [
            ['network' => 'arbitrum', 'asset' => 'USDC', 'default' => true],
            ['network' => 'polygon', 'asset' => 'USDC', 'default' => false],
        ])
        ->assertJsonPath('data.form.prefill', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com'])
        ->assertJsonPath('data.form.options.occupation', [['value' => '15-1252', 'label' => 'Software Developers']])
        ->assertJsonPath('data.form.options.annual_salary.0', ['value' => '0-10000', 'label' => 'Up to $10,000'])
        ->assertJsonPath('data.form.options.annual_salary.7', ['value' => '250001+', 'label' => 'More than $250,000'])
        ->assertJsonPath('data.form.options.expected_monthly_volume.1', ['value' => '1001-5000', 'label' => '$1,001 to $5,000']);

    $fields = collect($response->json('data.form.fields'));
    expect($fields->pluck('name')->all())->toBe([
        'first_name', 'last_name', 'email', 'phone', 'birthday', 'address', 'address_line2', 'city', 'state', 'zip_code',
        'country', 'nationality', 'occupation', 'annual_salary', 'account_purpose', 'expected_monthly_volume', 'document_issue_date',
    ])
        ->and($fields->firstWhere('name', 'birthday'))->toMatchArray(['type' => 'date', 'required' => true, 'min_age' => 18])
        ->and($fields->firstWhere('name', 'country'))->toMatchArray(['type' => 'country', 'required' => true, 'excluded' => ['US']])
        ->and($fields->firstWhere('name', 'address_line2'))->toMatchArray(['required' => false])
        ->and($fields->firstWhere('name', 'occupation'))->toMatchArray(['type' => 'select', 'required' => true]);
    Http::assertNothingSent();
});

it('offers only the questionnaire to a cardholder before the identity check', function (): void {
    $user = pilotUser();
    endpointCardholder($user, 'pending');

    $response = $this->getJson('/api/v1/cards/onboarding')
        ->assertJsonPath('data.step', 'kyc')
        ->assertJsonPath('data.form.prefill', ['occupation' => '15-1252', 'annual_salary' => '30001-50000']);

    expect(collect($response->json('data.form.fields'))->pluck('name')->all())
        ->toBe(['occupation', 'annual_salary', 'account_purpose', 'expected_monthly_volume', 'document_issue_date']);
});

it('tells the app the identity check was sent before Lepo decides', function (): void {
    $user = pilotUser();
    $cardholder = endpointCardholder($user, 'pending');

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'kyc')->assertJsonPath('data.kyc_submitted', false);

    $cardholder->forceFill(['verification_data' => [...($cardholder->verification_data ?? []), 'kyc_submitted_at' => now()->toIso8601String()]])->save();

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'kyc')->assertJsonPath('data.kyc_submitted', true);
});

it('hides the form once the identity check is submitted', function (): void {
    $user = pilotUser();
    endpointCardholder($user, 'in_review');

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'kyc_review')->assertJsonMissingPath('data.form');
});

it('accepts corrected answers without the personal details', function (): void {
    $user = pilotUser();
    $cardholder = endpointCardholder($user, 'action_required');
    Http::fake();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', [
            'occupation'              => '15-1252', 'annual_salary' => '50001-75000', 'account_purpose' => 'Travel',
            'expected_monthly_volume' => '5001-15000', 'document_issue_date' => '2021-03-04',
        ])
        ->assertOk()
        ->assertJsonPath('data.kyc_status', 'action_required');

    expect($cardholder->fresh()?->verification_data['questionnaire'] ?? null)->toBe([
        'occupation'            => '15-1252', 'annualSalary' => '50001-75000', 'accountPurpose' => 'Travel',
        'expectedMonthlyVolume' => '5001-15000', 'documentIssueDate' => '2021-03-04',
    ]);
    Http::assertNothingSent();
});

it('rejects answers that are not on the offered lists', function (): void {
    pilotUser();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload(['occupation' => 'astronaut', 'account_purpose' => 'anything']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['occupation', 'account_purpose']);
});

it('reads the occupation list from Lepo once and keeps it', function (): void {
    pilotUser();
    Cache::forget(LepoCardholderForm::OCCUPATIONS_CACHE_KEY);
    Http::fake(['lepo.test/api/v2/users/occupation-codes' => Http::response([
        ['value' => '29-1141', 'title' => 'Registered Nurses', 'majorGroup' => 'Healthcare'],
        ['value' => '', 'title' => 'Broken row'],
    ])]);

    $this->getJson('/api/v1/cards/onboarding')
        ->assertJsonPath('data.form.options.occupation', [['value' => '29-1141', 'label' => 'Registered Nurses']]);
    $this->getJson('/api/v1/cards/onboarding')->assertOk();

    Http::assertSentCount(1);
});

it('still shows the form when Lepo cannot list occupations', function (): void {
    pilotUser();
    Cache::forget(LepoCardholderForm::OCCUPATIONS_CACHE_KEY);
    Http::fake(['lepo.test/api/v2/users/occupation-codes' => Http::response(['message' => 'down'], 500)]);

    $response = $this->getJson('/api/v1/cards/onboarding')->assertOk()->assertJsonPath('data.form.options.occupation', []);

    expect(collect($response->json('data.form.fields'))->firstWhere('name', 'occupation'))->toMatchArray(['type' => 'text']);
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

it('starts the identity check on a hosted page', function (): void {
    $user = pilotUser();
    $cardholder = endpointCardholder($user, 'pending');
    Http::fake();

    $response = $this->postJson('/api/v1/cards/kyc/session')
        ->assertOk()
        ->assertJsonPath('data.method', 'hosted_url')
        ->assertJsonMissingPath('data.token');

    expect((string) $response->json('data.url'))->toContain('/cards/kyc/' . $cardholder->id)->toContain('signature=')
        ->and($response->json('data.expires_at'))->toBeString();
});

it('starts the identity check in the SumSub SDK when configured', function (): void {
    config(['cardissuance.providers.lepo_kyc_method' => 'sumsub_sdk']);
    $user = pilotUser();
    endpointCardholder($user, 'pending');
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'sdk-token'])]);

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.kyc_method', 'sumsub_sdk');
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
        ->assertJsonPath('data.deposit_addresses.0', ['network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0xarb', 'default' => true]);

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

it('shows only the configured deposit networks', function (): void {
    config(['cardissuance.providers.lepo_deposit_networks' => 'arbitrum']);
    $user = pilotUser();
    $cardholder = endpointCardholder($user, 'verified');
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0xarb', 'is_active' => true]);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'polygon', 'asset' => 'USDC', 'address' => '0xpol', 'is_active' => true]);

    $this->getJson('/api/v1/cards/account')->assertOk()->assertJsonCount(1, 'data.deposit_addresses');
});

it('ignores cardholders of other providers', function (): void {
    $user = pilotUser();
    $legacy = new Cardholder();
    $legacy->forceFill(['user_id' => $user->id, 'first_name' => 'Old', 'last_name' => 'Row', 'kyc_status' => 'verified']);
    $legacy->save();

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'cardholder');
});

it('rejects questionnaire answers Lepo does not accept', function (): void {
    pilotUser();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload(['annual_salary' => '30000-60000', 'expected_monthly_volume' => 'lots']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['annual_salary', 'expected_monthly_volume']);
});

it('lets a cardholder correct the answers before the identity check', function (): void {
    $user = pilotUser();
    $cardholder = endpointCardholder($user, 'pending');
    Http::fake();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload(['annual_salary' => '50001-75000']))
        ->assertOk()
        ->assertJsonPath('data.cardholder_id', $cardholder->id);

    expect($cardholder->fresh()?->verification_data['questionnaire']['annualSalary'] ?? null)->toBe('50001-75000');
    Http::assertNothingSent();
});

it('limits names to the 50 characters Lepo accepts', function (): void {
    pilotUser();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/cards/cardholder', cardholderPayload(['first_name' => str_repeat('A', 51)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name']);
});
