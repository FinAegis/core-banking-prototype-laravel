<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Cardholder;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validFinCardCardholderBody(array $overrides = []): array
{
    return array_merge([
        'first_name'              => 'Jane',
        'last_name'               => 'Smith',
        'gender'                  => 'female',
        'birthday'                => '1992-03-20',
        'nationality'             => 'US',
        'occupation'              => 'Engineer',
        'annual_salary'           => '50k-100k',
        'expected_monthly_volume' => '1k-5k',
        'account_purpose'         => 'personal spending',
        'phone'                   => '5559876543',
        'phone_country_code'      => '+1',
        'email'                   => 'jane@example.com',
        'country'                 => 'US',
        'city'                    => 'Los Angeles',
        'address'                 => '456 Oak Avenue',
        'zip_code'                => '90001',
        'id_type'                 => 'PASSPORT',
        'id_number'               => 'A1234567',
        'id_front_file_id'        => 'file-front',
        'id_selfie_file_id'       => 'file-selfie',
    ], $overrides);
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();

    // Real client, faked HTTP — exercises the client + service + controller.
    $this->app->instance(FinCardClient::class, new FinCardClient(
        baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
        tenantId: 't',
        orgId: 'o',
        userId: 'u',
        username: 'x',
        password: 'y',
    ));

    Http::fake([
        'sandbox.finhub.cloud/api/v2.1/admin/*' => Http::response([
            'success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/holder/occupations' => Http::response([
            'success' => true, 'data' => [['code' => 'ENG', 'name' => 'Engineer']],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/holder/v2/create' => Http::response([
            'success' => true, 'data' => ['holderId' => 'h-777'],
        ]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/common/file/upload' => Http::response([
            'success' => true, 'data' => ['fileId' => 'f-1'],
        ]),
    ]);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
});

it('returns a prefilled draft and field schema for onboarding', function () {
    $this->getJson('/api/v1/cards/onboarding')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.schema.id_types', ['PASSPORT', 'DLN', 'GOVERNMENT_ISSUED_ID_CARD'])
        ->assertJsonPath('data.schema.occupations.0.code', 'ENG')
        ->assertJsonPath('data.prefill.email', $this->user->email);
});

it('creates a FinCard cardholder and persists the local row', function () {
    $this->withHeader('Idempotency-Key', (string) Illuminate\Support\Str::uuid())
        ->postJson('/api/v1/cards/cardholder', validFinCardCardholderBody())
        ->assertCreated()
        ->assertJsonPath('data.kyc_status', 'in_review')
        ->assertJsonPath('data.kyc_stage', 'admin');

    expect(Cardholder::where('user_id', $this->user->id)->where('issuer_cardholder_id', 'h-777')->exists())->toBeTrue();
});

it('rejects a second cardholder for the same user with ERR_CARDS_009', function () {
    Cardholder::create([
        'user_id'    => $this->user->id, 'first_name' => 'J', 'last_name' => 'S',
        'kyc_status' => 'in_review', 'issuer_cardholder_id' => 'h-existing',
    ]);

    $this->withHeader('Idempotency-Key', (string) Illuminate\Support\Str::uuid())
        ->postJson('/api/v1/cards/cardholder', validFinCardCardholderBody())
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ERR_CARDS_009');
});

it('rejects a restricted country with ERR_CARDS_010', function () {
    $this->withHeader('Idempotency-Key', (string) Illuminate\Support\Str::uuid())
        ->postJson('/api/v1/cards/cardholder', validFinCardCardholderBody(['country' => 'RU', 'nationality' => 'RU']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_CARDS_010');
});

it('validates the cardholder body', function () {
    $this->withHeader('Idempotency-Key', (string) Illuminate\Support\Str::uuid())
        ->postJson('/api/v1/cards/cardholder', validFinCardCardholderBody(['id_type' => 'NATIONAL_ID']))
        ->assertStatus(422);
});

it('reports not_started status when no cardholder exists', function () {
    $this->getJson('/api/v1/cards/cardholder')
        ->assertOk()
        ->assertJsonPath('data.kyc_status', 'not_started');
});

it('uploads a KYC document and returns a file id', function () {
    $this->postJson('/api/v1/cards/kyc/documents', [
        'type' => 'id_front',
        'file' => UploadedFile::fake()->image('front.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.file_id', 'f-1')
        ->assertJsonPath('data.type', 'id_front');
});
