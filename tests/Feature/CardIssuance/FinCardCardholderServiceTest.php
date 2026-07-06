<?php

declare(strict_types=1);

namespace Tests\Feature\CardIssuance;

use App\Domain\CardIssuance\Events\Broadcast\FinCardKycStatusChanged;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\FinCardCardholderService;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinCardCardholderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    private function client(): FinCardClient
    {
        return new FinCardClient(
            baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
            tenantId: 't',
            orgId: 'o',
            userId: 'u',
            username: 'x',
            password: 'y',
        );
    }

    /**
     * @return array<string, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function loginFake(): array
    {
        return [
            'sandbox.finhub.cloud/api/v2.1/admin/*' => Http::response([
                'success' => true, 'code' => 0, 'msg' => 'ok',
                'data'    => ['accessToken' => 'jwt', 'expiresIn' => 3600],
            ]),
        ];
    }

    private function cardholder(User $user): Cardholder
    {
        // Assign explicitly (not Model::create(array<string,mixed>)) — Larastan
        // rejects a non-shaped array to create().
        $cardholder = new Cardholder();
        $cardholder->user_id = (string) $user->id;
        $cardholder->first_name = 'Jane';
        $cardholder->last_name = 'Smith';
        $cardholder->kyc_status = 'in_review';
        $cardholder->kyc_stage = 'admin';
        $cardholder->issuer_cardholder_id = 'h-9';
        $cardholder->save();

        return $cardholder;
    }

    public function test_create_cardholder_calls_fincard_and_persists_local_row(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/holder/v2/create' => Http::response([
                'success' => true, 'code' => 0, 'msg' => 'ok', 'data' => ['holderId' => 'h-123'],
            ]),
        ]));

        $user = User::factory()->create();
        $service = new FinCardCardholderService($this->client());

        $cardholder = $service->createCardholder($user, [
            'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com',
            'phone'      => '5551234', 'country' => 'US', 'city' => 'NYC', 'gender' => 'female',
            'birthday'   => '1990-01-01', 'address' => '1 Main St', 'zip_code' => '10001',
        ], ['forwarded_for' => '203.0.113.4', 'platform' => 'ios', 'device_id' => 'd1']);

        $this->assertSame('h-123', $cardholder->issuer_cardholder_id);
        $this->assertSame('in_review', $cardholder->kyc_status);
        $this->assertSame('admin', $cardholder->kyc_stage);
        $this->assertSame((int) $user->id, (int) $cardholder->user_id);
        $this->assertDatabaseHas('cardholders', ['issuer_cardholder_id' => 'h-123', 'user_id' => $user->id]);

        // The FinCard payload used camelCase + carried the end-user IP.
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/card/holder/v2/create')) {
                return false;
            }
            $body = json_decode($request->body(), true);

            return ($body['firstName'] ?? null) === 'Jane' && ($body['ipAddress'] ?? null) === '203.0.113.4';
        });
    }

    public function test_apply_kyc_webhook_pass_audit_verifies_and_broadcasts(): void
    {
        Event::fake([FinCardKycStatusChanged::class]);
        $user = User::factory()->create();
        $this->cardholder($user);

        (new FinCardCardholderService($this->client()))->applyKycWebhook('pass_audit', ['data' => ['holderId' => 'h-9']]);

        $fresh = Cardholder::query()->where('issuer_cardholder_id', 'h-9')->firstOrFail();
        $this->assertSame('verified', $fresh->kyc_status);
        $this->assertNotNull($fresh->verified_at);
        Event::assertDispatched(FinCardKycStatusChanged::class, fn ($e): bool => $e->kycStatus === 'verified' && $e->cardholderId === $fresh->id);
    }

    public function test_apply_kyc_webhook_reject_records_reason(): void
    {
        Event::fake([FinCardKycStatusChanged::class]);
        $user = User::factory()->create();
        $this->cardholder($user);

        (new FinCardCardholderService($this->client()))->applyKycWebhook('reject', ['data' => ['holderId' => 'h-9', 'reason' => 'document blurry']]);

        $fresh = Cardholder::query()->where('issuer_cardholder_id', 'h-9')->firstOrFail();
        $this->assertSame('rejected', $fresh->kyc_status);
        $this->assertSame('document blurry', $fresh->kyc_rejection_reason);
    }

    public function test_apply_kyc_webhook_for_unknown_holder_is_a_noop(): void
    {
        Event::fake([FinCardKycStatusChanged::class]);

        (new FinCardCardholderService($this->client()))->applyKycWebhook('pass_audit', ['data' => ['holderId' => 'nope']]);

        Event::assertNotDispatched(FinCardKycStatusChanged::class);
    }

    public function test_existing_for_finds_the_users_cardholder(): void
    {
        $user = User::factory()->create();
        $this->cardholder($user);
        $service = new FinCardCardholderService($this->client());

        $this->assertNotNull($service->existingFor($user));
        $this->assertNull($service->existingFor(User::factory()->create()));
    }
}
