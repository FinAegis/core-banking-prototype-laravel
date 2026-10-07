<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Lending\Models\LoanApplication;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Exceptions\DepositAlreadyProcessedException;
use App\Domain\Payment\Exceptions\DepositNotAllowedException;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Services\DepositReplayGuard;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Domain\Payment\Services\PayseraDepositService;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Http\Controllers\Api\TransactionReversalController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
| Authorization and integrity rules on ledger-moving endpoints. These hold
| regardless of the feature flags (the flags are left at their testing
| defaults, i.e. enabled, so the routes and resolvers are reachable).
*/

beforeEach(function (): void {
    Cache::forget('domain_manager_disabled');
    config([
        'modules.disabled'              => [],
        'ledger.direct_funding_enabled' => true,
        'trading.enabled'               => true,
    ]);
});

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function ledgerAuthGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
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

function ledgerAuthStripeGateway(PaymentServiceInterface $paymentService, PaymentIntent $intent): PaymentGatewayService
{
    return new class ($paymentService, $intent) extends PaymentGatewayService {
        public function __construct(PaymentServiceInterface $paymentService, private readonly PaymentIntent $intent)
        {
            parent::__construct($paymentService);
        }

        protected function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
        {
            return $this->intent;
        }
    };
}

/**
 * @param  array<string, string>  $metadata
 */
function ledgerAuthPaymentIntent(array $metadata, string $id = 'pi_test_deposit_1', string $status = 'succeeded'): PaymentIntent
{
    return PaymentIntent::constructFrom([
        'id'                   => $id,
        'object'               => 'payment_intent',
        'status'               => $status,
        'amount'               => 5000,
        'currency'             => 'usd',
        'payment_method'       => 'pm_test',
        'payment_method_types' => ['card'],
        'metadata'             => $metadata,
    ]);
}

/**
 * @return array{0: User, 1: Account, 2: string}
 */
function ledgerAuthPayseraOrder(int $amount = 2500): array
{
    $user = User::factory()->withPersonalTeam()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $orderId = 'PAY-' . strtoupper(Str::random(12));

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
 * Query string carrying a Paysera-signed response (data + ss1) for an order.
 */
function ledgerAuthPayseraSigned(string $orderId, int $amount, string $currency = 'EUR', string $status = '1'): string
{
    $data = PayseraDepositService::encodePayseraData(http_build_query([
        'projectid' => '12345',
        'orderid'   => $orderId,
        'amount'    => $amount,
        'currency'  => $currency,
        'status'    => $status,
        'requestid' => 'REQ-' . $orderId,
        'test'      => '1',
    ]));

    return http_build_query([
        'order_id' => $orderId,
        'data'     => $data,
        'ss1'      => md5($data . 'test-sign-password'),
    ]);
}

describe('GraphQL ownership', function (): void {
    it('refuses initiatePayment from an account the caller does not own', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $attacker = User::factory()->create();

        $json = ledgerAuthGraphql($this, $attacker, '
            mutation ($input: InitiatePaymentInput!) { initiatePayment(input: $input) { id } }
        ', ['input' => [
            'account_uuid' => $account->uuid,
            'amount'       => 100,
            'currency'     => 'USD',
            'type'         => 'transfer',
        ]]);

        expect($json['errors'][0]['message'] ?? null)->toBe('You are not authorized to use this account.');
        expect(PaymentTransaction::query()->where('account_uuid', $account->uuid)->exists())->toBeFalse();

        // The owner passes the ownership check.
        $json = ledgerAuthGraphql($this, $owner, '
            mutation ($input: InitiatePaymentInput!) { initiatePayment(input: $input) { id } }
        ', ['input' => [
            'account_uuid' => $account->uuid,
            'amount'       => 100,
            'currency'     => 'USD',
            'type'         => 'transfer',
        ]]);

        expect($json['errors'][0]['message'] ?? null)->not->toBe('You are not authorized to use this account.');
    });

    it('refuses initiateAssetTransfer from an account the caller does not own', function (): void {
        $owner = User::factory()->create();
        $source = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $attacker = User::factory()->create();
        $target = Account::factory()->create(['user_uuid' => $attacker->uuid]);

        $json = ledgerAuthGraphql($this, $attacker, '
            mutation ($input: InitiateAssetTransferInput!) { initiateAssetTransfer(input: $input) }
        ', ['input' => [
            'from_account_uuid' => $source->uuid,
            'to_account_uuid'   => $target->uuid,
            'asset_code'        => 'USD',
            'amount'            => 10,
        ]]);

        expect($json['errors'][0]['message'] ?? null)->toBe('You are not authorized to use this account.');
    });

    it('limits mintStablecoin and redeemStablecoin to stablecoin operators', function (): void {
        $user = User::factory()->create();

        $mint = ledgerAuthGraphql($this, $user, '
            mutation ($input: MintStablecoinInput!) { mintStablecoin(input: $input) { id } }
        ', ['input' => ['stablecoin_code' => 'FUSD', 'amount' => 100, 'pool_id' => 'pool-1']]);
        expect($mint['errors'][0]['message'] ?? null)->toBe('You are not authorized to perform stablecoin operations.');

        $redeem = ledgerAuthGraphql($this, $user, '
            mutation ($input: RedeemStablecoinInput!) { redeemStablecoin(input: $input) { id } }
        ', ['input' => ['reserve_id' => 'reserve-1', 'amount' => 1]]);
        expect($redeem['errors'][0]['message'] ?? null)->toBe('You are not authorized to perform stablecoin operations.');

        Role::firstOrCreate(['name' => 'stablecoin_operator', 'guard_name' => 'web']);
        $operator = User::factory()->create();
        $operator->assignRole('stablecoin_operator');

        $mint = ledgerAuthGraphql($this, $operator, '
            mutation ($input: MintStablecoinInput!) { mintStablecoin(input: $input) { id } }
        ', ['input' => ['stablecoin_code' => 'FUSD', 'amount' => 100, 'pool_id' => 'pool-1']]);
        expect($mint['errors'][0]['message'] ?? null)->not->toBe('You are not authorized to perform stablecoin operations.');
    });

    it('limits approveLoan to administrators', function (): void {
        $borrower = User::factory()->create();
        $application = LoanApplication::query()->create([
            'id'               => (string) Str::uuid(),
            'borrower_id'      => (string) Str::uuid(),
            'borrower_info'    => [],
            'requested_amount' => '1000.00',
            'term_months'      => 12,
            'purpose'          => 'personal',
            'status'           => 'submitted',
            'submitted_at'     => now(),
        ]);

        $json = ledgerAuthGraphql($this, $borrower, '
            mutation ($input: ApproveLoanInput!) { approveLoan(input: $input) { id status } }
        ', ['input' => ['id' => (string) $application->id, 'approved_amount' => 5000, 'interest_rate' => 1.0]]);

        expect($json['errors'][0]['message'] ?? null)->toBe('You are not authorized to approve loan applications.');
        expect($application->fresh()?->status)->toBe('submitted');
    });

    it('places GraphQL orders on the caller\'s own account only', function (): void {
        $other = User::factory()->create();
        $caller = User::factory()->create(['id' => 987654]);
        // An account whose numeric id happens to equal the caller's user id.
        Account::factory()->create(['id' => 987654, 'user_uuid' => $other->uuid]);

        $json = ledgerAuthGraphql($this, $caller, '
            mutation ($input: PlaceOrderInput!) { placeOrder(input: $input) { id } }
        ', ['input' => [
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'type'           => 'buy',
            'order_type'     => 'market',
            'amount'         => 0.01,
        ]]);

        expect($json['errors'][0]['message'] ?? null)->toBe('Account not found. Please complete your account setup.');
        expect(Order::query()->where('account_id', '987654')->exists())->toBeFalse();
    });

    it('does not cancel another user\'s order', function (): void {
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $orderId = (string) Str::uuid();
        Order::query()->create([
            'order_id'       => $orderId,
            'account_id'     => (string) $ownerAccount->id,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => '0.01',
            'price'          => '50000',
            'status'         => 'open',
        ]);

        $caller = User::factory()->create();
        Account::factory()->create(['user_uuid' => $caller->uuid]);

        $json = ledgerAuthGraphql($this, $caller, 'mutation { cancelOrder(order_id: "' . $orderId . '") { id status } }');

        expect($json['errors'] ?? [])->not->toBeEmpty();
        expect(Order::query()->where('order_id', $orderId)->value('status'))->toBe('open');
    });
});

describe('REST account deposit and withdraw ownership', function (): void {
    it('answers 403 to a non-owner on v1 and v2 deposit and withdraw', function (string $path): void {
        $owner = User::factory()->create();
        $account = Account::factory()->withBalance(10000)->create(['user_uuid' => $owner->uuid]);
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker, ['read', 'write', 'delete']);

        $this->postJson(sprintf($path, $account->uuid), ['amount' => 10, 'asset_code' => 'USD'])
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
    })->with([
        'v1 deposit'  => ['/api/accounts/%s/deposit'],
        'v1 withdraw' => ['/api/accounts/%s/withdraw'],
        'v2 deposit'  => ['/api/v2/accounts/%s/deposit'],
        'v2 withdraw' => ['/api/v2/accounts/%s/withdraw'],
    ]);
});

describe('custodian transfer and mock custodian', function (): void {
    it('answers 403 when the internal account belongs to someone else', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker, ['read', 'write', 'delete']);

        $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'deposit',
        ])->assertForbidden()->assertJsonPath('error', 'FORBIDDEN');
    });

    it('lets the owner past the ownership check', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        CustodianAccount::create([
            'account_uuid'         => $account->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
            'status'               => 'active',
            'is_primary'           => true,
        ]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'deposit',
        ]);

        expect($response->status())->not->toBe(403);
    });
});

describe('Stripe deposit confirmation', function (): void {
    it('credits a PaymentIntent once and refuses a replay', function (): void {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        /** @var PaymentServiceInterface&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentServiceInterface::class);
        $paymentService->shouldReceive('processStripeDeposit')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['account_uuid'] === $account->uuid
                && $data['amount'] === 5000
                && $data['external_reference'] === 'pi_test_deposit_1'))
            ->andReturn('txn_1');

        $gateway = ledgerAuthStripeGateway($paymentService, ledgerAuthPaymentIntent([
            'user_id'      => (string) $user->id,
            'type'         => 'deposit',
            'account_uuid' => $account->uuid,
        ]));

        $result = $gateway->processDeposit('pi_test_deposit_1', $user);
        expect($result['account_uuid'])->toBe($account->uuid);
        expect(app(DepositReplayGuard::class)->isClaimed(DepositReplayGuard::SOURCE_STRIPE, 'pi_test_deposit_1'))->toBeTrue();

        expect(fn () => $gateway->processDeposit('pi_test_deposit_1', $user))
            ->toThrow(DepositAlreadyProcessedException::class);
    });

    it('refuses a PaymentIntent created for another user or another user\'s account', function (array $metadataFor): void {
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $caller = User::factory()->create();
        $callerAccount = Account::factory()->create(['user_uuid' => $caller->uuid]);

        $metadata = [
            'type'         => $metadataFor['type'],
            'user_id'      => (string) ($metadataFor['user'] === 'owner' ? $owner->id : $caller->id),
            'account_uuid' => $metadataFor['account'] === 'owner' ? $ownerAccount->uuid : $callerAccount->uuid,
        ];

        /** @var PaymentServiceInterface&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentServiceInterface::class);
        $paymentService->shouldNotReceive('processStripeDeposit');

        $gateway = ledgerAuthStripeGateway($paymentService, ledgerAuthPaymentIntent($metadata));

        expect(fn () => $gateway->processDeposit('pi_test_deposit_1', $caller))
            ->toThrow(DepositNotAllowedException::class);
        expect(app(DepositReplayGuard::class)->isClaimed(DepositReplayGuard::SOURCE_STRIPE, 'pi_test_deposit_1'))->toBeFalse();
    })->with([
        'intent of another user'       => [['user' => 'owner', 'account' => 'owner', 'type' => 'deposit']],
        'own user id, foreign account' => [['user' => 'caller', 'account' => 'owner', 'type' => 'deposit']],
        'own account, not a deposit'   => [['user' => 'caller', 'account' => 'caller', 'type' => 'subscription']],
    ]);

    it('releases the claim when the credit cannot be started', function (): void {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        /** @var PaymentServiceInterface&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentServiceInterface::class);
        $paymentService->shouldReceive('processStripeDeposit')->once()->andThrow(new RuntimeException('workflow down'));
        $paymentService->shouldReceive('processStripeDeposit')->once()->andReturn('txn_2');

        $gateway = ledgerAuthStripeGateway($paymentService, ledgerAuthPaymentIntent([
            'user_id'      => (string) $user->id,
            'type'         => 'deposit',
            'account_uuid' => $account->uuid,
        ], 'pi_test_deposit_2'));

        expect(fn () => $gateway->processDeposit('pi_test_deposit_2', $user))->toThrow(RuntimeException::class);
        expect(app(DepositReplayGuard::class)->isClaimed(DepositReplayGuard::SOURCE_STRIPE, 'pi_test_deposit_2'))->toBeFalse();

        $gateway->processDeposit('pi_test_deposit_2', $user);
        expect(app(DepositReplayGuard::class)->isClaimed(DepositReplayGuard::SOURCE_STRIPE, 'pi_test_deposit_2'))->toBeTrue();
    });

    it('tells the user a replayed confirmation was already credited', function (): void {
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        /** @var PaymentServiceInterface&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentServiceInterface::class);
        $paymentService->shouldReceive('processStripeDeposit')->once()->andReturn('txn_3');
        $this->app->instance(PaymentGatewayService::class, ledgerAuthStripeGateway($paymentService, ledgerAuthPaymentIntent([
            'user_id'      => (string) $user->id,
            'type'         => 'deposit',
            'account_uuid' => $account->uuid,
        ], 'pi_test_deposit_3')));

        $this->actingAs($user)
            ->get('/wallet/deposit/confirm?payment_intent_id=pi_test_deposit_3')
            ->assertRedirect(route('wallet.index'))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get('/wallet/deposit/confirm?payment_intent_id=pi_test_deposit_3')
            ->assertRedirect(route('wallet.index'))
            ->assertSessionHas('info');
    });
});

describe('Paysera deposit callback', function (): void {
    it('answers 404 while the Paysera connector is disabled', function (): void {
        config(['custodians.connectors.paysera.enabled' => false]);
        [$user, , $orderId] = ledgerAuthPayseraOrder();

        $this->actingAs($user)
            ->getJson("/wallet/deposit/paysera/callback?order_id={$orderId}&status=completed")
            ->assertNotFound();
        $this->actingAs($user)
            ->postJson('/wallet/deposit/paysera/initiate', [])
            ->assertNotFound();
    });

    it('credits the stored amount once, only to the user who started the order', function (): void {
        config([
            'custodians.connectors.paysera.enabled' => true,
            'services.paysera.project_id'           => '12345',
            'services.paysera.sign_password'        => 'test-sign-password',
        ]);
        [$user, $account, $orderId] = ledgerAuthPayseraOrder(2500);

        /** @var PaymentServiceInterface&MockInterface $paymentService */
        $paymentService = Mockery::mock(PaymentServiceInterface::class);
        $this->app->instance(PaymentServiceInterface::class, $paymentService);
        $paymentService->shouldReceive('processStripeDeposit')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['account_uuid'] === $account->uuid
                && $data['amount'] === 2500
                && $data['currency'] === 'EUR'))
            ->andReturn('txn_paysera');

        // Another user cannot complete it, even with a valid Paysera response.
        $other = User::factory()->withPersonalTeam()->create();
        $this->actingAs($other)
            ->getJson('/wallet/deposit/paysera/callback?' . ledgerAuthPayseraSigned($orderId, 2500))
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        // A different (signed) amount than the order is refused.
        $this->actingAs($user)
            ->getJson('/wallet/deposit/paysera/callback?' . ledgerAuthPayseraSigned($orderId, 999999))
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->actingAs($user)
            ->getJson('/wallet/deposit/paysera/callback?' . ledgerAuthPayseraSigned($orderId, 2500))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Replay.
        $this->actingAs($user)
            ->getJson('/wallet/deposit/paysera/callback?' . ledgerAuthPayseraSigned($orderId, 2500))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Deposit already processed');
    });

    it('refuses to start a deposit for an account the caller does not own', function (): void {
        config(['custodians.connectors.paysera.enabled' => true]);
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $caller = User::factory()->withPersonalTeam()->create();

        $this->actingAs($caller)
            ->postJson('/wallet/deposit/paysera/initiate', [
                'account_uuid' => $account->uuid,
                'amount'       => 1000,
                'currency'     => 'EUR',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
    });
});

describe('transaction reversal', function (): void {
    it('is limited to the account owner, also for administrators', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['read', 'write', 'delete']);

        $this->postJson("/api/accounts/{$account->uuid}/transactions/reverse", [
            'amount'           => 10,
            'asset_code'       => 'USD',
            'transaction_type' => 'debit',
            'reversal_reason'  => 'test',
        ])->assertForbidden();
    });

    it('reverses a given original transaction at most once', function (): void {
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $payload = [
            'amount'                  => 10,
            'asset_code'              => 'USD',
            'transaction_type'        => 'debit',
            'reversal_reason'         => 'duplicate charge',
            'original_transaction_id' => 'txn-original-1',
        ];
        $claimKey = $account->uuid . ':' . hash('sha256', 'txn-original-1');

        // A reversal that does not go through leaves no claim behind, so it
        // can be retried.
        $failed = $this->postJson("/api/accounts/{$account->uuid}/transactions/reverse", $payload);
        if ($failed->status() !== 200) {
            expect(ProcessedWebhookEvent::query()
                ->where('provider', 'transaction_reversal')
                ->where('event_id', $claimKey)
                ->exists())->toBeFalse();

            ProcessedWebhookEvent::query()->create([
                'provider'     => 'transaction_reversal',
                'event_id'     => $claimKey,
                'event_type'   => 'reversal',
                'processed_at' => now(),
            ]);
        }

        // Once a reversal of that original transaction exists, a repeat is refused.
        $this->postJson("/api/accounts/{$account->uuid}/transactions/reverse", $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'ALREADY_REVERSED');

        // A different original transaction is not affected.
        $other = $this->postJson("/api/accounts/{$account->uuid}/transactions/reverse", array_merge($payload, [
            'original_transaction_id' => 'txn-original-2',
        ]));
        expect($other->status())->not->toBe(409);
    });

    it('answers 404 from the controller itself when direct ledger funding is disabled', function (): void {
        config(['ledger.direct_funding_enabled' => false]);
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $this->actingAs($owner);

        expect(fn () => app(TransactionReversalController::class)->reverseTransaction(
            Request::create("/api/accounts/{$account->uuid}/transactions/reverse", 'POST'),
            $account->uuid,
        ))->toThrow(NotFoundHttpException::class);
    });
});
