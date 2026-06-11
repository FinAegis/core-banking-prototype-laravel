<?php

declare(strict_types=1);

use App\Domain\TrustCert\Models\VerificationPayment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    // Verification fees default OFF (the pay endpoints 403 with "verification
    // is currently free") — these tests exercise the paid path.
    config(['trustcert.verification_fees.enabled' => true]);

    Schema::dropIfExists('verification_payments');
    Schema::create('verification_payments', function ($table): void {
        $table->uuid('id')->primary();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('application_id', 128);
        $table->string('method', 20);
        $table->decimal('amount', 10, 2);
        $table->string('currency', 3)->default('USD');
        $table->string('status', 20)->default('completed');
        $table->string('stripe_session_id', 255)->nullable();
        $table->string('iap_transaction_id', 255)->nullable();
        $table->string('platform', 10)->nullable();
        $table->timestamps();
        $table->unique('application_id');
    });
});

it('replays the same wallet pay receipt for repeated Idempotency-Key', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $applicationId = 'app_idem_' . Str::random(8);
    Cache::put("trustcert_application:{$user->id}", [
        'id'           => $applicationId,
        'user_id'      => $user->id,
        'target_level' => 'basic',
        'status'       => 'pending',
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addDays(30));
    Cache::put("wallet_balance:{$user->id}", '100.00');

    $idempotencyKey = (string) Str::uuid();

    $first = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson("/api/v1/trustcert/applications/{$applicationId}/pay");

    $first->assertStatus(200)
        ->assertJsonStructure(['receiptId', 'amount', 'currency', 'paidAt']);

    $firstReceiptId = $first->json('receiptId');

    // Second call with same key must NOT create another payment row and must
    // return the original receipt verbatim.
    $second = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson("/api/v1/trustcert/applications/{$applicationId}/pay");

    $second->assertStatus(200)
        ->assertJsonPath('receiptId', $firstReceiptId)
        ->assertHeader('X-Idempotency-Replayed', 'true');

    expect(VerificationPayment::where('application_id', $applicationId)->count())->toBe(1);
});

it('rejects same Idempotency-Key reused with a different request body', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $applicationId = 'app_idem_' . Str::random(8);
    Cache::put("trustcert_application:{$user->id}", [
        'id'           => $applicationId,
        'user_id'      => $user->id,
        'target_level' => 'basic',
        'status'       => 'pending',
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addDays(30));
    Cache::put("wallet_balance:{$user->id}", '100.00');

    $idempotencyKey = (string) Str::uuid();

    $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson("/api/v1/trustcert/applications/{$applicationId}/pay", ['note' => 'first'])
        ->assertStatus(200);

    $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->postJson("/api/v1/trustcert/applications/{$applicationId}/pay", ['note' => 'mutated'])
        ->assertStatus(409)
        ->assertJsonPath('error', 'Idempotency key already used');
});

it('still allows a different Idempotency-Key on the same application to fall through to the duplicate-payment guard', function (): void {
    // Belt-and-suspenders: even without idempotency, the controller's own
    // isAlreadyPaid() check guards against double-charging the same
    // application via two different keys. This documents that contract.
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $applicationId = 'app_idem_' . Str::random(8);
    Cache::put("trustcert_application:{$user->id}", [
        'id'           => $applicationId,
        'user_id'      => $user->id,
        'target_level' => 'basic',
        'status'       => 'pending',
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addDays(30));
    Cache::put("wallet_balance:{$user->id}", '100.00');

    $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/trustcert/applications/{$applicationId}/pay")
        ->assertStatus(200);

    $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/trustcert/applications/{$applicationId}/pay")
        ->assertStatus(409)
        ->assertJsonPath('error', 'ERR_CERT_409');

    expect(VerificationPayment::where('application_id', $applicationId)->count())->toBe(1);
});
