<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\DeFi\Models\DeFiPosition;
use App\Domain\Governance\Models\Poll;
use App\Domain\Microfinance\Models\ShareAccount;
use App\Domain\Microfinance\Services\ShareAccountService;
use App\Domain\Newsletter\Mail\SubscriberConfirmation;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Product\Models\Product;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Domain\User\Models\UserInvitation;
use App\Models\User;
use Database\Factories\StablecoinCollateralPositionFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| Access rules for the web Privy login, the blog Mailchimp sync, the
| stablecoin REST endpoints and GraphQL mutations that act on records of
| other users or on platform configuration.
*/

beforeEach(function (): void {
    // Throttles and the sub-product flags live in the cache store.
    Cache::flush();
    config(['cache.default' => 'array']);
});

/**
 * Bind a Privy OTP client double.
 *
 * @param  callable(MockInterface): void  $configure
 */
function achPrivy(callable $configure): void
{
    /** @var PrivyEmailOtpClient&MockInterface $mock */
    $mock = Mockery::mock(PrivyEmailOtpClient::class);
    $configure($mock);
    app()->instance(PrivyEmailOtpClient::class, $mock);
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function achGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
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
function achGraphqlError(array $json): ?string
{
    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    return $errors[0]['message'] ?? null;
}

function achUserWithRole(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Provision a demo card for the user and return its token.
 */
function achProvisionCard(PHPUnit\Framework\TestCase $test, User $owner): string
{
    $json = achGraphql($test, $owner, 'mutation { provisionCard(input: { cardholder_name: "Card Owner" }) { card_token } }');
    $token = $json['data']['provisionCard']['card_token'] ?? null;
    expect($token)->toBeString();

    return (string) $token;
}

describe('web Privy login', function (): void {
    beforeEach(function (): void {
        config([
            'privy.app_id'            => 'ach-app-id',
            'privy.app_secret'        => 'ach-app-secret',
            'privy.web_login_enabled' => true,
        ]);
    });

    it('answers 404 on both steps while the web Privy login is off', function (): void {
        config(['privy.web_login_enabled' => false, 'registration.api_enabled' => true]);
        achPrivy(function (MockInterface $m): void {
            $m->shouldNotReceive('sendCode');
            $m->shouldNotReceive('loginWithCode');
        });

        $this->post('/login/privy/send', ['email' => 'someone@example.com'])->assertNotFound();
        $this->post('/login/privy/verify', ['email' => 'someone@example.com', 'code' => '123456'])->assertNotFound();
        // The route names stay resolvable for views.
        expect(route('login.privy.send'))->toEndWith('/login/privy/send');
    });

    it('gives an unknown address the same answer as a sent code without calling Privy while registration is closed', function (): void {
        config(['registration.api_enabled' => false]);
        User::factory()->create(['email' => 'known@example.com']);

        achPrivy(function (MockInterface $m): void {
            $m->shouldReceive('sendCode')->once()->with('known@example.com');
        });

        $this->from(route('login'))->post('/login/privy/send', ['email' => 'known@example.com'])
            ->assertRedirect(route('login', ['email' => 'known@example.com', 'step' => 'verify']))
            ->assertSessionHas('status', 'If this email can sign in, a 6-digit code is on its way to known@example.com.')
            ->assertSessionHasNoErrors();

        $this->from(route('login'))->post('/login/privy/send', ['email' => 'Unknown@Example.com'])
            ->assertRedirect(route('login', ['email' => 'unknown@example.com', 'step' => 'verify']))
            ->assertSessionHas('status', 'If this email can sign in, a 6-digit code is on its way to unknown@example.com.')
            ->assertSessionHasNoErrors();
    });

    it('answers an invalid code for an unknown address without calling Privy while registration is closed', function (): void {
        config(['registration.api_enabled' => false]);
        achPrivy(function (MockInterface $m): void {
            $m->shouldNotReceive('loginWithCode');
        });

        $this->post('/login/privy/verify', ['email' => 'unknown@example.com', 'code' => '123456'])
            ->assertRedirect(route('login', ['email' => 'unknown@example.com', 'step' => 'verify']))
            ->assertSessionHasErrors(['code' => 'That code did not work. Try again or request a new one.']);

        expect(User::where('email', 'unknown@example.com')->exists())->toBeFalse();
        $this->assertGuest();
    });

    it('refuses an uninvited address in invite-only mode and serves an invited one', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => true]);
        $inviter = User::factory()->create();
        UserInvitation::query()->create([
            'email'      => 'invited@example.com',
            'token'      => Str::random(64),
            'role'       => 'customer_private',
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDay(),
        ]);

        achPrivy(function (MockInterface $m): void {
            $m->shouldReceive('sendCode')->once()->with('invited@example.com');
            $m->shouldReceive('loginWithCode')->once()->with('invited@example.com', '123456')
                ->andReturn(['id' => 'did:privy:achinvited01', 'email' => 'invited@example.com']);
        });

        $this->post('/login/privy/send', ['email' => 'stranger@example.com'])
            ->assertRedirect(route('login', ['email' => 'stranger@example.com', 'step' => 'verify']))
            ->assertSessionHas('status');
        $this->post('/login/privy/verify', ['email' => 'stranger@example.com', 'code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->post('/login/privy/send', ['email' => 'invited@example.com'])->assertSessionHas('status');
        $this->post('/login/privy/verify', ['email' => 'invited@example.com', 'code' => '123456'])->assertRedirect();

        $this->assertAuthenticated();
        expect(User::where('email', 'invited@example.com')->value('privy_user_id'))->toBe('did:privy:achinvited01');
    });

    it('keeps existing users signing in while registration is closed', function (): void {
        config(['registration.api_enabled' => false]);
        $existing = User::factory()->create([
            'email'           => 'Returning@Example.com',
            'privy_user_id'   => 'did:privy:achreturning1',
            'privy_linked_at' => now(),
        ]);

        achPrivy(function (MockInterface $m): void {
            $m->shouldReceive('sendCode')->once()->with('returning@example.com');
            $m->shouldReceive('loginWithCode')->once()->with('returning@example.com', '654321')
                ->andReturn(['id' => 'did:privy:achreturning1', 'email' => 'returning@example.com']);
        });

        $this->post('/login/privy/send', ['email' => 'returning@example.com'])
            ->assertRedirect(route('login', ['email' => 'returning@example.com', 'step' => 'verify']));
        $this->post('/login/privy/verify', ['email' => 'returning@example.com', 'code' => '654321'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($existing);
    });

    it('still calls Privy for a new address while registration is open', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);
        achPrivy(function (MockInterface $m): void {
            $m->shouldReceive('sendCode')->once()->with('newcomer@example.com');
        });

        $this->post('/login/privy/send', ['email' => 'newcomer@example.com'])->assertSessionHas('status');
    });

    it('gives the same answer when Privy fails to send the code', function (): void {
        config(['registration.api_enabled' => false]);
        User::factory()->create(['email' => 'known@example.com']);

        achPrivy(function (MockInterface $m): void {
            $m->shouldReceive('sendCode')->once()->with('known@example.com')
                ->andThrow(new App\Domain\Auth\Exceptions\PrivyEmailOtpException('rate limited'));
        });

        $this->from(route('login'))->post('/login/privy/send', ['email' => 'known@example.com'])
            ->assertRedirect(route('login', ['email' => 'known@example.com', 'step' => 'verify']))
            ->assertSessionHas('status', 'If this email can sign in, a 6-digit code is on its way to known@example.com.')
            ->assertSessionHasNoErrors();

        $this->from(route('login'))->post('/login/privy/send', ['email' => 'nobody@example.com'])
            ->assertRedirect(route('login', ['email' => 'nobody@example.com', 'step' => 'verify']))
            ->assertSessionHas('status', 'If this email can sign in, a 6-digit code is on its way to nobody@example.com.')
            ->assertSessionHasNoErrors();
    });

    it('still refuses account creation when Privy resolves a different new address while closed', function (): void {
        config(['registration.api_enabled' => false]);
        User::factory()->create(['email' => 'typed@example.com']);

        achPrivy(function (MockInterface $m): void {
            $m->shouldReceive('loginWithCode')->once()
                ->andReturn(['id' => 'did:privy:achdifferent1', 'email' => 'different@example.com']);
        });

        $this->post('/login/privy/verify', ['email' => 'typed@example.com', 'code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error_code', 'ERR_REG_001');

        expect(User::where('email', 'different@example.com')->exists())->toBeFalse();
        $this->assertGuest();
    });
});

describe('blog subscribers and Mailchimp', function (): void {
    beforeEach(function (): void {
        Mail::fake();
        Http::fake(['*.api.mailchimp.com/*' => Http::response(['id' => 'm1'], 200)]);
        config([
            'services.mailchimp.api_key' => 'achkey0123456789-us7',
            'services.mailchimp.list_id' => 'ach-list',
        ]);
    });

    it('reaches Mailchimp only after the confirmation link is opened, and once', function (): void {
        $this->postJson('/blog/subscribe', ['email' => 'reader@example.com'])->assertOk();
        // A repeated form post for a pending address does not reach Mailchimp either.
        $this->postJson('/blog/subscribe', ['email' => 'reader@example.com'])->assertOk();

        Http::assertNothingSent();

        $url = null;
        Mail::assertQueued(SubscriberConfirmation::class, function (SubscriberConfirmation $mail) use (&$url): bool {
            $url = $mail->confirmationUrl;

            return $mail->hasTo('reader@example.com');
        });
        expect($url)->toBeString();

        // Opening the link only shows the confirm button; the POST confirms.
        $this->get((string) $url)->assertOk()->assertSee('Confirm subscription');
        Http::assertNothingSent();
        $this->post((string) $url)->assertOk()->assertSee('Subscription confirmed');
        // Confirming again does not push the address again.
        $this->post((string) $url)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://us7.api.mailchimp.com/3.0/lists/ach-list/members'
            && $request['email_address'] === 'reader@example.com'
            && $request['status'] === 'subscribed');
    });

    it('does not reach Mailchimp for an unconfirmed or invalid link', function (): void {
        $this->postJson('/blog/subscribe', ['email' => 'pending@example.com'])->assertOk();
        $subscriber = Subscriber::where('email', 'pending@example.com')->firstOrFail();

        $forged = Illuminate\Support\Facades\URL::temporarySignedRoute(
            'subscriber.confirm',
            now()->addHour(),
            ['subscriber' => $subscriber->id, 'token' => str_repeat('x', 64)]
        );

        $this->get($forged)->assertOk()->assertSee('Link expired or invalid');
        $this->post($forged)->assertOk()->assertSee('Link expired or invalid');
        $this->get('/subscriber/confirm/' . $subscriber->id . '?token=abc')->assertOk()->assertSee('Link expired or invalid');

        Http::assertNothingSent();
        expect($subscriber->fresh()?->status)->toBe(Subscriber::STATUS_PENDING);
    });

    it('syncs a pending subscriber from another form who asked for blog updates', function (): void {
        $this->postJson('/subscriber/' . Subscriber::SOURCE_FOOTER, ['email' => 'both@example.com'])->assertOk();
        $this->postJson('/blog/subscribe', ['email' => 'both@example.com'])->assertOk();

        Http::assertNothingSent();

        $url = null;
        Mail::assertQueued(SubscriberConfirmation::class, function (SubscriberConfirmation $mail) use (&$url): bool {
            $url = $mail->confirmationUrl;

            return $mail->hasTo('both@example.com');
        });

        $this->post((string) $url)->assertOk();

        Http::assertSentCount(1);
    });
});

describe('stablecoin REST endpoints', function (): void {
    beforeEach(function (): void {
        Feature::define('sub_product.stablecoins', true);
        config(['ledger.direct_funding_enabled' => true]);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
        );
        Stablecoin::create([
            'code'                   => 'ACHUSD',
            'name'                   => 'Access USD',
            'symbol'                 => 'ACHUSD',
            'peg_asset_code'         => 'USD',
            'peg_ratio'              => 1.0,
            'target_price'           => 1.0,
            'stability_mechanism'    => 'collateralized',
            'collateral_ratio'       => 1.5,
            'min_collateral_ratio'   => 1.2,
            'liquidation_penalty'    => 0.1,
            'total_supply'           => 0,
            'max_supply'             => 10000000,
            'total_collateral_value' => 0,
            'mint_fee'               => 0.005,
            'burn_fee'               => 0.003,
            'precision'              => 2,
            'is_active'              => true,
            'minting_enabled'        => true,
            'burning_enabled'        => true,
        ]);
    });

    // Configuration writes, as [method, uri, payload].
    $adminWrites = [
        'create'            => ['POST', '/api/v2/stablecoins', []],
        'update'            => ['PUT', '/api/v2/stablecoins/ACHUSD', ['liquidation_penalty' => 0.5]],
        'deactivate'        => ['POST', '/api/v2/stablecoins/ACHUSD/deactivate', []],
        'reactivate'        => ['POST', '/api/v2/stablecoins/ACHUSD/reactivate', []],
        'execute-stability' => ['POST', '/api/v2/stablecoins/ACHUSD/execute-stability', []],
    ];

    it('requires authentication for configuration writes', function (string $method, string $uri, array $payload): void {
        $this->json($method, $uri, $payload)->assertUnauthorized();

        expect((float) Stablecoin::findOrFail('ACHUSD')->liquidation_penalty)->toBe(0.1)
            ->and(Stablecoin::findOrFail('ACHUSD')->is_active)->toBeTrue();
    })->with($adminWrites);

    it('refuses configuration writes from users without an operator role', function (string $method, string $uri, array $payload): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->json($method, $uri, $payload)->assertForbidden();

        expect((float) Stablecoin::findOrFail('ACHUSD')->liquidation_penalty)->toBe(0.1)
            ->and(Stablecoin::findOrFail('ACHUSD')->is_active)->toBeTrue();
    })->with($adminWrites);

    it('lets stablecoin operators change the configuration', function (string $role): void {
        Sanctum::actingAs(achUserWithRole($role), ['read', 'write', 'delete']);

        $this->putJson('/api/v2/stablecoins/ACHUSD', ['liquidation_penalty' => 0.2])->assertOk();
        $this->postJson('/api/v2/stablecoins/ACHUSD/deactivate')->assertOk();

        $stablecoin = Stablecoin::findOrFail('ACHUSD');
        expect((float) $stablecoin->liquidation_penalty)->toBe(0.2)
            ->and($stablecoin->is_active)->toBeFalse();
    })->with(['admin', 'super_admin', 'bank_admin', 'stablecoin_operator']);

    it('keeps the read endpoints public', function (): void {
        $this->getJson('/api/v2/stablecoins/ACHUSD')->assertOk();
    });

    $mintPayload = fn (string $accountUuid): array => [
        'account_uuid'          => $accountUuid,
        'stablecoin_code'       => 'ACHUSD',
        'collateral_asset_code' => 'USD',
        'collateral_amount'     => 1500,
        'mint_amount'           => 1000,
    ];

    it('refuses position writes on another user\'s account', function () use ($mintPayload): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);

        /** @var StablecoinIssuanceService&MockInterface $issuance */
        $issuance = Mockery::mock(StablecoinIssuanceService::class);
        $issuance->shouldNotReceive('mint', 'burn', 'addCollateral');
        app()->instance(StablecoinIssuanceService::class, $issuance);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->postJson('/api/v2/stablecoin-operations/mint', $mintPayload($account->uuid))
            ->assertForbidden()
            ->assertJson(['error' => 'FORBIDDEN']);
        $this->postJson('/api/v2/stablecoin-operations/burn', [
            'account_uuid'    => $account->uuid,
            'stablecoin_code' => 'ACHUSD',
            'burn_amount'     => 10,
        ])->assertForbidden();
        $this->postJson('/api/v2/stablecoin-operations/add-collateral', [
            'account_uuid'          => $account->uuid,
            'stablecoin_code'       => 'ACHUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount'     => 10,
        ])->assertForbidden();
    });

    it('mints for the account owner while the ledger flag is on', function () use ($mintPayload): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $position = StablecoinCollateralPositionFactory::new()->create([
            'account_uuid'          => $account->uuid,
            'stablecoin_code'       => 'ACHUSD',
            'collateral_asset_code' => 'USD',
        ]);

        /** @var StablecoinIssuanceService&MockInterface $issuance */
        $issuance = Mockery::mock(StablecoinIssuanceService::class);
        $issuance->shouldReceive('mint')->once()
            ->withArgs(fn (Account $target): bool => $target->uuid === $account->uuid)
            ->andReturn($position);
        app()->instance(StablecoinIssuanceService::class, $issuance);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->postJson('/api/v2/stablecoin-operations/mint', $mintPayload($account->uuid))
            ->assertOk()
            ->assertJson(['message' => 'Stablecoin minted successfully']);
    });

    it('answers 404 on position writes while the ledger flag is off', function () use ($mintPayload): void {
        config(['ledger.direct_funding_enabled' => false]);
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->postJson('/api/v2/stablecoin-operations/mint', $mintPayload($account->uuid))->assertNotFound();
        $this->postJson('/api/v2/stablecoin-operations/burn', [])->assertNotFound();
        $this->postJson('/api/v2/stablecoin-operations/add-collateral', [])->assertNotFound();
    });

    it('requires authentication for liquidation', function (): void {
        $this->postJson('/api/v2/stablecoin-operations/liquidation/execute')->assertUnauthorized();
        $this->postJson('/api/v2/stablecoin-operations/liquidation/positions/' . Str::uuid()->toString())->assertUnauthorized();
    });

    it('shows positions only to the account owner or a stablecoin operator', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $position = StablecoinCollateralPositionFactory::new()->createOne([
            'account_uuid'          => $account->uuid,
            'stablecoin_code'       => 'ACHUSD',
            'collateral_asset_code' => 'USD',
            // Fixed amounts: the factory's random ones can give a ratio above
            // what decimal(8,4) holds, and the positions read stores the ratio.
            'collateral_amount' => 150000,
            'debt_amount'       => 100000,
            'collateral_ratio'  => 1.5,
        ]);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->getJson("/api/v2/stablecoin-operations/accounts/{$account->uuid}/positions")
            ->assertForbidden()
            ->assertJson(['error' => 'FORBIDDEN']);
        $this->getJson("/api/v2/stablecoin-operations/positions/{$position->uuid}")
            ->assertForbidden()
            ->assertJsonMissingPath('data');

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson("/api/v2/stablecoin-operations/accounts/{$account->uuid}/positions")
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $position->uuid);
        $this->getJson("/api/v2/stablecoin-operations/positions/{$position->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $position->uuid);

        Sanctum::actingAs(achUserWithRole('stablecoin_operator'), ['read', 'write', 'delete']);
        $this->getJson("/api/v2/stablecoin-operations/positions/{$position->uuid}")->assertOk();
    });

    it('puts the web console mint and burn behind the ledger flag and the sub-product gate', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Illuminate\Routing\Route $route): bool => in_array('POST', $route->methods(), true)
                && in_array($route->uri(), ['wallet/stablecoin-operations/mint', 'wallet/stablecoin-operations/burn'], true));

        expect($routes)->toHaveCount(2);
        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('feature:ledger.direct_funding_enabled', 'sub_product:stablecoins');
        }

        config(['ledger.direct_funding_enabled' => false]);
        $this->actingAs(achUserWithRole('stablecoin_operator'))
            ->post('/wallet/stablecoin-operations/mint', [])
            ->assertNotFound();
    });

    it('uses one stablecoin operator role set for REST, GraphQL and the web console', function (): void {
        expect(App\GraphQL\Mutations\Stablecoin\MintStablecoinMutation::OPERATOR_ROLES)
            ->toBe(App\Support\StaffRoles::STABLECOIN_OPERATORS);
    });

    it('keeps the sub-product gate', function (): void {
        Feature::define('sub_product.stablecoins', false);
        Cache::flush();
        Sanctum::actingAs(achUserWithRole('super_admin'), ['read', 'write', 'delete']);

        $this->putJson('/api/v2/stablecoins/ACHUSD', ['liquidation_penalty' => 0.2])->assertForbidden();
        expect((float) Stablecoin::findOrFail('ACHUSD')->liquidation_penalty)->toBe(0.1);
    });
});

describe('GraphQL plugin, merchant and soulbound token mutations', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    it('limits plugin management to administrators', function (string $mutation): void {
        $json = achGraphql($this, User::factory()->create(), $mutation);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage plugins.');

        $json = achGraphql($this, achUserWithRole('bank_admin'), $mutation);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage plugins.');

        foreach (['admin', 'super_admin'] as $role) {
            $json = achGraphql($this, achUserWithRole($role), $mutation);
            expect(achGraphqlError($json))->not->toBe('You are not authorized to manage plugins.');
        }
    })->with([
        'enablePlugin'  => 'mutation { enablePlugin(id: "999") { success message } }',
        'disablePlugin' => 'mutation { disablePlugin(id: "999") { success message } }',
        'scanPlugin'    => 'mutation { scanPlugin(id: "999") { plugin safe issue_count } }',
    ]);

    it('limits merchant approval and suspension to operators', function (string $mutation): void {
        $json = achGraphql($this, User::factory()->create(), $mutation);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage merchants.');

        $json = achGraphql($this, achUserWithRole('bank_admin'), $mutation);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage merchants.');
    })->with([
        'approveMerchant' => 'mutation { approveMerchant(id: "999") { id } }',
        'suspendMerchant' => 'mutation { suspendMerchant(id: "999") { id } }',
    ]);

    it('limits soulbound token issuance and revocation to operators', function (string $mutation): void {
        $json = achGraphql($this, User::factory()->create(), $mutation);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage soulbound tokens.');

        $json = achGraphql($this, achUserWithRole('admin'), $mutation);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage soulbound tokens.');
    })->with([
        'issueSoulboundToken'  => 'mutation { issueSoulboundToken(input: { token_type: "kyc_verified", recipient_address: "0xabc" }) { token_hash } }',
        'revokeSoulboundToken' => 'mutation { revokeSoulboundToken(token_hash: "abc", reason: "x") }',
    ]);
});

describe('GraphQL card mutations', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => [], 'cardissuance.default_issuer' => 'demo']);
    });

    it('lets only the card owner or an operator freeze, unfreeze or cancel a card', function (string $mutation): void {
        $owner = User::factory()->create();
        $token = achProvisionCard($this, $owner);

        $json = achGraphql($this, User::factory()->create(), $mutation, ['id' => $token]);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage this card.');

        $json = achGraphql($this, $owner, $mutation, ['id' => $token]);
        expect($json)->not->toHaveKey('errors');

        $operatorToken = achProvisionCard($this, $owner);
        $json = achGraphql($this, achUserWithRole('bank_admin'), $mutation, ['id' => $operatorToken]);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage this card.');
    })->with([
        'freezeCard'   => 'mutation ($id: ID!) { freezeCard(id: $id) { card_token status } }',
        'unfreezeCard' => 'mutation ($id: ID!) { unfreezeCard(id: $id) { card_token status } }',
        'cancelCard'   => 'mutation ($id: ID!) { cancelCard(id: $id) { card_token status } }',
    ]);

    it('leaves another user\'s card untouched', function (): void {
        $owner = User::factory()->create();
        $token = achProvisionCard($this, $owner);

        $json = achGraphql($this, User::factory()->create(), 'mutation ($id: ID!) { cancelCard(id: $id) { status } }', ['id' => $token]);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage this card.');

        expect(app(CardProvisioningService::class)->getCard($token)?->status)->toBe(CardStatus::ACTIVE);
    });

    it('accepts a card linked to the user by a local card record', function (): void {
        $owner = User::factory()->create();
        $token = achProvisionCard($this, User::factory()->create());
        $cardholder = Cardholder::create([
            'user_id'    => $owner->id,
            'first_name' => 'Card',
            'last_name'  => 'Owner',
            'kyc_status' => 'verified',
        ]);
        Card::query()->create([
            'user_id'           => $owner->id,
            'cardholder_id'     => $cardholder->id,
            'issuer_card_token' => $token,
            'issuer'            => 'demo',
            'last4'             => '4242',
            'network'           => 'visa',
            'status'            => 'active',
        ]);

        $json = achGraphql($this, $owner, 'mutation ($id: ID!) { freezeCard(id: $id) { status } }', ['id' => $token]);
        expect($json)->not->toHaveKey('errors');
    });

    it('shows card transactions only to the card owner', function (): void {
        $owner = User::factory()->create();
        $token = achProvisionCard($this, $owner);
        $query = 'query ($id: String!) { cardTransactions(card_id: $id) { id card_id } }';

        $json = achGraphql($this, $owner, $query, ['id' => $token]);
        expect($json['data']['cardTransactions'])->not->toBeEmpty();

        $json = achGraphql($this, User::factory()->create(), $query, ['id' => $token]);
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['cardTransactions'])->toBe([]);
    });
});

describe('GraphQL mutations on other users\' records', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    it('lets only the initiator or an operator cancel a bank transfer', function (): void {
        $owner = User::factory()->create();
        $insert = function () use ($owner): string {
            // bank_transfers.id is a uuid column (36 characters on MySQL).
            $id = Str::uuid()->toString();
            DB::table('bank_transfers')->insert([
                'id'              => $id,
                'user_uuid'       => $owner->uuid,
                'from_bank_code'  => 'TEST_BANK',
                'from_account_id' => 'acct-001',
                'to_bank_code'    => 'TEST_BANK',
                'to_account_id'   => 'acct-002',
                'amount'          => 50.00,
                'currency'        => 'EUR',
                'type'            => 'SEPA',
                'status'          => 'initiated',
                'metadata'        => json_encode(['status_history' => []]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return $id;
        };
        $query = 'mutation ($id: String!) { cancelTransfer(transfer_id: $id) { status } }';

        $first = $insert();
        // Another user's transfer answers like an unknown id (GHSA-rrfq).
        $json = achGraphql($this, User::factory()->create(), $query, ['id' => $first]);
        expect($json['data']['cancelTransfer']['status'] ?? null)->toBe('not_found')
            ->and(DB::table('bank_transfers')->where('id', $first)->value('status'))->toBe('initiated');

        $json = achGraphql($this, $owner, $query, ['id' => $first]);
        expect($json['data']['cancelTransfer']['status'] ?? null)->toBe('cancelled');

        $second = $insert();
        $json = achGraphql($this, achUserWithRole('admin'), $query, ['id' => $second]);
        expect($json['data']['cancelTransfer']['status'] ?? null)->toBe('cancelled');
    });

    it('lets only the owner or an operator close a DeFi position', function (): void {
        $owner = User::factory()->create();
        $create = fn (): DeFiPosition => DeFiPosition::create([
            'user_id'   => $owner->id,
            'protocol'  => 'uniswap_v3',
            'type'      => 'lp',
            'status'    => 'active',
            'chain'     => 'ethereum',
            'asset'     => 'ETH/USDC',
            'amount'    => '1.0',
            'value_usd' => 100.00,
            'apy'       => 1.0,
            'opened_at' => now(),
        ]);
        $query = 'mutation ($id: ID!) { closePosition(input: { position_id: $id }) { id } }';

        $position = $create();
        $json = achGraphql($this, User::factory()->create(), $query, ['id' => $position->id]);
        expect(achGraphqlError($json))->toBe('You are not authorized to close this position.')
            ->and($position->fresh()?->status->value)->toBe('active');

        $json = achGraphql($this, $owner, $query, ['id' => $position->id]);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to close this position.');

        $other = $create();
        $json = achGraphql($this, achUserWithRole('super_admin'), $query, ['id' => $other->id]);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to close this position.');
    });

    it('lets users submit KYC documents only for themselves unless they are compliance staff', function (): void {
        $target = User::factory()->create();
        $query = 'mutation ($input: SubmitKycDocumentInput!) { submitKycDocument(input: $input) { id status } }';
        $input = fn (User $user): array => ['input' => [
            'user_id'          => $user->id,
            'type'             => 'identity',
            'document_type'    => 'passport',
            'document_country' => 'US',
        ]];

        $json = achGraphql($this, User::factory()->create(), $query, $input($target));
        expect(achGraphqlError($json))->toBe('You are not authorized to submit KYC documents for this user.');

        $json = achGraphql($this, $target, $query, $input($target));
        expect(achGraphqlError($json))->not->toBe('You are not authorized to submit KYC documents for this user.');

        $json = achGraphql($this, achUserWithRole('compliance_officer'), $query, $input($target));
        expect(achGraphqlError($json))->not->toBe('You are not authorized to submit KYC documents for this user.');
    });

    it('limits AML checks to compliance staff', function (): void {
        $target = User::factory()->create();
        $query = 'mutation ($input: TriggerAmlCheckInput!) { triggerAmlCheck(input: $input) { id } }';
        $vars = ['input' => ['entity_type' => User::class, 'entity_id' => (string) $target->id]];

        $json = achGraphql($this, User::factory()->create(), $query, $vars);
        expect(achGraphqlError($json))->toBe('You are not authorized to run AML checks.')
            ->and(ComplianceAlert::query()->count())->toBe(0);

        $json = achGraphql($this, achUserWithRole('compliance_officer'), $query, $vars);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to run AML checks.');
    });

    it('lets only the holder or an operator buy shares on a share account', function (): void {
        $holder = User::factory()->create();
        $account = app(ShareAccountService::class)->openAccount($holder->id);
        $query = 'mutation ($id: ID!) { mfiPurchaseShares(input: { account_id: $id, shares: 5 }) { id } }';

        $json = achGraphql($this, User::factory()->create(), $query, ['id' => $account->id]);
        expect(achGraphqlError($json))->toBe('You are not authorized to use this share account.')
            ->and((int) ShareAccount::query()->findOrFail($account->id)->shares_purchased)->toBe(0);

        $json = achGraphql($this, $holder, $query, ['id' => $account->id]);
        expect($json)->not->toHaveKey('errors')
            ->and((int) ShareAccount::query()->findOrFail($account->id)->shares_purchased)->toBe(5);

        $json = achGraphql($this, achUserWithRole('bank_admin'), $query, ['id' => $account->id]);
        expect($json)->not->toHaveKey('errors');
    });

    it('lets only the creator or an operator manage a poll', function (string $mutation): void {
        $creator = User::factory()->create();
        $poll = Poll::create([
            'uuid'        => Str::uuid()->toString(),
            'title'       => 'Access poll',
            'description' => 'x',
            'type'        => 'single_choice',
            'status'      => 'draft',
            'options'     => [
                ['id' => 'yes', 'label' => 'Yes'],
                ['id' => 'no', 'label' => 'No'],
            ],
            'start_date'             => now()->subDay(),
            'end_date'               => now()->addDays(7),
            'created_by'             => $creator->uuid,
            'required_participation' => 0,
            'voting_power_strategy'  => 'one_user_one_vote',
        ]);

        $json = achGraphql($this, User::factory()->create(), $mutation, ['id' => $poll->id]);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage this poll.')
            ->and($poll->fresh()?->status)->toBe($poll->status);

        $json = achGraphql($this, $creator, $mutation, ['id' => $poll->id]);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage this poll.');

        $json = achGraphql($this, achUserWithRole('admin'), $mutation, ['id' => $poll->id]);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage this poll.');
    })->with([
        'activatePoll' => 'mutation ($id: ID!) { activatePoll(id: $id) { id } }',
        'completePoll' => 'mutation ($id: ID!) { completePoll(id: $id) { id } }',
        'cancelPoll'   => 'mutation ($id: ID!) { cancelPoll(id: $id, reason: "x") { id } }',
    ]);
});

describe('GraphQL back-office mutations', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    it('limits general ledger writes to operators', function (string $mutation, array $variables): void {
        $json = achGraphql($this, User::factory()->create(), $mutation, $variables);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage the general ledger.');

        $json = achGraphql($this, achUserWithRole('bank_admin'), $mutation, $variables);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage the general ledger.');
    })->with([
        'ledgerCreateAccount' => [
            'mutation ($input: CreateLedgerAccountInput!) { ledgerCreateAccount(input: $input) { __typename } }',
            ['input' => ['code' => '9100', 'name' => 'Access test', 'type' => 'asset']],
        ],
        'ledgerPostEntry' => [
            'mutation ($lines: [JournalLineInput!]!) { ledgerPostEntry(description: "x", lines: $lines) { __typename } }',
            ['lines' => [
                ['account_code' => '1000', 'debit' => '5', 'credit' => '0'],
                ['account_code' => '4000', 'debit' => '0', 'credit' => '5'],
            ]],
        ],
        'ledgerReverseEntry' => [
            'mutation { ledgerReverseEntry(id: "1", reason: "x") { __typename } }',
            [],
        ],
    ]);

    it('limits regulatory submissions and checks to compliance staff', function (string $mutation, array $variables): void {
        $json = achGraphql($this, User::factory()->create(), $mutation, $variables);
        expect(achGraphqlError($json))->toStartWith('You are not authorized to');

        $json = achGraphql($this, achUserWithRole('compliance_officer'), $mutation, $variables);
        expect((string) achGraphqlError($json))->not->toStartWith('You are not authorized to');
    })->with([
        'submitRegulatoryReport' => [
            'mutation ($input: SubmitRegulatoryReportInput!) { submitRegulatoryReport(input: $input) }',
            ['input' => ['jurisdiction' => 'EU', 'report_type' => 'MICA', 'report_data' => '{}']],
        ],
        'runHealthChecks' => [
            'mutation { runHealthChecks }',
            [],
        ],
        'submitReport' => [
            'mutation ($input: SubmitReportInput!) { submitReport(input: $input) { id } }',
            ['input' => ['report_type' => 'AML', 'jurisdiction' => 'UK']],
        ],
    ]);

    it('limits product activation to operators', function (): void {
        $product = Product::create([
            'id'          => Str::uuid()->toString(),
            'name'        => 'Access Savings',
            'description' => 'x',
            'category'    => 'savings',
            'type'        => 'deposit',
            'status'      => 'draft',
        ]);
        $query = 'mutation ($id: ID!) { activateProduct(input: { id: $id }) { id status } }';

        $json = achGraphql($this, User::factory()->create(), $query, ['id' => $product->id]);
        expect(achGraphqlError($json))->toBe('You are not authorized to activate products.')
            ->and($product->fresh()?->status)->toBe('draft');

        $json = achGraphql($this, achUserWithRole('admin'), $query, ['id' => $product->id]);
        expect($json['data']['activateProduct']['status'] ?? null)->toBe('active');
    });
});

describe('REST endpoints that match the GraphQL role checks', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    it('limits general ledger writes to operators', function (): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/ledger/accounts', ['code' => '9300', 'name' => 'Access asset', 'type' => 'asset'])->assertForbidden();
        $this->postJson('/api/v1/ledger/entries', [
            'description' => 'x',
            'lines'       => [
                ['account_code' => '9300', 'debit' => 5, 'credit' => 0],
                ['account_code' => '9301', 'debit' => 0, 'credit' => 5],
            ],
        ])->assertForbidden();
        $this->postJson('/api/v1/ledger/entries/' . Str::uuid()->toString() . '/reverse', ['reason' => 'x'])->assertForbidden();

        expect(DB::table('ledger_accounts')->where('code', '9300')->exists())->toBeFalse();

        Sanctum::actingAs(achUserWithRole('bank_admin'), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/ledger/accounts', ['code' => '9300', 'name' => 'Access asset', 'type' => 'asset'])->assertCreated();
    });

    it('limits regulatory report submission to compliance staff', function (): void {
        $payload = ['report_type' => 'sar', 'jurisdiction' => 'US', 'report_data' => ['x' => 1]];

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/regtech/reports', $payload)->assertForbidden();

        Sanctum::actingAs(achUserWithRole('compliance_officer'), ['read', 'write', 'delete']);
        $this->postJson('/api/regtech/reports', $payload)->assertStatus(201);
    });

    it('lets only the holder or an operator buy or redeem shares', function (): void {
        $holder = User::factory()->create();
        $account = app(ShareAccountService::class)->openAccount($holder->id);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson("/api/v1/microfinance/share-accounts/{$account->id}/purchase", ['shares' => 3])->assertForbidden();
        $this->postJson("/api/v1/microfinance/share-accounts/{$account->id}/redeem", ['shares' => 1])->assertForbidden();
        expect((int) ShareAccount::query()->findOrFail($account->id)->shares_purchased)->toBe(0);

        Sanctum::actingAs($holder, ['read', 'write', 'delete']);
        $this->postJson("/api/v1/microfinance/share-accounts/{$account->id}/purchase", ['shares' => 3])->assertOk();

        Sanctum::actingAs(achUserWithRole('bank_admin'), ['read', 'write', 'delete']);
        $this->postJson("/api/v1/microfinance/share-accounts/{$account->id}/redeem", ['shares' => 1])->assertOk();
        expect((int) ShareAccount::query()->findOrFail($account->id)->shares_purchased)->toBe(2);
    });

    it('leaves group set-up to operators and member management to the group leader or an operator', function (): void {
        $group = app(App\Domain\Microfinance\Services\GroupLendingService::class)->createGroup('Access group', 'weekly');
        $plain = User::factory()->create();
        $leader = User::factory()->create();

        Sanctum::actingAs($plain, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/microfinance/groups', ['name' => 'Mine'])->assertForbidden();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/activate")->assertForbidden();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/close")->assertForbidden();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/members", ['user_id' => $plain->id, 'role' => 'leader'])->assertForbidden();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/meetings", ['attendees_count' => 1])->assertForbidden();
        expect($group->fresh()?->status?->value)->toBe('pending');

        $operator = achUserWithRole('bank_admin');
        Sanctum::actingAs($operator, ['read', 'write', 'delete']);
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/activate")->assertOk();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/members", ['user_id' => $leader->id, 'role' => 'leader'])->assertCreated();

        // The leader adds plain members only, and removes them.
        Sanctum::actingAs($leader, ['read', 'write', 'delete']);
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/members", ['user_id' => $plain->id, 'role' => 'treasurer'])->assertForbidden();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/members", ['user_id' => $plain->id])->assertCreated();
        $this->deleteJson("/api/v1/microfinance/groups/{$group->id}/members/{$plain->id}")->assertOk();
        $this->postJson("/api/v1/microfinance/groups/{$group->id}/close")->assertForbidden();
    });

    it('lets only the creator or an operator activate a poll', function (): void {
        $creator = User::factory()->create();
        $poll = achPoll($creator);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson("/api/polls/{$poll->uuid}/activate")->assertForbidden();
        expect($poll->fresh()?->status?->value)->toBe('draft');

        Sanctum::actingAs($creator, ['read', 'write', 'delete']);
        $this->postJson("/api/polls/{$poll->uuid}/activate")->assertOk();
        expect($poll->fresh()?->status?->value)->toBe('active');
    });

    it('leaves execution workflows on polls to operators', function (): void {
        $payload = [
            'title'              => 'Workflow poll',
            'type'               => 'single_choice',
            'options'            => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']],
            'start_date'         => now()->addMinute()->toIso8601String(),
            'end_date'           => now()->addMinutes(2)->toIso8601String(),
            'execution_workflow' => 'AddAssetWorkflow',
        ];

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/polls', $payload)->assertForbidden();
        expect(Poll::query()->where('title', 'Workflow poll')->exists())->toBeFalse();

        // A poll that already has a workflow: its creator cannot activate or
        // complete it.
        $creator = User::factory()->create();
        $poll = achPoll($creator, ['execution_workflow' => 'AddAssetWorkflow']);

        Sanctum::actingAs($creator, ['read', 'write', 'delete']);
        $this->postJson("/api/polls/{$poll->uuid}/activate")->assertForbidden();
        foreach (['activatePoll', 'completePoll'] as $mutation) {
            $json = achGraphql($this, $creator, 'mutation ($id: ID!) { ' . $mutation . '(id: $id) { id } }', ['id' => $poll->id]);
            expect(achGraphqlError($json))->toBe('You are not authorized to manage this poll.');
        }
        expect($poll->fresh()?->status?->value)->toBe('draft');

        $operator = achUserWithRole('admin');
        Sanctum::actingAs($operator, ['read', 'write', 'delete']);
        $this->postJson('/api/polls', $payload)->assertCreated();
        $this->postJson("/api/polls/{$poll->uuid}/activate")->assertOk();
    });

    it('limits basket creation and rebalancing to operators', function (): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->postJson('/api/v2/baskets', ['code' => 'ACHB'])->assertForbidden();
        $this->postJson('/api/v2/baskets/GCU/rebalance')->assertForbidden();
    });
});

describe('GraphQL mutations on platform records', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    it('limits basket creation and rebalancing to operators', function (): void {
        $basket = App\Domain\Basket\Models\BasketAsset::create([
            'code'                => 'ACH-BSK',
            'name'                => 'Access basket',
            'type'                => 'dynamic',
            'rebalance_frequency' => 'daily',
            'is_active'           => true,
        ]);
        $plain = User::factory()->create();

        $json = achGraphql($this, $plain, 'mutation { createBasket(input: { name: "Mine" }) { id } }');
        expect(achGraphqlError($json))->toBe('You are not authorized to manage baskets.')
            ->and(App\Domain\Basket\Models\BasketAsset::query()->where('name', 'Mine')->exists())->toBeFalse();

        $json = achGraphql($this, $plain, 'mutation ($id: ID!) { rebalanceBasket(input: { id: $id }) { id } }', ['id' => $basket->id]);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage baskets.');

        $json = achGraphql($this, achUserWithRole('admin'), 'mutation { createBasket(input: { name: "Operator basket" }) { id name } }');
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage baskets.');

        $json = achGraphql($this, achUserWithRole('bank_admin'), 'mutation ($id: ID!) { rebalanceBasket(input: { id: $id }) { id } }', ['id' => $basket->id]);
        expect(achGraphqlError($json))->not->toBe('You are not authorized to manage baskets.');
    });

    it('limits product creation to operators', function (): void {
        $query = 'mutation { createProduct(input: { name: "Access checking", category: "checking", type: "deposit" }) { id name } }';

        $json = achGraphql($this, User::factory()->create(), $query);
        expect(achGraphqlError($json))->toBe('You are not authorized to create products.')
            ->and(Product::query()->where('name', 'Access checking')->exists())->toBeFalse();

        $json = achGraphql($this, achUserWithRole('bank_admin'), $query);
        expect($json['data']['createProduct']['name'] ?? null)->toBe('Access checking');
    });

    it('limits microfinance group set-up to operators and member management to the leader', function (): void {
        $group = app(App\Domain\Microfinance\Services\GroupLendingService::class)->createGroup('Access group', 'weekly');
        $plain = User::factory()->create();
        $leader = User::factory()->create();

        $json = achGraphql($this, $plain, 'mutation { mfiCreateGroup(input: { name: "Mine", meeting_frequency: "weekly" }) { id } }');
        expect(achGraphqlError($json))->toBe('You are not authorized to create groups.');

        $json = achGraphql($this, $plain, 'mutation ($id: ID!) { mfiActivateGroup(id: $id) { id } }', ['id' => $group->id]);
        expect(achGraphqlError($json))->toBe('You are not authorized to activate groups.')
            ->and($group->fresh()?->status?->value)->toBe('pending');

        $addMember = 'mutation ($input: MfiAddMemberInput!) { mfiAddMember(input: $input) { id } }';
        $json = achGraphql($this, $plain, $addMember, ['input' => ['group_id' => $group->id, 'user_id' => $plain->id, 'role' => 'leader']]);
        expect(achGraphqlError($json))->toBe('You are not authorized to manage this group.');

        $operator = achUserWithRole('bank_admin');
        $json = achGraphql($this, $operator, $addMember, ['input' => ['group_id' => $group->id, 'user_id' => $leader->id, 'role' => 'leader']]);
        expect($json)->not->toHaveKey('errors');

        $json = achGraphql($this, $leader, $addMember, ['input' => ['group_id' => $group->id, 'user_id' => $plain->id, 'role' => 'treasurer']]);
        expect(achGraphqlError($json))->toBe('You are not authorized to assign this role.');

        $json = achGraphql($this, $leader, $addMember, ['input' => ['group_id' => $group->id, 'user_id' => $plain->id]]);
        expect($json)->not->toHaveKey('errors')
            ->and(App\Domain\Microfinance\Models\GroupMember::query()->where('group_id', $group->id)->where('user_id', $plain->id)->first()?->role->value)->toBe('member');

        $json = achGraphql($this, $operator, 'mutation ($id: ID!) { mfiActivateGroup(id: $id) { id } }', ['id' => $group->id]);
        expect($json)->not->toHaveKey('errors')
            ->and($group->fresh()?->status->value)->toBe('active');
    });
});

/**
 * @param  array<model-property<Poll>, mixed>  $overrides
 */
function achPoll(User $creator, array $overrides = []): Poll
{
    $model = new Poll();
    $model->fill(array_merge([
        'uuid'        => Str::uuid()->toString(),
        'title'       => 'Access poll',
        'description' => 'x',
        'type'        => 'single_choice',
        'status'      => 'draft',
        'options'     => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
        'start_date'             => now()->subDay(),
        'end_date'               => now()->addDays(7),
        'created_by'             => $creator->uuid,
        'required_participation' => 0,
        'voting_power_strategy'  => 'one_user_one_vote',
    ], $overrides));
    $model->save();

    return $model;
}
