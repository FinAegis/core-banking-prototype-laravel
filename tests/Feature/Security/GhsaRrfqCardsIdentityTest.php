<?php

declare(strict_types=1);

use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\InviteTeamMember;
use App\Domain\Account\Models\Account;
use App\Domain\AI\MCP\Tools\Account\CreateAccountTool;
use App\Domain\Auth\Exceptions\PrivyEmailOtpException;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Domain\CardIssuance\Services\FinCardCardService;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use App\Http\Controllers\Concerns\ProvisionsPersonalTeam;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\Team;
use App\Models\TeamInvitation as TeamInvitationModel;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use App\Rules\TeamName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
| GHSA-rrfq-93h6-29wj, cluster cards-identity: who froze a card (C20), one
| card creation counter for REST and GraphQL (C41), confirmed email changes
| and the staff role allowlist (C65), timing of the web sign-in code check
| (C45), MCP account.create for the caller only, and team invitation
| residuals (C19).
*/

beforeEach(function (): void {
    // Keep counters, rate limiters and IP blocks out of the Redis cache that
    // CI's parallel processes share.
    Tests\Traits\SharedCacheIsolation::isolate();
    Cache::flush();
    Cache::forget('domain_manager_disabled');
    config([
        'modules.disabled'            => [],
        'cardissuance.default_issuer' => 'demo',
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function grrfqCustomer(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

function grrfqOperator(string $role = 'admin'): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function grrfqGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
{
    $response = $test->actingAs($user, 'sanctum')->postJson('/graphql', [
        'query'     => $query,
        'variables' => $variables,
    ]);
    $response->assertOk();

    /** @var array<string, mixed> $json */
    $json = $response->json();

    return $json;
}

/**
 * @param  array<string, mixed>  $json
 */
function grrfqError(array $json): ?string
{
    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    return $errors[0]['message'] ?? null;
}

/** A demo-issuer card owned by the user. */
function grrfqDemoCard(User $owner): string
{
    return app(CardProvisioningService::class)->createCard((string) $owner->id, 'Card Owner')->cardToken;
}

function grrfqCardStatus(string $token): ?CardStatus
{
    return app(CardProvisioningService::class)->getCard($token)?->status;
}

function grrfqVirtualCard(string $token): VirtualCard
{
    return new VirtualCard(
        cardToken: $token,
        last4: '4242',
        network: CardNetwork::VISA,
        status: CardStatus::ACTIVE,
        cardholderName: 'Card Holder',
        expiresAt: new DateTimeImmutable('+3 years'),
    );
}

function grrfqFinCardSetup(): void
{
    app()->instance(FinCardClient::class, new FinCardClient(
        baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
        tenantId: 't',
        orgId: 'o',
        userId: 'u',
        username: 'x',
        password: 'y',
    ));

    Http::fake([
        'sandbox.finhub.cloud/api/v2.1/admin/*'                          => Http::response(['success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600]]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/freeze'   => Http::response(['success' => true, 'data' => []]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/unfreeze' => Http::response(['success' => true, 'data' => []]),
    ]);
}

function grrfqFinCardCard(User $owner, string $status = 'active'): Card
{
    $holder = new Cardholder();
    $holder->user_id = (string) $owner->id;
    $holder->first_name = 'Jane';
    $holder->last_name = 'Smith';
    $holder->kyc_status = 'verified';
    $holder->issuer_cardholder_id = 'h-1';
    $holder->save();

    $card = new Card();
    $card->user_id = (string) $owner->id;
    $card->cardholder_id = $holder->id;
    $card->issuer_card_token = 'fc-card-1';
    $card->issuer = 'fincard';
    $card->last4 = '4242';
    $card->network = 'visa';
    $card->status = $status;
    $card->currency = 'USD';
    $card->balance_cents = 1000;
    $card->fincard_account_id = 'acc-1';
    $card->save();

    return $card;
}

function grrfqFinCardUnfreezeCalls(): int
{
    return Http::recorded(fn ($request): bool => str_contains($request->url(), '/card/v2/unfreeze'))->count();
}

describe('C20 card freeze source', function (): void {
    it('refuses the owner\'s REST unfreeze of a card an operator froze, and the card stays frozen', function (): void {
        $owner = grrfqCustomer();
        $token = grrfqDemoCard($owner);

        Sanctum::actingAs(grrfqOperator(), ['read', 'write', 'delete']);
        $this->postJson("/api/v1/cards/{$token}/freeze")->assertOk()->assertJsonPath('success', true);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->deleteJson("/api/v1/cards/{$token}/freeze")
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ERR_CARD_007');

        expect(grrfqCardStatus($token))->toBe(CardStatus::FROZEN)
            ->and(CardStaffFreeze::isHeld($token))->toBeTrue();
    });

    it('lets staff take over a freeze the owner placed, so the owner can no longer lift it', function (): void {
        $owner = grrfqCustomer();
        $token = grrfqDemoCard($owner);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson("/api/v1/cards/{$token}/freeze")->assertOk();

        Sanctum::actingAs(grrfqOperator('bank_admin'), ['read', 'write', 'delete']);
        $this->postJson("/api/v1/cards/{$token}/freeze")->assertOk()->assertJsonPath('success', true);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->deleteJson("/api/v1/cards/{$token}/freeze")->assertStatus(403);

        expect(grrfqCardStatus($token))->toBe(CardStatus::FROZEN);
    });

    it('keeps the owner\'s own REST freeze and unfreeze unchanged, and lets staff lift a staff freeze', function (): void {
        $owner = grrfqCustomer();
        $token = grrfqDemoCard($owner);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson("/api/v1/cards/{$token}/freeze")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Card frozen successfully']);
        expect(CardStaffFreeze::isHeld($token))->toBeFalse();

        $this->deleteJson("/api/v1/cards/{$token}/freeze")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Card unfrozen successfully']);
        expect(grrfqCardStatus($token))->toBe(CardStatus::ACTIVE);

        $operator = grrfqOperator();
        Sanctum::actingAs($operator, ['read', 'write', 'delete']);
        $this->postJson("/api/v1/cards/{$token}/freeze")->assertOk();
        $this->deleteJson("/api/v1/cards/{$token}/freeze")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Card unfrozen successfully']);

        expect(grrfqCardStatus($token))->toBe(CardStatus::ACTIVE)
            ->and(CardStaffFreeze::isHeld($token))->toBeFalse();
    });

    it('refuses the owner\'s GraphQL unfreezeCard of a card an operator froze', function (): void {
        $owner = grrfqCustomer();
        $token = grrfqDemoCard($owner);

        $frozen = grrfqGraphql($this, grrfqOperator(), 'mutation ($id: ID!) { freezeCard(id: $id) { status } }', ['id' => $token]);
        expect($frozen['data']['freezeCard']['status'] ?? null)->toBe('frozen');

        $json = grrfqGraphql($this, $owner, 'mutation ($id: ID!) { unfreezeCard(id: $id) { status } }', ['id' => $token]);

        expect(grrfqError($json))->toBe('This card was frozen by our support team. Only they can unfreeze it.')
            ->and(grrfqCardStatus($token))->toBe(CardStatus::FROZEN);
    });

    it('lets the owner freeze and unfreeze their own card over GraphQL', function (): void {
        $owner = grrfqCustomer();
        $token = grrfqDemoCard($owner);

        grrfqGraphql($this, $owner, 'mutation ($id: ID!) { freezeCard(id: $id) { status } }', ['id' => $token]);
        $json = grrfqGraphql($this, $owner, 'mutation ($id: ID!) { unfreezeCard(id: $id) { status } }', ['id' => $token]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['unfreezeCard']['status'] ?? null)->toBe('active');
    });

    it('refuses the owner\'s FinCard unfreeze of a card staff froze through the generic route, without calling FinCard', function (): void {
        config(['cardissuance.default_issuer' => 'fincard']);
        grrfqFinCardSetup();
        $owner = grrfqCustomer();
        $card = grrfqFinCardCard($owner);

        Sanctum::actingAs(grrfqOperator(), ['read', 'write', 'delete']);
        $this->postJson("/api/v1/cards/{$card->issuer_card_token}/freeze")->assertOk()->assertJsonPath('success', true);
        expect($card->fresh()?->status)->toBe('frozen');

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/cards/fincard/{$card->issuer_card_token}/unfreeze")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ERR_CARDS_021');

        expect($card->fresh()?->status)->toBe('frozen')
            ->and(grrfqFinCardUnfreezeCalls())->toBe(0);
    });

    it('keeps the owner\'s own FinCard freeze and unfreeze unchanged', function (): void {
        grrfqFinCardSetup();
        $owner = grrfqCustomer();
        $card = grrfqFinCardCard($owner);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/cards/fincard/{$card->issuer_card_token}/freeze")
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/cards/fincard/{$card->issuer_card_token}/unfreeze")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card_id', 'fc-card-1')
            ->assertJsonPath('data.status', 'active');

        expect(grrfqFinCardUnfreezeCalls())->toBe(1);
    });

    it('treats a FinCard issuer block as a staff freeze until the issuer unfreezes the card', function (): void {
        grrfqFinCardSetup();
        $owner = grrfqCustomer();
        $card = grrfqFinCardCard($owner);
        $service = app(FinCardCardService::class);

        $service->applyCardWebhook('blocked', ['data' => ['cardId' => 'fc-card-1']]);
        expect($card->fresh()?->status)->toBe('frozen')
            ->and(CardStaffFreeze::isHeld('fc-card-1'))->toBeTrue();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/cards/fincard/fc-card-1/unfreeze')
            ->assertStatus(403);

        $service->applyCardWebhook('UnFreeze', ['data' => ['cardId' => 'fc-card-1']]);
        expect($card->fresh()?->status)->toBe('active')
            ->and(CardStaffFreeze::isHeld('fc-card-1'))->toBeFalse();
    });
});

describe('C41 one card creation counter for REST and GraphQL', function (): void {
    beforeEach(function (): void {
        config(['rate_limiting.enabled' => true, 'rate_limiting.force_in_tests' => true]);
    });

    it('stops GraphQL createCard once REST card creation used up the hourly limit', function (): void {
        $user = grrfqCustomer();
        $mock = Mockery::mock(CardProvisioningService::class);
        $mock->shouldReceive('createCard')->times(15)->andReturnUsing(fn (): VirtualCard => grrfqVirtualCard('tok_' . Str::random(8)));
        app()->instance(CardProvisioningService::class, $mock);

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        for ($i = 0; $i < 15; $i++) {
            $this->postJson('/api/v1/cards', ['cardholder_name' => 'Card Holder'])->assertCreated();
        }

        $json = grrfqGraphql($this, $user, 'mutation { createCard(input: { cardholder_id: "Card Holder" }) { id } }');

        expect(grrfqError($json))->toBe('Rate limit for Mutation.createCard exceeded. Try again later.');
    });

    it('stops REST card creation once GraphQL provisionCard used up the hourly limit', function (): void {
        $user = grrfqCustomer();
        $mock = Mockery::mock(CardProvisioningService::class);
        $mock->shouldReceive('createCard')->times(15)->andReturnUsing(fn (): VirtualCard => grrfqVirtualCard('tok_' . Str::random(8)));
        app()->instance(CardProvisioningService::class, $mock);

        $fields = [];
        for ($i = 1; $i <= 15; $i++) {
            $fields[] = "c{$i}: provisionCard(input: { cardholder_name: \"Card Holder\" }) { id }";
        }
        $json = grrfqGraphql($this, $user, 'mutation { ' . implode(' ', $fields) . ' }');
        expect($json)->not->toHaveKey('errors');

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/cards', ['cardholder_name' => 'Card Holder'])->assertStatus(429);
    });

    it('lets a user create cards over both paths while under the shared limit', function (): void {
        $user = grrfqCustomer();
        $mock = Mockery::mock(CardProvisioningService::class);
        $mock->shouldReceive('createCard')->times(15)->andReturnUsing(fn (): VirtualCard => grrfqVirtualCard('tok_' . Str::random(8)));
        app()->instance(CardProvisioningService::class, $mock);

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        for ($i = 0; $i < 14; $i++) {
            $this->postJson('/api/v1/cards', ['cardholder_name' => 'Card Holder'])->assertCreated();
        }

        $json = grrfqGraphql($this, $user, 'mutation { createCard(input: { cardholder_id: "Card Holder" }) { id } }');

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['createCard']['id'] ?? null)->toStartWith('tok_');
    });
});

describe('C65 email changes need confirmation of the new address', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('keeps the old address until the new one is confirmed, then applies it as verified', function (): void {
        $verifiedAt = Carbon::parse('2026-01-01 10:00:00');
        $user = grrfqCustomer(['email' => 'old@example.com', 'email_verified_at' => $verifiedAt]);

        $this->actingAs($user)
            ->put('/user/profile-information', ['name' => 'New Name', 'email' => 'invitee@corp.example'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        expect($user->email)->toBe('old@example.com')
            ->and($user->name)->toBe('New Name')
            ->and(Carbon::parse((string) $user->email_verified_at)->equalTo($verifiedAt))->toBeTrue()
            ->and(User::query()->where('email', 'invitee@corp.example')->exists())->toBeFalse();

        $url = null;
        Notification::assertSentOnDemand(ConfirmEmailChange::class, function (ConfirmEmailChange $notification, array $channels, object $notifiable) use (&$url): bool {
            $url = $notification->confirmationUrl;

            return ($notifiable->routes['mail'] ?? null) === 'invitee@corp.example';
        });

        $this->actingAs($user)->get((string) $url)->assertRedirect(route('profile.show'));

        $user->refresh();
        expect($user->email)->toBe('invitee@corp.example')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Carbon::parse((string) $user->email_verified_at)->equalTo($verifiedAt))->toBeFalse();
    });

    it('refuses a confirmation link opened by another account or with a changed address', function (): void {
        $user = grrfqCustomer(['email' => 'owner@example.com']);
        $other = grrfqCustomer(['email' => 'other@example.com']);

        $this->actingAs($user)->put('/user/profile-information', ['name' => 'Owner', 'email' => 'new@example.com']);

        $url = null;
        Notification::assertSentOnDemand(ConfirmEmailChange::class, function (ConfirmEmailChange $notification) use (&$url): bool {
            $url = $notification->confirmationUrl;

            return true;
        });

        $this->actingAs($other)->get((string) $url)->assertForbidden();

        $tampered = (string) preg_replace('/email=[^&]+/', 'email=' . urlencode(Illuminate\Support\Facades\Crypt::encryptString('victim@example.com')), (string) $url);
        $this->actingAs($user)->get($tampered)->assertForbidden();

        expect($user->fresh()?->email)->toBe('owner@example.com')
            ->and($other->fresh()?->email)->toBe('other@example.com')
            ->and(User::query()->whereIn('email', ['new@example.com', 'victim@example.com'])->exists())->toBeFalse();
    });

    it('refuses an email change on an account linked to the sign-in provider, but saves a name change', function (): void {
        $user = grrfqCustomer(['email' => 'privy@example.com', 'privy_user_id' => 'did:privy:abc123']);

        $this->actingAs($user)
            ->put('/user/profile-information', ['name' => 'Renamed', 'email' => 'someone@example.com'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

        expect($user->fresh()?->email)->toBe('privy@example.com');
        Notification::assertNothingSent();

        $this->actingAs($user)
            ->put('/user/profile-information', ['name' => 'Renamed', 'email' => 'privy@example.com'])
            ->assertSessionHasNoErrors();

        expect($user->fresh()?->name)->toBe('Renamed')
            ->and($user->fresh()?->email)->toBe('privy@example.com');
    });

    it('caps confirmation emails per user and day', function (): void {
        $user = grrfqCustomer(['email' => 'cap@example.com']);

        foreach (['a', 'b', 'c'] as $suffix) {
            $this->actingAs($user)
                ->put('/user/profile-information', ['name' => 'Cap', 'email' => "{$suffix}@example.com"])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($user)
            ->put('/user/profile-information', ['name' => 'Cap', 'email' => 'd@example.com'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

        Notification::assertSentOnDemandTimes(ConfirmEmailChange::class, 3);
        expect($user->fresh()?->email)->toBe('cap@example.com');
    });

    it('lets the real owner of an address sign up with the web email code after someone asked to use it', function (): void {
        config([
            'privy.app_id'                 => 'test-app-id',
            'privy.app_secret'             => 'test-app-secret',
            'privy.web_login_enabled'      => true,
            'registration.api_enabled'     => true,
            'registration.api_invite_only' => false,
        ]);
        Sleep::fake();
        $squatter = grrfqCustomer(['email' => 'squatter@example.com']);
        $this->actingAs($squatter)->put('/user/profile-information', ['name' => 'S', 'email' => 'victim@example.com']);
        auth()->guard('web')->logout();

        $mock = Mockery::mock(PrivyEmailOtpClient::class);
        $mock->shouldReceive('loginWithCode')->once()->with('victim@example.com', '123456')
            ->andReturn(['id' => 'did:privy:victim', 'email' => 'victim@example.com']);
        app()->instance(PrivyEmailOtpClient::class, $mock);

        $this->post(route('login.privy.verify'), ['email' => 'victim@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $victim = User::query()->where('privy_user_id', 'did:privy:victim')->first();
        expect($victim?->email)->toBe('victim@example.com')
            ->and($squatter->fresh()?->email)->toBe('squatter@example.com');
    });

    it('does not count an unverified address as an allowlisted operator in user:audit-staff-roles', function (): void {
        config(['security.staff_role_allowlist' => 'ops@example.com']);
        Role::findOrCreate('compliance_officer', 'web');
        $holder = grrfqCustomer(['email' => 'ops@example.com', 'email_verified_at' => null]);
        $holder->assignRole('compliance_officer');

        $this->artisan('user:audit-staff-roles')
            ->expectsOutputToContain('allowlisted address is not verified')
            ->assertExitCode(1);

        expect($holder->fresh()?->hasRole('compliance_officer'))->toBeTrue();
    });

    it('still counts a verified allowlisted operator in user:audit-staff-roles', function (): void {
        config(['security.staff_role_allowlist' => 'ops@example.com']);
        Role::findOrCreate('compliance_officer', 'web');
        $holder = grrfqCustomer(['email' => 'ops@example.com', 'email_verified_at' => now()]);
        $holder->assignRole('compliance_officer');

        $this->artisan('user:audit-staff-roles')->assertExitCode(0);
    });
});

describe('C45 web email-code check timing', function (): void {
    beforeEach(function (): void {
        Sleep::fake();
        config([
            'privy.app_id'                     => 'test-app-id',
            'privy.app_secret'                 => 'test-app-secret',
            'privy.web_login_enabled'          => true,
            'privy.web_verify_min_duration_ms' => 1500,
        ]);
    });

    it('holds a refused code for an address that may not sign in to the minimum time, without calling Privy', function (): void {
        config(['registration.api_enabled' => false]);
        $mock = Mockery::mock(PrivyEmailOtpClient::class);
        $mock->shouldReceive('loginWithCode')->never();
        app()->instance(PrivyEmailOtpClient::class, $mock);

        $this->post(route('login.privy.verify'), ['email' => 'nobody@example.com', 'code' => '123456'])
            ->assertSessionHasErrors('code');

        Sleep::assertSlept(fn ($duration): bool => $duration->totalMicroseconds > 1_000_000, 1);
        expect(User::query()->where('email', 'nobody@example.com')->exists())->toBeFalse();
    });

    it('holds a wrong code for an address that may sign in to the same minimum time', function (): void {
        grrfqCustomer(['email' => 'member@example.com']);
        $mock = Mockery::mock(PrivyEmailOtpClient::class);
        $mock->shouldReceive('loginWithCode')->once()->andThrow(PrivyEmailOtpException::apiError('/api/v1/passwordless/authenticate', 400, 'invalid code'));
        app()->instance(PrivyEmailOtpClient::class, $mock);

        $this->post(route('login.privy.verify'), ['email' => 'member@example.com', 'code' => '000000'])
            ->assertSessionHasErrors('code');

        Sleep::assertSlept(fn ($duration): bool => $duration->totalMicroseconds > 1_000_000, 1);
        $this->assertGuest();
    });

    it('signs in at once with a correct code', function (): void {
        $member = grrfqCustomer(['email' => 'member@example.com', 'privy_user_id' => 'did:privy:member']);
        $mock = Mockery::mock(PrivyEmailOtpClient::class);
        $mock->shouldReceive('loginWithCode')->once()->andReturn(['id' => 'did:privy:member', 'email' => 'member@example.com']);
        app()->instance(PrivyEmailOtpClient::class, $mock);

        $this->post(route('login.privy.verify'), ['email' => 'member@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        Sleep::assertNeverSlept();
        $this->assertAuthenticatedAs($member);
    });
});

describe('MCP account.create only for the caller', function (): void {
    it('refuses to open an account for another user, whatever the caller\'s role', function (string $role): void {
        $victim = grrfqCustomer();
        $this->actingAs(grrfqOperator($role));

        $tool = app(CreateAccountTool::class);
        $forVictim = $tool->execute(['name' => 'Planted', 'currency' => 'USD', 'type' => 'checking', 'user_uuid' => $victim->uuid]);
        $forUnknown = $tool->execute(['name' => 'Planted', 'currency' => 'USD', 'type' => 'checking', 'user_uuid' => (string) Str::uuid()]);

        expect($forVictim->isSuccess())->toBeFalse()
            ->and($forVictim->getError())->toBe('Unauthorized to create account for this user')
            ->and($forUnknown->getError())->toBe($forVictim->getError())
            ->and(Account::query()->where('user_uuid', $victim->uuid)->count())->toBe(0);
    })->with(['admin', 'banker']);

    it('opens an account for the caller, with or without their own user_uuid', function (): void {
        $caller = grrfqCustomer();
        $this->actingAs($caller);

        $tool = app(CreateAccountTool::class);
        $withUuid = $tool->execute(['name' => 'Mine', 'currency' => 'USD', 'type' => 'checking', 'user_uuid' => strtoupper((string) $caller->uuid)]);
        $without = $tool->execute(['name' => 'Mine too', 'currency' => 'USD', 'type' => 'checking']);

        expect($withUuid->isSuccess())->toBeTrue()
            ->and($without->isSuccess())->toBeTrue()
            ->and(Account::query()->where('user_uuid', $caller->uuid)->count())->toBe(2);
    });
});

describe('C19 team invitation residuals', function (): void {
    it('builds a neutral personal team name when the user\'s name is not a valid team name', function (string $name): void {
        $user = grrfqCustomer(['name' => $name]);
        $provisioner = new class () {
            use ProvisionsPersonalTeam;

            public function provision(User $user): Team
            {
                return $this->ensurePersonalTeam($user);
            }
        };

        expect($provisioner->provision($user)->name)->toBe(TeamName::PERSONAL_TEAM_FALLBACK);
    })->with([
        'web address'   => ['evil.example/login'],
        'domain'        => ['zelta-support.com now'],
        'markdown link' => ['[Unlock](https://evil.example)'],
    ]);

    it('keeps "<first name>\'s Team" for an ordinary name', function (): void {
        $user = grrfqCustomer(['name' => "Łukasz O'Brien"]);
        $provisioner = new class () {
            use ProvisionsPersonalTeam;

            public function provision(User $user): Team
            {
                return $this->ensurePersonalTeam($user);
            }
        };

        expect($provisioner->provision($user)->name)->toBe("Łukasz's Team");
    });

    it('refuses a team name with a web address and keeps a stored one out of the invitation email', function (): void {
        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->ownedTeams()->firstOrFail();
        $original = $team->name;

        expect(fn () => app(CreateTeam::class)->create($owner, ['name' => 'Visit zelta-support.com']))
            ->toThrow(ValidationException::class);
        expect($team->fresh()?->name)->toBe($original);

        // A name stored before the rule existed.
        $team->forceFill(['name' => 'Visit zelta-support.com today'])->save();
        $invitation = TeamInvitationModel::create(['team_id' => $team->id, 'email' => 'victim@example.com', 'role' => 'admin']);
        $html = (new TeamInvitation($invitation))->render();

        expect($html)->not->toContain('zelta-support')
            ->and($html)->toContain('You have been invited to join a team.');
    });

    it('still shows an ordinary team name in the invitation email', function (): void {
        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->ownedTeams()->firstOrFail();
        $team->forceFill(['name' => 'Acme Ltd.'])->save();
        $invitation = TeamInvitationModel::create(['team_id' => $team->id, 'email' => 'colleague@example.com', 'role' => 'admin']);

        expect((new TeamInvitation($invitation))->render())->toContain('You have been invited to join the Acme Ltd. team!');
    });

    it('gives new inviters their own platform-wide pool, so they cannot use up the pool of established inviters', function (): void {
        Mail::fake();
        config(['jetstream.invitation_limits.new_inviters_global_daily' => 2]);
        $invite = app(InviteTeamMember::class);

        $newInviters = [User::factory()->withPersonalTeam()->create(), User::factory()->withPersonalTeam()->create()];
        foreach ($newInviters as $i => $inviter) {
            /** @var Team $inviterTeam */
            $inviterTeam = $inviter->ownedTeams()->firstOrFail();
            $invite->invite($inviter, $inviterTeam, ['a@example.com', 'b@example.com'][$i], 'admin');
        }

        $throwaway = User::factory()->withPersonalTeam()->create();
        /** @var Team $throwawayTeam */
        $throwawayTeam = $throwaway->ownedTeams()->firstOrFail();
        expect(fn () => $invite->invite($throwaway, $throwawayTeam, 'c@example.com', 'admin'))
            ->toThrow(ValidationException::class);
        expect($throwawayTeam->teamInvitations()->count())->toBe(0);

        $established = User::factory()->withPersonalTeam()->create(['created_at' => now()->subDays(30)]);
        /** @var Team $establishedTeam */
        $establishedTeam = $established->ownedTeams()->firstOrFail();
        $invite->invite($established, $establishedTeam, 'd@example.com', 'admin');

        Mail::assertSent(TeamInvitation::class, 3);
    });

    it('gives a new inviter a smaller daily share than an established one', function (): void {
        Mail::fake();
        config(['jetstream.invitation_limits.per_new_inviter_daily' => 2]);
        $invite = app(InviteTeamMember::class);

        $new = User::factory()->withPersonalTeam()->create();
        /** @var Team $newTeam */
        $newTeam = $new->ownedTeams()->firstOrFail();
        $invite->invite($new, $newTeam, 'n1@example.com', 'admin');
        $invite->invite($new, $newTeam, 'n2@example.com', 'admin');
        expect(fn () => $invite->invite($new, $newTeam, 'n3@example.com', 'admin'))
            ->toThrow(ValidationException::class);

        $unverified = User::factory()->withPersonalTeam()->create(['created_at' => now()->subDays(30), 'email_verified_at' => null]);
        /** @var Team $unverifiedTeam */
        $unverifiedTeam = $unverified->ownedTeams()->firstOrFail();
        $invite->invite($unverified, $unverifiedTeam, 'u1@example.com', 'admin');
        $invite->invite($unverified, $unverifiedTeam, 'u2@example.com', 'admin');
        expect(fn () => $invite->invite($unverified, $unverifiedTeam, 'u3@example.com', 'admin'))
            ->toThrow(ValidationException::class);

        $established = User::factory()->withPersonalTeam()->create(['created_at' => now()->subDays(30)]);
        /** @var Team $establishedTeam */
        $establishedTeam = $established->ownedTeams()->firstOrFail();
        foreach (['e1', 'e2', 'e3'] as $local) {
            $invite->invite($established, $establishedTeam, "{$local}@example.com", 'admin');
        }

        Mail::assertSent(TeamInvitation::class, 7);
        expect($establishedTeam->teamInvitations()->count())->toBe(3);
    });
});
