<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Account\Workflows\DepositAccountActivity;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Asset\Workflows\Activities\DepositAssetActivity;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Compliance\Services\OndatoService;
use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Newsletter\Mail\SubscriberConfirmation;
use App\Domain\Newsletter\Mail\SubscriberWelcome;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use App\Domain\Payment\Services\DemoPaymentGatewayService;
use App\Domain\Payment\Services\DemoPayseraDepositService;
use App\Domain\Payment\Services\HyperSwitch\HyperSwitchPaymentService;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Domain\Payment\Services\PayseraDepositService;
use App\Domain\Payment\Workflows\TransferWorkflow;
use App\Domain\Shared\Models\Plugin;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Infrastructure\Plugins\PluginManager;
use App\Infrastructure\Plugins\PluginSecurityScanner;
use App\Jobs\ProcessOndatoWebhook;
use App\Models\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\Permission\Models\Role;
use Workflow\Exceptions\NonRetryableException;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

/*
| GHSA-385w cluster "deposits-config": payment-backed web deposits outside the
| demo-only direct-funding flag, Paysera server notifications and replay
| claims, owner versus staff account freezes, Ondato webhook passthrough and
| replays, newsletter confirmation by POST, SMS budget releases across
| midnight, GDPR exports cached before the deploy, the APP_ENV allow-list for
| secure defaults, and stronger denial tests for the ledger REST paths.
*/

beforeEach(function (): void {
    Cache::forget('domain_manager_disabled');
    config(['modules.disabled' => []]);
});

function ghsaDcStaff(string $role = 'admin'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Evaluate a config file with the given environment variables (null =
 * unset) and restore the process environment afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function ghsaDcConfigUnderEnv(string $file, array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null, array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path("config/{$file}.php");

        return $config;
    } finally {
        foreach ($saved as $key => [$hasServer, $server, $hasEnv, $envValue, $getenv]) {
            if ($hasServer) {
                $_SERVER[$key] = $server;
            } else {
                unset($_SERVER[$key]);
            }
            if ($hasEnv) {
                $_ENV[$key] = $envValue;
            } else {
                unset($_ENV[$key]);
            }
            if ($getenv === false) {
                putenv($key);
            } else {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function ghsaDcGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
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
 * Store a pending Paysera order as PayseraDepositService::initiateDeposit does.
 *
 * @return array{0: User, 1: Account, 2: string}
 */
function ghsaDcPayseraOrder(int $amount = 2500): array
{
    $user = User::factory()->withPersonalTeam()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $orderId = 'PAY-' . strtoupper(Str::random(20));

    Cache::put("paysera_order:{$orderId}", [
        'order_id'     => $orderId,
        'account_uuid' => $account->uuid,
        'amount'       => $amount,
        'currency'     => 'EUR',
        'user_id'      => $user->id,
        'status'       => 'pending',
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addHour());

    return [$user, $account, $orderId];
}

/**
 * Paysera's signed response for an order.
 *
 * @param  array<string, string|int>  $overrides
 * @return array{data: string, ss1: string}
 */
function ghsaDcPayseraResponse(string $orderId, array $overrides = [], string $password = 'test-sign-password'): array
{
    $data = PayseraDepositService::encodePayseraData(http_build_query(array_merge([
        'projectid' => '12345',
        'orderid'   => $orderId,
        'amount'    => 2500,
        'currency'  => 'EUR',
        'status'    => '1',
        'requestid' => 'REQ-' . $orderId,
        'payment'   => 'hanzabankas',
        'test'      => '0',
    ], $overrides)));

    return ['data' => $data, 'ss1' => md5($data . $password)];
}

/**
 * @return PaymentServiceInterface&MockInterface
 */
function ghsaDcPaymentService(int $credits): MockInterface
{
    /** @var PaymentServiceInterface&MockInterface $mock */
    $mock = Mockery::mock(PaymentServiceInterface::class);
    if ($credits === 0) {
        $mock->shouldNotReceive('processStripeDeposit');
    } else {
        $mock->shouldReceive('processStripeDeposit')->times($credits)->andReturn('txn_paysera');
    }
    app()->instance(PaymentServiceInterface::class, $mock);

    return $mock;
}

/**
 * Bind a Mockery mock of the class in the container.
 *
 * @param  class-string  $abstract
 * @param  Closure(MockInterface): void  $expectations
 */
function ghsaDcMock(string $abstract, Closure $expectations): MockInterface
{
    $mock = Mockery::mock($abstract);
    $expectations($mock);
    app()->instance($abstract, $mock);

    return $mock;
}

function ghsaDcStoredWorkflows(): int
{
    return StoredWorkflow::query()->count();
}

/**
 * Run a deposit activity the way the queue worker does for a stored workflow
 * of the given class, optionally started as a child of another workflow.
 *
 * @param  class-string<Workflow\Activity>  $activityClass
 * @param  class-string<Workflow\Workflow>  $workflowClass
 * @param  list<mixed>  $arguments
 * @param  class-string<Workflow\Workflow>|null  $parentClass
 */
function ghsaDcRunDepositActivity(string $activityClass, string $workflowClass, array $arguments, ?string $parentClass = null): mixed
{
    $now = now()->format('Y-m-d\TH:i:s.u\Z');
    $stored = StoredWorkflow::query()->whereKey(WorkflowStub::make($workflowClass)->id())->firstOrFail();

    if ($parentClass !== null) {
        $parent = StoredWorkflow::query()->whereKey(WorkflowStub::make($parentClass)->id())->firstOrFail();
        // parent_now is a DATETIME column; MySQL refuses the ISO-8601 form the activity takes.
        $stored->parents()->attach($parent, ['parent_index' => 0, 'parent_now' => now()->format('Y-m-d H:i:s')]);
    }

    $activity = new $activityClass(0, $now, $stored, ...$arguments);

    return $activity->handle();
}

/**
 * Stored ledger events: legacy account transactions and the shared event store.
 *
 * @return array{0: int, 1: int}
 */
function ghsaDcLedgerEvents(): array
{
    return [Transaction::query()->count(), EloquentStoredEvent::query()->count()];
}

function ghsaDcConfirmationUrl(string $email): string
{
    $url = null;
    Mail::assertQueued(SubscriberConfirmation::class, function (SubscriberConfirmation $mail) use ($email, &$url): bool {
        if (! $mail->hasTo($email)) {
            return false;
        }
        $url = $mail->confirmationUrl;

        return true;
    });

    return (string) $url;
}

// ── C08: payment-backed web deposits do not depend on direct funding ─────

describe('payment-backed web deposits (C08)', function (): void {
    beforeEach(function (): void {
        config([
            'ledger.direct_funding_enabled'    => false,
            'ledger.web_card_deposits_enabled' => true,
        ]);
    });

    it('starts a HyperSwitch card deposit while direct funding is off', function (): void {
        config(['hyperswitch.enabled' => true]);
        $user = User::factory()->withPersonalTeam()->create();
        Account::factory()->create(['user_uuid' => $user->uuid]);

        ghsaDcMock(HyperSwitchPaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('startDeposit')->once()->andReturn(['client_secret' => 'cs_test', 'payment_id' => 'pay_test']);
        });

        $this->actingAs($user)
            ->postJson('/wallet/deposit/card', ['amount' => 50, 'currency' => 'USD'])
            ->assertOk()
            ->assertJsonPath('processor', 'hyperswitch')
            ->assertJsonPath('client_secret', 'cs_test')
            ->assertJsonPath('amount', 5000);

        $this->actingAs($user)
            ->get('/wallet/deposit/confirm?payment_intent_id=pay_test')
            ->assertRedirect(route('wallet.index'));
    });

    it('answers 404 for card deposits while their own switch is off', function (): void {
        config(['hyperswitch.enabled' => true, 'ledger.web_card_deposits_enabled' => false]);
        $user = User::factory()->withPersonalTeam()->create();
        Account::factory()->create(['user_uuid' => $user->uuid]);

        ghsaDcMock(HyperSwitchPaymentService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('startDeposit');
        });

        $this->actingAs($user)->postJson('/wallet/deposit/card', ['amount' => 50, 'currency' => 'USD'])->assertNotFound();
        $this->actingAs($user)->get('/wallet/deposit/confirm?payment_intent_id=pi_x')->assertNotFound();
    });

    it('keeps the simulated demo card gateway behind the direct-funding flag', function (): void {
        $user = User::factory()->withPersonalTeam()->create();
        Account::factory()->create(['user_uuid' => $user->uuid]);
        $this->app->bind(PaymentGatewayService::class, DemoPaymentGatewayService::class);

        $this->actingAs($user)->get('/wallet/deposit/card')->assertNotFound();
        $this->actingAs($user)->postJson('/wallet/deposit/card', ['amount' => 50, 'currency' => 'USD'])->assertNotFound();

        config(['ledger.direct_funding_enabled' => true]);
        $secret = (string) $this->actingAs($user)
            ->postJson('/wallet/deposit/card', ['amount' => 50, 'currency' => 'USD'])
            ->assertOk()
            ->json('client_secret');
        expect($secret)->toStartWith('demo_secret_');
    });

    it('starts a Paysera deposit while direct funding is off and PAYSERA_ENABLED is on', function (): void {
        config([
            'custodians.connectors.paysera.enabled' => true,
            'services.paysera.project_id'           => '12345',
            'services.paysera.sign_password'        => 'test-sign-password',
        ]);
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $this->actingAs($user)
            ->postJson('/wallet/deposit/paysera/initiate', ['account_uuid' => $account->uuid, 'amount' => 2500, 'currency' => 'EUR'])
            ->assertOk()
            ->assertJsonPath('success', true);

        config(['custodians.connectors.paysera.enabled' => false]);
        $this->actingAs($user)
            ->postJson('/wallet/deposit/paysera/initiate', ['account_uuid' => $account->uuid, 'amount' => 2500, 'currency' => 'EUR'])
            ->assertNotFound();
    });

    it('keeps the simulated demo Paysera service behind the direct-funding flag', function (): void {
        $this->app->bind(PayseraDepositServiceInterface::class, DemoPayseraDepositService::class);
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        $payload = ['account_uuid' => $account->uuid, 'amount' => 1500, 'currency' => 'EUR'];

        $this->actingAs($user)->postJson('/wallet/deposit/paysera/initiate', $payload)->assertNotFound();

        config(['ledger.direct_funding_enabled' => true]);
        $this->actingAs($user)->postJson('/wallet/deposit/paysera/initiate', $payload)->assertOk();
        DemoPayseraDepositService::resetDemoOrders();
    });

    it('turns card deposits on in production only with HyperSwitch or an explicit opt-in', function (): void {
        $resolve = fn (array $env): array => ghsaDcConfigUnderEnv('ledger', array_merge([
            'APP_ENV'                       => 'production',
            'LEDGER_DIRECT_FUNDING_ENABLED' => null,
            'WEB_CARD_DEPOSITS_ENABLED'     => null,
            'HYPERSWITCH_ENABLED'           => null,
        ], $env));

        expect($resolve([])['web_card_deposits_enabled'])->toBeFalse()
            ->and($resolve(['HYPERSWITCH_ENABLED' => 'true'])['web_card_deposits_enabled'])->toBeTrue()
            ->and($resolve(['HYPERSWITCH_ENABLED' => 'true'])['direct_funding_enabled'])->toBeFalse()
            ->and($resolve(['WEB_CARD_DEPOSITS_ENABLED' => 'true'])['web_card_deposits_enabled'])->toBeTrue()
            ->and($resolve(['HYPERSWITCH_ENABLED' => 'true', 'WEB_CARD_DEPOSITS_ENABLED' => 'false'])['web_card_deposits_enabled'])->toBeFalse()
            ->and($resolve(['APP_ENV' => 'demo'])['web_card_deposits_enabled'])->toBeTrue();
    });
});

// ── C38 / C39: Paysera replay claim and server notification ──────────────

describe('Paysera deposits (C38, C39)', function (): void {
    beforeEach(function (): void {
        config([
            'custodians.connectors.paysera.enabled' => true,
            'services.paysera.project_id'           => '12345',
            'services.paysera.sign_password'        => 'test-sign-password',
        ]);
    });

    it('keeps the replay claim when the status cache write fails after the credit was started', function (): void {
        [$user, , $orderId] = ghsaDcPayseraOrder();
        ghsaDcPaymentService(1);

        // A cache that fails only when the order is marked completed.
        Cache::extend('ghsa_dc_flaky', fn () => Cache::repository(new class () extends ArrayStore {
            public function put($key, $value, $seconds)
            {
                if (str_starts_with((string) $key, 'paysera_order:') && is_array($value) && ($value['status'] ?? null) === 'completed') {
                    throw new RuntimeException('cache unavailable');
                }

                return parent::put($key, $value, $seconds);
            }
        }));
        config(['cache.stores.ghsa_dc_flaky' => ['driver' => 'ghsa_dc_flaky']]);
        $order = Cache::get("paysera_order:{$orderId}");
        Cache::setDefaultDriver('ghsa_dc_flaky');
        Cache::put("paysera_order:{$orderId}", $order, now()->addHour());

        $signed = ghsaDcPayseraResponse($orderId);
        $callback = [
            'order_id'     => $orderId,
            'status'       => 'completed',
            'user_id'      => $user->id,
            'paysera_data' => $signed['data'],
            'paysera_ss1'  => $signed['ss1'],
        ];
        $service = app(PayseraDepositService::class);

        expect($service->handleCallback($callback)['success'])->toBeTrue();

        // The reload of the same signed return URL credits nothing more.
        $second = $service->handleCallback($callback);
        expect($second['success'])->toBeFalse()
            ->and($second['message'])->toBe('Deposit already processed')
            ->and(ProcessedWebhookEvent::query()->where('provider', 'paysera_deposit')->where('event_id', $orderId)->exists())->toBeTrue();
    });

    it('gives the claim back when the credit itself could not be started', function (): void {
        [$user, , $orderId] = ghsaDcPayseraOrder();
        /** @var PaymentServiceInterface&MockInterface $mock */
        $mock = Mockery::mock(PaymentServiceInterface::class);
        $mock->shouldReceive('processStripeDeposit')->once()->andThrow(new RuntimeException('queue down'));
        app()->instance(PaymentServiceInterface::class, $mock);
        $signed = ghsaDcPayseraResponse($orderId);

        $result = app(PayseraDepositService::class)->handleCallback([
            'order_id'     => $orderId,
            'status'       => 'completed',
            'user_id'      => $user->id,
            'paysera_data' => $signed['data'],
            'paysera_ss1'  => $signed['ss1'],
        ]);

        expect($result['success'])->toBeFalse()
            ->and(ProcessedWebhookEvent::query()->where('provider', 'paysera_deposit')->where('event_id', $orderId)->exists())->toBeFalse();
    });

    it('credits the stored order once from the server notification without a session', function (): void {
        [, $account, $orderId] = ghsaDcPayseraOrder();
        /** @var PaymentServiceInterface&MockInterface $mock */
        $mock = Mockery::mock(PaymentServiceInterface::class);
        $mock->shouldReceive('processStripeDeposit')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['account_uuid'] === $account->uuid && $data['amount'] === 2500 && $data['currency'] === 'EUR'))
            ->andReturn('txn_paysera');
        app()->instance(PaymentServiceInterface::class, $mock);

        $url = '/api/webhooks/paysera/deposit?' . http_build_query(ghsaDcPayseraResponse($orderId));

        $this->get($url)->assertOk()->assertSeeText('OK');
        // Paysera retries until it sees OK; a retry credits nothing more.
        $this->get($url)->assertOk()->assertSeeText('OK');

        expect(Cache::get("paysera_order:{$orderId}")['status'] ?? null)->toBe('completed');
    });

    it('acknowledges a not-yet-executed payment without crediting it, then credits the paid notification', function (): void {
        [, , $orderId] = ghsaDcPayseraOrder();
        ghsaDcPaymentService(1);

        $this->get('/api/webhooks/paysera/deposit?' . http_build_query(ghsaDcPayseraResponse($orderId, ['status' => '2'])))
            ->assertOk()->assertSeeText('OK');
        expect(Cache::get("paysera_order:{$orderId}")['status'] ?? null)->toBe('pending');

        $this->get('/api/webhooks/paysera/deposit?' . http_build_query(ghsaDcPayseraResponse($orderId)))
            ->assertOk()->assertSeeText('OK');
    });

    it('refuses a server notification without a valid Paysera signature', function (array $params): void {
        [, , $orderId] = ghsaDcPayseraOrder();
        ghsaDcPaymentService(0);

        if (($params['data'] ?? null) === '{tampered}') {
            $params = ['data' => ghsaDcPayseraResponse($orderId, ['amount' => 999999])['data'], 'ss1' => ghsaDcPayseraResponse($orderId)['ss1']];
        }
        if (($params['data'] ?? null) === '{wrong-password}') {
            $params = ghsaDcPayseraResponse($orderId, [], 'not-the-password');
        }

        $this->get('/api/webhooks/paysera/deposit?' . http_build_query($params))->assertStatus(400);

        expect(Cache::get("paysera_order:{$orderId}")['status'] ?? null)->toBe('pending');
    })->with([
        'no signed data'      => [[]],
        'ss1 of another body' => [['data' => '{tampered}']],
        'wrong sign password' => [['data' => '{wrong-password}']],
    ]);

    it('refuses a server notification when the order user no longer owns the account', function (): void {
        [, $account, $orderId] = ghsaDcPayseraOrder();
        ghsaDcPaymentService(0);
        $account->forceFill(['user_uuid' => User::factory()->create()->uuid])->save();

        $this->get('/api/webhooks/paysera/deposit?' . http_build_query(ghsaDcPayseraResponse($orderId)))->assertStatus(400);
    });

    it('answers 404 to the server notification while Paysera is off', function (): void {
        [, , $orderId] = ghsaDcPayseraOrder();
        ghsaDcPaymentService(0);
        config(['custodians.connectors.paysera.enabled' => false]);

        $this->get('/api/webhooks/paysera/deposit?' . http_build_query(ghsaDcPayseraResponse($orderId)))->assertNotFound();

        expect(Cache::get("paysera_order:{$orderId}")['status'] ?? null)->toBe('pending');
    });

    it('answers 404 to the server notification for the simulated demo service', function (): void {
        [, , $orderId] = ghsaDcPayseraOrder();
        ghsaDcPaymentService(0);
        config(['ledger.direct_funding_enabled' => true]);
        $this->app->bind(PayseraDepositServiceInterface::class, DemoPayseraDepositService::class);

        $this->get('/api/webhooks/paysera/deposit?' . http_build_query(ghsaDcPayseraResponse($orderId)))->assertNotFound();

        expect(Cache::get("paysera_order:{$orderId}")['status'] ?? null)->toBe('pending');
    });
});

// ── C20: owners may lift only a freeze they placed ───────────────────────

describe('account freezes placed by staff (C20)', function (): void {
    beforeEach(function (): void {
        config(['ledger.direct_funding_enabled' => true]);
    });

    it('refuses the owner lifting a staff freeze over REST v1 and v2', function (string $prefix): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        $admin = ghsaDcStaff();

        Sanctum::actingAs($admin, ['read', 'write', 'delete']);
        $this->postJson("{$prefix}/{$account->uuid}/freeze", ['reason' => 'suspected fraud'])->assertOk();
        expect($account->fresh()?->frozen)->toBeTrue()
            ->and($account->fresh()?->frozen_by_owner)->toBeFalse();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $workflows = ghsaDcStoredWorkflows();
        $this->postJson("{$prefix}/{$account->uuid}/unfreeze", ['reason' => 'ok'])
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
        expect($account->fresh()?->frozen)->toBeTrue()
            ->and(ghsaDcStoredWorkflows())->toBe($workflows);

        // Staff can still lift it (the unfreeze workflow starts).
        Sanctum::actingAs($admin, ['read', 'write', 'delete']);
        $this->postJson("{$prefix}/{$account->uuid}/unfreeze", ['reason' => 'cleared'])->assertOk();
        expect(ghsaDcStoredWorkflows())->toBeGreaterThan($workflows);
    })->with(['v1' => ['/api/accounts'], 'v2' => ['/api/v2/accounts']]);

    it('lets the owner lift a freeze they placed themselves', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->postJson("/api/accounts/{$account->uuid}/freeze", ['reason' => 'lost phone'])->assertOk();
        expect($account->fresh()?->frozen_by_owner)->toBeTrue();

        $workflows = ghsaDcStoredWorkflows();
        $this->postJson("/api/accounts/{$account->uuid}/unfreeze", ['reason' => 'found it'])->assertOk();
        expect(ghsaDcStoredWorkflows())->toBeGreaterThan($workflows);
    });

    it('lets staff take over a freeze the owner placed', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson("/api/accounts/{$account->uuid}/freeze", ['reason' => 'pre-emptive'])->assertOk();

        Sanctum::actingAs(ghsaDcStaff('bank_admin'), ['read', 'write', 'delete']);
        $this->postJson("/api/accounts/{$account->uuid}/freeze", ['reason' => 'compliance hold'])->assertOk();
        expect($account->fresh()?->frozen_by_owner)->toBeFalse();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson("/api/accounts/{$account->uuid}/unfreeze", ['reason' => 'ok'])->assertForbidden();
        expect($account->fresh()?->frozen)->toBeTrue();
    });

    it('treats a freeze set outside the API (admin panel, compliance monitoring, legacy) as a staff freeze', function (): void {
        $owner = User::factory()->create();
        $legacy = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => true]);
        $panel = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        $panel->update(['frozen' => true]);
        $monitored = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        $monitored->recordFreeze(byStaff: false);
        $monitored->recordFreeze(byStaff: true);

        foreach ([$legacy, $panel, $monitored] as $account) {
            $json = ghsaDcGraphql($this, $owner, 'mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }', ['id' => (string) $account->id]);

            expect($json['errors'][0]['message'] ?? null)->toBe('This account was frozen by staff and can only be unfrozen by staff.')
                ->and($account->fresh()?->frozen)->toBeTrue();
        }

        $json = ghsaDcGraphql($this, ghsaDcStaff('super_admin'), 'mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }', ['id' => (string) $legacy->id]);
        expect($json)->not->toHaveKey('errors')
            ->and($legacy->fresh()?->frozen)->toBeFalse();
    });

    it('does not let an owner GraphQL freeze take over a staff freeze', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);

        ghsaDcGraphql($this, ghsaDcStaff(), 'mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }', ['input' => ['id' => (string) $account->id, 'reason' => 'fraud']]);
        ghsaDcGraphql($this, $owner, 'mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }', ['input' => ['id' => (string) $account->id, 'reason' => 'mine']]);
        expect($account->fresh()?->frozen_by_owner)->toBeFalse();

        $json = ghsaDcGraphql($this, $owner, 'mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }', ['id' => (string) $account->id]);
        expect($json['errors'][0]['message'] ?? null)->toBe('This account was frozen by staff and can only be unfrozen by staff.')
            ->and($account->fresh()?->frozen)->toBeTrue();
    });

    it('refuses the owner lifting a staff freeze through BIAN control', function (string $action): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => true]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $workflows = ghsaDcStoredWorkflows();

        $this->putJson("/api/bian/current-account/{$account->uuid}/control", ['controlAction' => $action, 'controlReason' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');

        expect($account->fresh()?->frozen)->toBeTrue()
            ->and(ghsaDcStoredWorkflows())->toBe($workflows);
    })->with(['unfreeze', 'reactivate']);

    it('lets the owner freeze and unfreeze through BIAN control', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->putJson("/api/bian/current-account/{$account->uuid}/control", ['controlAction' => 'freeze', 'controlReason' => 'travel'])
            ->assertOk();
        expect($account->fresh()?->frozen)->toBeTrue()
            ->and($account->fresh()?->frozen_by_owner)->toBeTrue();

        $this->putJson("/api/bian/current-account/{$account->uuid}/control", ['controlAction' => 'unfreeze', 'controlReason' => 'back'])
            ->assertOk()
            ->assertJsonPath('currentAccountFulfillmentControlRecord.controlStatus', 'active');
    });
});

// ── C29: the custodian-transfer ownership check on its own ───────────────

describe('custodian transfer ownership (C29)', function (): void {
    beforeEach(function (): void {
        config(['ledger.direct_funding_enabled' => true]);
    });

    it('refuses the victim account even when it is linked to the custodian account', function (): void {
        $victim = User::factory()->create();
        $account = Account::factory()->withBalance(5000)->create(['user_uuid' => $victim->uuid]);
        CustodianAccount::create([
            'account_uuid'         => $account->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
            'status'               => 'active',
            'is_primary'           => true,
        ]);
        ghsaDcMock(CustodianRegistry::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('get');
        });
        $workflows = ghsaDcStoredWorkflows();

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'withdraw',
        ])->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN')
            ->assertJsonPath('message', 'Access denied to this account');

        expect($account->fresh()?->getBalance('USD'))->toBe(5000)
            ->and(ghsaDcStoredWorkflows())->toBe($workflows);
    });

    it('lets the owner past the ownership and link checks to the connector', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->withBalance(5000)->create(['user_uuid' => $owner->uuid]);
        CustodianAccount::create([
            'account_uuid'         => $account->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
            'status'               => 'active',
            'is_primary'           => true,
        ]);
        $connector = Mockery::mock(ICustodianConnector::class);
        $connector->shouldReceive('validateAccount')->once()->with('mock-account-1')->andReturnFalse();
        ghsaDcMock(CustodianRegistry::class, function (MockInterface $mock) use ($connector): void {
            $mock->shouldReceive('get')->once()->with('mock')->andReturn($connector);
        });

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'withdraw',
        ])->assertStatus(400)->assertJsonPath('error', 'Invalid custodian account');
    });
});

// ── C56: non-owner denials leave balances, freezes and workflows alone ───

describe('ledger REST non-owner denials change nothing (C56)', function (): void {
    beforeEach(function (): void {
        config(['ledger.direct_funding_enabled' => true]);
        Feature::define('sub_product.exchange', fn (): bool => true);
    });

    it('refuses a non-owner and leaves the balance, the freeze state and the workflows unchanged', function (string $method, string $path, array $payload): void {
        $owner = User::factory()->create();
        $account = Account::factory()->withBalance(5000)->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $workflows = ghsaDcStoredWorkflows();

        $payload = array_map(fn (mixed $value): mixed => $value === '{uuid}' ? $account->uuid : $value, $payload);
        $this->json($method, str_replace('{uuid}', $account->uuid, $path), $payload)->assertForbidden();

        $fresh = $account->fresh();
        expect($fresh?->getBalance('USD'))->toBe(5000)
            ->and($fresh?->frozen)->toBeFalse()
            ->and(ghsaDcStoredWorkflows())->toBe($workflows);
    })->with([
        'BIAN freeze'         => ['PUT', '/api/bian/current-account/{uuid}/control', ['controlAction' => 'freeze', 'controlReason' => 'x']],
        'BIAN withdrawal'     => ['POST', '/api/bian/current-account/{uuid}/payment/execute', ['paymentAmount' => 100, 'paymentType' => 'withdrawal']],
        'BIAN deposit'        => ['POST', '/api/bian/current-account/{uuid}/deposit/execute', ['depositAmount' => 100, 'depositType' => 'cash']],
        'v1 deposit'          => ['POST', '/api/accounts/{uuid}/deposit', ['amount' => 10, 'asset_code' => 'USD']],
        'v1 withdraw'         => ['POST', '/api/accounts/{uuid}/withdraw', ['amount' => 10, 'asset_code' => 'USD']],
        'v2 deposit'          => ['POST', '/api/v2/accounts/{uuid}/deposit', ['amount' => 10, 'asset_code' => 'USD']],
        'v2 withdraw'         => ['POST', '/api/v2/accounts/{uuid}/withdraw', ['amount' => 10, 'asset_code' => 'USD']],
        'currency conversion' => ['POST', '/api/exchange/convert', ['account_uuid' => '{uuid}', 'from_currency' => 'USD', 'to_currency' => 'EUR', 'amount' => 10]],
    ]);

    it('still serves the owner on the same paths', function (string $method, string $path, array $payload): void {
        $owner = User::factory()->create();
        $account = Account::factory()->withBalance(5000)->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $workflows = ghsaDcStoredWorkflows();

        $this->json($method, str_replace('{uuid}', $account->uuid, $path), $payload)->assertSuccessful();

        expect(ghsaDcStoredWorkflows())->toBeGreaterThan($workflows);
    })->with([
        'BIAN freeze'  => ['PUT', '/api/bian/current-account/{uuid}/control', ['controlAction' => 'freeze', 'controlReason' => 'x']],
        'BIAN deposit' => ['POST', '/api/bian/current-account/{uuid}/deposit/execute', ['depositAmount' => 100, 'depositType' => 'cash']],
        'v1 deposit'   => ['POST', '/api/accounts/{uuid}/deposit', ['amount' => 10, 'asset_code' => 'USD']],
    ]);
});

// ── D12: the runtime-management switch on real plugin rows ───────────────

describe('runtime plugin management switch on a real plugin (D12)', function (): void {
    beforeEach(function (): void {
        $this->ghsaDcPlugin = Plugin::create([
            'vendor'  => 'acme',
            'name'    => 'ledger-tools',
            'version' => '1.0.0',
            'path'    => storage_path('framework/testing/ghsa-dc-plugin'),
            'status'  => 'inactive',
        ]);
    });

    it('answers 404 to an admin while off, before the controller touches the plugin', function (string $method, string $suffix): void {
        config(['modules.runtime_management_enabled' => false]);
        ghsaDcMock(PluginManager::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('enable', 'disable', 'remove');
        });
        ghsaDcMock(PluginSecurityScanner::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('scan');
        });
        Sanctum::actingAs(ghsaDcStaff(), ['read', 'write', 'delete']);

        $this->json($method, '/api/v2/plugins/' . $this->ghsaDcPlugin->id . $suffix)->assertNotFound();

        expect(Plugin::query()->whereKey($this->ghsaDcPlugin->id)->value('status'))->toBe('inactive');
    })->with([
        'enable'  => ['POST', '/enable'],
        'disable' => ['POST', '/disable'],
        'delete'  => ['DELETE', ''],
        'scan'    => ['POST', '/scan'],
    ]);

    it('reaches the controller for an admin while on', function (string $method, string $suffix): void {
        config(['modules.runtime_management_enabled' => true]);
        ghsaDcMock(PluginManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('enable', 'disable', 'remove')->andReturn(['success' => true, 'message' => 'done']);
        });
        ghsaDcMock(PluginSecurityScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scan')->andReturn(['safe' => true, 'issues' => []]);
            $mock->shouldReceive('summarize')->andReturn([]);
        });
        Sanctum::actingAs(ghsaDcStaff(), ['read', 'write', 'delete']);

        $this->json($method, '/api/v2/plugins/' . $this->ghsaDcPlugin->id . $suffix)->assertOk();
    })->with([
        'enable'  => ['POST', '/enable'],
        'disable' => ['POST', '/disable'],
        'delete'  => ['DELETE', ''],
        'scan'    => ['POST', '/scan'],
    ]);
});

// ── C42: Ondato passthrough and replays ──────────────────────────────────

describe('Ondato webhooks (C42)', function (): void {
    it('refuses unsigned webhooks in sandbox mode outside local and testing', function (string $env): void {
        Queue::fake();
        config(['services.ondato.sandbox' => true, 'services.ondato.webhook_secret' => '']);
        $this->app->instance(OndatoService::class, new OndatoService());

        $this->app->detectEnvironment(fn (): string => $env);
        $this->postJson('/api/webhooks/ondato/identity-verification', ['identityVerificationId' => 'idv-forged', 'status' => 'PROCESSED'])
            ->assertUnauthorized();

        Queue::assertNotPushed(ProcessOndatoWebhook::class);
    })->with(['demo', 'staging', 'production']);

    it('keeps the unsigned sandbox passthrough for local and testing', function (string $env): void {
        Queue::fake();
        config(['services.ondato.sandbox' => true, 'services.ondato.webhook_secret' => '']);

        $this->app->detectEnvironment(fn (): string => $env);
        $this->postJson('/api/webhooks/ondato/identity-verification', ['identityVerificationId' => 'idv-local', 'status' => 'STARTED'])
            ->assertOk();

        Queue::assertPushed(ProcessOndatoWebhook::class, 1);
    })->with(['local', 'testing']);

    it('processes a signed delivery once and drops a replay of the same body', function (): void {
        Queue::fake();
        config(['services.ondato.sandbox' => false, 'services.ondato.webhook_secret' => 'ondato-test-secret']);
        $this->app->detectEnvironment(fn (): string => 'production');

        $send = function (array $payload): Illuminate\Testing\TestResponse {
            $body = (string) json_encode($payload);

            return $this->call('POST', '/api/webhooks/ondato/identity-verification', [], [], [], [
                'CONTENT_TYPE'            => 'application/json',
                'HTTP_ACCEPT'             => 'application/json',
                'HTTP_X_ONDATO_SIGNATURE' => hash_hmac('sha256', $body, 'ondato-test-secret'),
            ], $body);
        };

        $processed = ['id' => 'idv-1', 'identityVerificationId' => 'idv-1', 'status' => 'PROCESSED'];
        $send($processed)->assertOk()->assertJsonPath('status', 'received');
        $send(['id' => 'idv-1', 'identityVerificationId' => 'idv-1', 'status' => 'REJECTED'])->assertOk()->assertJsonPath('status', 'received');

        // A captured PROCESSED delivery replayed after the rejection.
        $send($processed)->assertOk()->assertJsonPath('status', 'duplicate');

        Queue::assertPushed(ProcessOndatoWebhook::class, 2);
        expect(ProcessedWebhookEvent::query()->where('provider', 'ondato')->count())->toBe(2);
    });
});

// ── C44: opening the confirmation link does not confirm ──────────────────

describe('newsletter confirmation (C44)', function (): void {
    beforeEach(function (): void {
        Mail::fake();
        Http::fake();
        config([
            'services.mailchimp.api_key' => 'ghsakey0123456789-us7',
            'services.mailchimp.list_id' => 'ghsa-list',
        ]);
    });

    it('changes nothing when the link is opened (GET or HEAD, as mail scanners do)', function (): void {
        $this->postJson('/blog/subscribe', ['email' => 'scanned@example.com'])->assertOk();
        $url = ghsaDcConfirmationUrl('scanned@example.com');

        $this->get($url)->assertOk()->assertSee('Confirm subscription')->assertSee('method="POST"', false);
        $this->call('HEAD', $url)->assertOk();

        expect(Subscriber::where('email', 'scanned@example.com')->firstOrFail()->status)->toBe(Subscriber::STATUS_PENDING);
        Mail::assertNotQueued(SubscriberWelcome::class);
        Http::assertNothingSent();
    });

    it('confirms on the POST from the confirm button', function (): void {
        $this->postJson('/blog/subscribe', ['email' => 'clicked@example.com'])->assertOk();
        $url = ghsaDcConfirmationUrl('clicked@example.com');

        $this->post($url)->assertOk()->assertSee('Subscription confirmed');

        expect(Subscriber::where('email', 'clicked@example.com')->firstOrFail()->status)->toBe(Subscriber::STATUS_ACTIVE);
        Mail::assertQueued(SubscriberWelcome::class, 1);
        Http::assertSentCount(1);
    });

    it('refuses a POST without a valid signature', function (): void {
        $this->postJson('/blog/subscribe', ['email' => 'forged@example.com'])->assertOk();
        $subscriber = Subscriber::where('email', 'forged@example.com')->firstOrFail();

        $forged = URL::temporarySignedRoute('subscriber.confirm', now()->addHour(), ['subscriber' => $subscriber->id, 'token' => str_repeat('x', 64)]);
        $this->post($forged)->assertOk()->assertSee('Link expired or invalid');
        $this->post('/subscriber/confirm/' . $subscriber->id . '?token=abc')->assertOk()->assertSee('Link expired or invalid');

        expect($subscriber->fresh()?->status)->toBe(Subscriber::STATUS_PENDING);
        Http::assertNothingSent();
    });
});

// ── C46: a release after midnight returns the unit to its own day ────────

describe('SMS budget release across midnight (C46)', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array', 'sms.rate_limit.per_user_per_day' => 5, 'sms.global_daily_limit' => 10]);
        Cache::flush();
    });

    it('decrements the day it reserved from, never the next day', function (): void {
        $budget = new SmsSendBudget();
        $this->travelTo(Carbon::parse('2026-11-19 23:59:50', 'UTC'));
        $day = SmsSendBudget::today();

        expect($budget->reserve(42, 'rest', $day))->toBeNull()
            ->and((int) Cache::get(SmsSendBudget::userCountCacheKey(42, $day)))->toBe(1);

        $this->travelTo(Carbon::parse('2026-11-20 00:00:20', 'UTC'));
        $budget->release(42, $day);

        expect((int) Cache::get(SmsSendBudget::userCountCacheKey(42, $day)))->toBe(0)
            ->and((int) Cache::get(SmsSendBudget::globalCountCacheKey($day)))->toBe(0)
            ->and(Cache::get(SmsSendBudget::userCountCacheKey(42)))->toBeNull()
            ->and(Cache::get(SmsSendBudget::globalCountCacheKey()))->toBeNull();

        // The new day starts with its full allowance and no extra unit.
        foreach (range(1, 5) as $ignored) {
            expect($budget->reserve(42, 'rest'))->toBeNull();
        }
        expect($budget->reserve(42, 'rest'))->toBe(SmsSendBudget::DENIED_USER_LIMIT);
    });

    it('gives a unit back on the same day', function (): void {
        $budget = new SmsSendBudget();

        expect($budget->reserve(7, 'mcp'))->toBeNull();
        $budget->release(7);

        expect((int) Cache::get(SmsSendBudget::userCountCacheKey(7)))->toBe(0)
            ->and((int) Cache::get(SmsSendBudget::globalCountCacheKey()))->toBe(0);
    });
});

// ── C57: GDPR exports cached before the deploy ───────────────────────────

describe('GDPR exports without a recorded owner (C57)', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
    });

    it('serves a completed legacy export to the user named in the export file only', function (): void {
        $owner = User::factory()->create();
        Storage::disk('local')->put('gdpr-exports/legacy-done.json.enc', encrypt((string) json_encode(['user' => ['uuid' => $owner->uuid, 'email' => $owner->email]])));
        Cache::put('gdpr_export:legacy-done', ['status' => 'completed', 'file_path' => 'gdpr-exports/legacy-done.json.enc'], now()->addHour());

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->getJson('/api/v1/user/data-export/legacy-done')->assertNotFound();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $download = (string) $this->getJson('/api/v1/user/data-export/legacy-done')
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonMissingPath('file_path')
            ->json('download_url');

        $this->get($download)->assertOk()->assertSee($owner->uuid);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->get($download)->assertNotFound();
    });

    it('keeps a legacy entry that is not completed staff-only', function (): void {
        Cache::put('gdpr_export:legacy-pending', ['status' => 'processing'], now()->addHour());

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->getJson('/api/v1/user/data-export/legacy-pending')->assertNotFound();

        Sanctum::actingAs(ghsaDcStaff('compliance_officer'), ['read', 'write', 'delete']);
        $this->getJson('/api/v1/user/data-export/legacy-pending')->assertOk();
    });
});

// ── D11: secure defaults use an APP_ENV allow-list ───────────────────────

describe('secure defaults outside local, testing and demo (D11)', function (): void {
    $switches = [
        'ledger direct funding'   => ['ledger', 'direct_funding_enabled', 'LEDGER_DIRECT_FUNDING_ENABLED'],
        'web card deposits'       => ['ledger', 'web_card_deposits_enabled', 'WEB_CARD_DEPOSITS_ENABLED'],
        'SMS'                     => ['sms', 'enabled', 'SMS_ENABLED'],
        'card waitlist deposit'   => ['cards', 'waitlist_deposit_enabled', 'CARDS_WAITLIST_DEPOSIT_ENABLED'],
        'investor page'           => ['investor', 'page_enabled', 'INVESTOR_PAGE_ENABLED'],
        'API registration'        => ['registration', 'api_enabled', 'REGISTRATION_API_ENABLED'],
        'runtime management'      => ['modules', 'runtime_management_enabled', 'ADMIN_RUNTIME_MANAGEMENT_ENABLED'],
        'generic gas sponsorship' => ['relayer', 'generic_sponsor_enabled', 'RELAYER_GENERIC_SPONSOR_ENABLED'],
        'trading'                 => ['trading', 'enabled', 'TRADING_ENABLED'],
    ];

    it('turns each switch off for staging, a typo or an unset APP_ENV', function (string $file, string $key, string $envName): void {
        foreach (['staging', 'prod', 'Production', null] as $appEnv) {
            $config = ghsaDcConfigUnderEnv($file, ['APP_ENV' => $appEnv, $envName => null, 'HYPERSWITCH_ENABLED' => null]);

            expect((bool) $config[$key])->toBeFalse();
        }
    })->with($switches);

    it('keeps each switch on for local, testing and demo', function (string $file, string $key, string $envName): void {
        foreach (['local', 'testing', 'demo'] as $appEnv) {
            $config = ghsaDcConfigUnderEnv($file, ['APP_ENV' => $appEnv, $envName => null]);

            expect((bool) $config[$key])->toBeTrue();
        }
    })->with($switches);

    it('applies the same rule to the MCP money tools', function (): void {
        foreach (['account.create' => 'MCP_TOOL_ACCOUNT_CREATE', 'payment.transfer' => 'MCP_TOOL_PAYMENT_TRANSFER', 'exchange.trade' => 'MCP_TOOL_EXCHANGE_TRADE', 'sms.send' => 'MCP_TOOL_SMS_SEND'] as $tool => $envName) {
            /** @var array<string, array<string, mixed>> $staging */
            $staging = ghsaDcConfigUnderEnv('mcp', ['APP_ENV' => 'staging', $envName => null])['tools'];
            /** @var array<string, array<string, mixed>> $demo */
            $demo = ghsaDcConfigUnderEnv('mcp', ['APP_ENV' => 'demo', $envName => null])['tools'];

            expect((bool) $staging[$tool]['enabled'])->toBeFalse()
                ->and((bool) $demo[$tool]['enabled'])->toBeTrue();
        }
    });
});

// ── Review follow-ups: deposit activities, HyperSwitch, freezes, agent mocks

describe('queued direct-funding deposits after the switch is off (review D3)', function (): void {
    it('refuses to credit a queued deposit workflow once direct funding is off', function (): void {
        $account = Account::factory()->withBalance(5000)->create(['frozen' => false]);
        config(['ledger.direct_funding_enabled' => false]);
        $events = ghsaDcLedgerEvents();

        expect(fn () => ghsaDcRunDepositActivity(DepositAccountActivity::class, DepositAccountWorkflow::class, [new AccountUuid($account->uuid), new Money(1000)]))
            ->toThrow(NonRetryableException::class);

        expect(ghsaDcLedgerEvents())->toBe($events)
            ->and($account->fresh()?->getBalance('USD'))->toBe(5000);
    });

    it('refuses to credit a queued asset deposit workflow once direct funding is off', function (): void {
        $account = Account::factory()->create(['frozen' => false]);
        config(['ledger.direct_funding_enabled' => false]);
        $events = ghsaDcLedgerEvents();

        expect(fn () => ghsaDcRunDepositActivity(DepositAssetActivity::class, AssetDepositWorkflow::class, [new AccountUuid($account->uuid), 'EUR', new Money(1000), null]))
            ->toThrow(NonRetryableException::class);

        expect(ghsaDcLedgerEvents())->toBe($events)
            ->and($account->fresh()?->getBalance('EUR'))->toBe(0);
    });

    it('credits both deposit workflows while direct funding is on', function (): void {
        $account = Account::factory()->create(['frozen' => false]);
        config(['ledger.direct_funding_enabled' => true]);

        $credited = ghsaDcRunDepositActivity(DepositAccountActivity::class, DepositAccountWorkflow::class, [new AccountUuid($account->uuid), new Money(1000)]);
        $transactionId = ghsaDcRunDepositActivity(DepositAssetActivity::class, AssetDepositWorkflow::class, [new AccountUuid($account->uuid), 'EUR', new Money(700), null]);

        expect($credited)->toBeTrue()
            ->and(Transaction::query()->where('aggregate_uuid', $account->uuid)->count())->toBe(1)
            ->and(EloquentStoredEvent::query()->where('aggregate_uuid', $transactionId)->count())->toBe(1);
    });

    it('still credits the receiving side of an internal transfer while direct funding is off', function (): void {
        $account = Account::factory()->create(['frozen' => false]);
        config(['ledger.direct_funding_enabled' => false]);

        $credited = ghsaDcRunDepositActivity(DepositAccountActivity::class, DepositAccountWorkflow::class, [new AccountUuid($account->uuid), new Money(1000)], TransferWorkflow::class);

        expect($credited)->toBeTrue()
            ->and(Transaction::query()->where('aggregate_uuid', $account->uuid)->count())->toBe(1);
    });
});

describe('HyperSwitch webhooks without a webhook secret (review D4)', function (): void {
    beforeEach(function (): void {
        config(['hyperswitch.webhook_secret' => '']);
    });

    $payload = ['event_type' => 'payment_succeeded', 'event_id' => 'evt_unsigned', 'content' => ['object' => ['payment_id' => 'pay_unsigned', 'amount' => 10000, 'currency' => 'USD']]];

    it('refuses an unsigned webhook outside local and testing', function (string $env) use ($payload): void {
        $this->app->detectEnvironment(fn (): string => $env);

        $this->postJson('/api/webhooks/hyperswitch', $payload)->assertUnauthorized();

        expect(ProcessedWebhookEvent::query()->where('event_id', 'evt_unsigned')->exists())->toBeFalse();
    })->with(['demo', 'staging', 'production']);

    it('keeps the unsigned passthrough for local and testing', function (string $env) use ($payload): void {
        $this->app->detectEnvironment(fn (): string => $env);

        $this->postJson('/api/webhooks/hyperswitch', $payload)->assertOk()->assertJsonPath('received', true);
    })->with(['local', 'testing']);
});

describe('owner freeze racing a staff freeze (review D5)', function (): void {
    it('keeps the staff marker when a compliance freeze lands while the owner freeze is in flight', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);
        // The owner request loaded the account before the compliance freeze.
        $ownerView = Account::query()->whereKey($account->getKey())->firstOrFail();

        $account->recordFreeze(byStaff: true);
        $ownerView->recordFreeze(byStaff: false);

        $fresh = $account->fresh();
        expect($fresh?->frozen)->toBeTrue()
            ->and($fresh?->frozen_by_owner)->toBeFalse()
            ->and($fresh?->mayBeUnfrozenBy($owner))->toBeFalse()
            ->and($ownerView->frozen_by_owner)->toBeFalse();
    });

    it('still records an owner freeze on an unfrozen account', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid, 'frozen' => false]);

        $account->recordFreeze(byStaff: false);

        $fresh = $account->fresh();
        expect($fresh?->frozen)->toBeTrue()
            ->and($fresh?->frozen_by_owner)->toBeTrue()
            ->and($account->frozen_by_owner)->toBeTrue()
            ->and($fresh?->mayBeUnfrozenBy($owner))->toBeTrue();
    });
});

describe('agent protocol mock defaults (review D6)', function (): void {
    it('turns mock signatures and webhooks off for production, staging, demo and an unset APP_ENV', function (): void {
        foreach (['production', 'staging', 'demo', null] as $appEnv) {
            /** @var array<string, mixed> $demo */
            $demo = ghsaDcConfigUnderEnv('agent_protocol', ['APP_ENV' => $appEnv, 'AGENT_MOCK_SIGNATURES' => null, 'AGENT_MOCK_WEBHOOKS' => null])['demo'];

            expect((bool) $demo['mock_signatures'])->toBeFalse()
                ->and((bool) $demo['mock_webhooks'])->toBeFalse();
        }
    });

    it('keeps them on for local and testing, and an explicit value wins', function (): void {
        foreach (['local', 'testing'] as $appEnv) {
            /** @var array<string, mixed> $demo */
            $demo = ghsaDcConfigUnderEnv('agent_protocol', ['APP_ENV' => $appEnv, 'AGENT_MOCK_SIGNATURES' => null, 'AGENT_MOCK_WEBHOOKS' => null])['demo'];

            expect((bool) $demo['mock_signatures'])->toBeTrue()
                ->and((bool) $demo['mock_webhooks'])->toBeTrue();
        }

        /** @var array<string, mixed> $explicit */
        $explicit = ghsaDcConfigUnderEnv('agent_protocol', ['APP_ENV' => null, 'AGENT_MOCK_SIGNATURES' => 'true', 'AGENT_MOCK_WEBHOOKS' => 'true'])['demo'];
        expect((bool) $explicit['mock_signatures'])->toBeTrue()
            ->and((bool) $explicit['mock_webhooks'])->toBeTrue();
    });
});
