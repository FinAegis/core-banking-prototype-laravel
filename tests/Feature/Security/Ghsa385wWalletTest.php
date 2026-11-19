<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Events\TransactionSponsored;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\User\Values\UserRoles;
use App\Domain\Wallet\Exceptions\AddressRegistrationRejectedException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\EvmTransactionProcessor;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use App\Domain\Wallet\Services\Send\SolanaSendPreparer;
use App\Domain\Wallet\Services\Send\SolanaSendSubmitter;
use App\Domain\Wallet\Services\SponsorshipBudget;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use App\Jobs\ProcessAlchemyWebhookJob;
use App\Jobs\ProcessHeliusWebhookJob;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/*
| GHSA-385w-q6j4-5qw6 — Zelta wallet cluster.
|
|  C15/C16 POST /api/v1/wallet/addresses: per-user cap, new-claim rate limit,
|          Privy ownership verification (enforce / monitor).
|  C17     attribution: no registering another user's smart account, earliest
|          claim wins in the Alchemy resolver, per-user activity-feed rows.
|  C62     Helius single-address updates fail closed on a failed read.
|  C63     one generic refusal message (no "is this a customer" oracle).
|  C64     reserved / malformed addresses refused; reconcile ignores them.
|  C13     shared global sponsorship unit taken at submit, not at prepare;
|          failed prepares give the per-user unit back.
|  C43     generic sponsorship is charged to the USD sponsorship budget.
|  C16     busy watched addresses stop costing an RPC call and a push per tx.
|  C63     wallet:reassign-address operator recovery for a squatted address.
*/

beforeEach(function (): void {
    Cache::flush();
    config([
        'services.helius.webhook_id'                          => '',
        'services.helius.api_key'                             => '',
        'wallet.address_registration.ownership_verification'  => 'off',
        'wallet.address_registration.max_addresses_per_chain' => 2,
        'wallet.address_registration.max_new_claims_per_hour' => 10,
        'wallet.sponsorship.per_user_daily_limit'             => 30,
        'wallet.sponsorship.global_daily_limit'               => 5000,
        'wallet.sponsorship.daily_budget_usd'                 => null,
    ]);
});

function ghsa385wSolana(): string
{
    // Random 32-byte ed25519-sized key, never all-zero (that's the System program).
    return Base58::encode("\x01" . random_bytes(31));
}

function ghsa385wEvm(): string
{
    return '0x' . bin2hex(random_bytes(20));
}

/**
 * @return TestResponse<SymfonyResponse>
 */
function ghsa385wRegister(PHPUnitTestCase $test, User $user, string $evm, string $solana): TestResponse
{
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    return $test->postJson('/api/v1/wallet/addresses', [
        'evm'    => ['address' => $evm],
        'solana' => ['address' => $solana],
    ]);
}

function ghsa385wRowCount(User $user): int
{
    return BlockchainAddress::where('user_uuid', $user->uuid)->count();
}

/**
 * Bind a Guzzle client whose responses are served from a queue, for the
 * Privy server-side user lookup.
 *
 * @param array<int, GuzzleResponse> $responses
 */
function ghsa385wFakePrivy(array $responses): void
{
    config(['privy.app_id' => 'privy-app', 'privy.app_secret' => 'privy-secret']);
    app()->instance(ClientInterface::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
}

/**
 * @param array<int, array<string, mixed>> $linked
 */
function ghsa385wPrivyUser(array $linked): GuzzleResponse
{
    return new GuzzleResponse(200, [], (string) json_encode(['id' => 'did:privy:x', 'linked_accounts' => $linked]));
}

/**
 * @param  array<int, string>|null $addresses
 * @return array<string, mixed>
 */
function ghsa385wHeliusConfig(?array $addresses): array
{
    $config = [
        'webhookID'        => 'test-webhook-id',
        'webhookURL'       => 'https://zelta.app/api/webhooks/helius/solana',
        'transactionTypes' => ['ANY'],
        'webhookType'      => 'enhanced',
        'authHeader'       => 'shared-secret',
    ];

    if ($addresses !== null) {
        $config['accountAddresses'] = $addresses;
    }

    return $config;
}

function ghsa385wSolanaRow(User $user, string $address): BlockchainAddress
{
    return BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => $address,
        'public_key' => $address,
        'is_active'  => true,
        'metadata'   => ['provider' => 'privy'],
    ]);
}

/**
 * @return TestResponse<SymfonyResponse>
 */
function ghsa385wPrepare(PHPUnitTestCase $test, User $user, ?string $idempotencyKey = null): TestResponse
{
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    return $test->postJson('/api/v1/wallet/transactions/prepare', [
        'to'       => ghsa385wSolana(),
        'token'    => 'USDC',
        'amount'   => '1.50',
        'network'  => 'SOLANA',
        'quote_id' => 'q_ghsa',
    ], $idempotencyKey !== null ? ['Idempotency-Key' => $idempotencyKey] : []);
}

function ghsa385wPendingRecord(User $user, string $status = WalletSendRecord::STATUS_PENDING): WalletSendRecord
{
    return WalletSendRecord::create([
        'public_id'         => 'pi_send_' . Str::random(20),
        'user_id'           => $user->id,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.50',
        'sender_address'    => ghsa385wSolana(),
        'recipient_address' => ghsa385wSolana(),
        'status'            => $status,
    ]);
}

/**
 * Bind mocks before the first request: the route caches its controller (and
 * the services injected into it) for the rest of the test.
 */
function ghsa385wMockPreparer(WalletSendRecord ...$records): void
{
    /** @var SolanaSendPreparer&MockInterface $preparer */
    $preparer = Mockery::mock(SolanaSendPreparer::class);
    $preparer->shouldReceive('prepare')->andReturn(...array_map(
        fn (WalletSendRecord $record): array => ['record' => $record, 'payload' => ['kind' => 'solana_tx']],
        $records,
    ));
    app()->instance(SolanaSendPreparer::class, $preparer);
}

/**
 * @param WalletSendRecord|null $returns the record submit() hands back; null = submit must not be called
 */
function ghsa385wMockSubmitter(?WalletSendRecord $returns): void
{
    /** @var SolanaSendSubmitter&MockInterface $submitter */
    $submitter = Mockery::mock(SolanaSendSubmitter::class);

    if ($returns === null) {
        $submitter->shouldNotReceive('submit');
    } else {
        $submitter->shouldReceive('submit')->once()->andReturn($returns);
    }

    app()->instance(SolanaSendSubmitter::class, $submitter);
}

function ghsa385wGlobalCount(): int
{
    return (int) Cache::get(SponsorshipCostTracker::globalSendCountCacheKey(), 0);
}

function ghsa385wUserCount(User $user): int
{
    return (int) Cache::get(SponsorshipBudget::perUserCountCacheKey($user->id), 0);
}

/**
 * Run ops:verify-env --json and return one check by name.
 *
 * @return array{name: string, category: string, result: string, detail: string}
 */
function ghsa385wOpsCheck(string $name): array
{
    Artisan::call('ops:verify-env', ['--json' => true]);

    /** @var array{checks: list<array{name: string, category: string, result: string, detail: string}>} $decoded */
    $decoded = json_decode(Artisan::output(), true);

    return collect($decoded['checks'])->keyBy('name')->get($name) ?? ['name' => $name, 'category' => '', 'result' => 'MISSING', 'detail' => ''];
}

/**
 * Spy on the log to count Alchemy watch-list additions: with no notify
 * token configured, AlchemyWebhookManager::addAddress() logs one debug line
 * per address it is asked to watch. (The observer queues a closure, so the
 * manager itself cannot be replaced with a mock.).
 */
function ghsa385wWatchSpy(): MockInterface
{
    config(['services.alchemy.notify_token' => '']);

    return Log::spy();
}

function ghsa385wWatchAttempts(MockInterface $logSpy, int $times): void
{
    $logSpy->shouldHaveReceived('debug')
        ->withArgs(fn (string $message): bool => str_contains($message, 'AlchemyWebhookManager: Skipped'))
        ->times($times);
}

// ─── Mobile contract: a normal user is unaffected ───────────────────────────

describe('mobile contract for a normal user', function (): void {
    it('registers the normal EVM + Solana set with the unchanged 201 body and re-registers it identically', function (): void {
        // Shipped default mode, with Privy confirming the caller's wallets.
        config(['wallet.address_registration.ownership_verification' => 'monitor']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:normal']);
        $evm = '0x' . str_repeat('aB', 20);
        $solana = ghsa385wSolana();
        ghsa385wFakePrivy([ghsa385wPrivyUser([
            ['type' => 'smart_wallet', 'address' => $evm],
            ['type' => 'wallet', 'chain_type' => 'solana', 'address' => $solana],
        ])]);

        $expected = [
            'success' => true,
            'data'    => ['addresses' => [
                ['address' => strtolower($evm), 'network' => 'polygon'],
                ['address' => strtolower($evm), 'network' => 'base'],
                ['address' => strtolower($evm), 'network' => 'arbitrum'],
                ['address' => strtolower($evm), 'network' => 'ethereum'],
                ['address' => $solana, 'network' => 'solana'],
            ]],
        ];

        ghsa385wRegister($this, $user, $evm, $solana)->assertStatus(201)->assertExactJson($expected);

        // The app re-posts after every login: same body, never rate limited.
        foreach (range(1, 12) as $_) {
            ghsa385wRegister($this, $user, $evm, $solana)->assertStatus(201)->assertExactJson($expected);
        }

        expect(ghsa385wRowCount($user))->toBe(5);
    });
});

// ─── C15 / C16: bounded, owner-verified address registration ────────────────

describe('C15 per-user cap on distinct Privy addresses', function (): void {
    it('refuses a third distinct Solana address and leaves the user\'s rows unchanged', function (): void {
        $user = User::factory()->create();
        $evm = ghsa385wEvm();
        $third = ghsa385wSolana();

        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        $before = ghsa385wRowCount($user);

        ghsa385wRegister($this, $user, $evm, $third)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ADDRESS')
            ->assertJsonPath('error.message', AddressRegistrationRejectedException::MESSAGE);

        expect(ghsa385wRowCount($user))->toBe($before)
            ->and(BlockchainAddress::where('chain', 'solana')->where('address', $third)->exists())->toBeFalse();
    });

    it('refuses a third distinct EVM address and leaves the user\'s rows unchanged', function (): void {
        $user = User::factory()->create();
        $solana = ghsa385wSolana();
        $third = ghsa385wEvm();

        ghsa385wRegister($this, $user, ghsa385wEvm(), $solana)->assertStatus(201);
        ghsa385wRegister($this, $user, ghsa385wEvm(), $solana)->assertStatus(201);
        $before = ghsa385wRowCount($user);

        ghsa385wRegister($this, $user, $third, $solana)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_ADDRESS');

        expect(ghsa385wRowCount($user))->toBe($before)
            ->and(BlockchainAddress::where('address', strtolower($third))->exists())->toBeFalse();
    });

    it('still accepts an idempotent re-post of owned addresses once the cap is reached', function (): void {
        $user = User::factory()->create();
        $evm = ghsa385wEvm();
        $first = ghsa385wSolana();

        ghsa385wRegister($this, $user, $evm, $first)->assertStatus(201);
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        $before = ghsa385wRowCount($user);

        ghsa385wRegister($this, $user, $evm, $first)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data.addresses');

        expect(ghsa385wRowCount($user))->toBe($before);
    });

    it('does not count rows from other provisioning paths against the cap', function (): void {
        $user = User::factory()->create();
        // e.g. the legacy derived "Primary Solana" row from solana:backfill
        BlockchainAddress::create([
            'user_uuid'  => $user->uuid,
            'chain'      => 'solana',
            'address'    => ghsa385wSolana(),
            'public_key' => 'x',
            'is_active'  => true,
            'label'      => 'Primary Solana',
        ]);

        $evm = ghsa385wEvm();
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
    });
});

describe('C15 new-claim rate limit', function (): void {
    beforeEach(function (): void {
        config([
            'wallet.address_registration.max_new_claims_per_hour' => 2,
            'wallet.address_registration.max_addresses_per_chain' => 10,
        ]);
    });

    it('returns 429 once a user exceeds the new-claim limit and writes nothing', function (): void {
        $user = User::factory()->create();
        $evm = ghsa385wEvm();
        $blocked = ghsa385wSolana();

        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        $before = ghsa385wRowCount($user);

        $response = ghsa385wRegister($this, $user, $evm, $blocked)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ADDRESS_REGISTRATION_RATE_LIMITED')
            ->assertJsonPath('error.message', 'Too many address registration attempts. Please try again later.')
            ->assertHeader('Retry-After');

        expect($response->json('retry_after'))->toBeInt()->toBeGreaterThan(0)
            ->and((int) $response->headers->get('Retry-After'))->toBe($response->json('retry_after'))
            ->and(array_keys((array) $response->json()))->toBe(['success', 'error', 'retry_after']);

        expect(ghsa385wRowCount($user))->toBe($before)
            ->and(BlockchainAddress::where('address', $blocked)->exists())->toBeFalse();
    });

    it('counts conflict probes, so another customer\'s address cannot be probed without limit', function (): void {
        $owner = User::factory()->create();
        $prober = User::factory()->create();
        $ownedEvm = ghsa385wEvm();
        ghsa385wRegister($this, $owner, $ownedEvm, ghsa385wSolana())->assertStatus(201);

        ghsa385wRegister($this, $prober, $ownedEvm, ghsa385wSolana())->assertStatus(422);
        ghsa385wRegister($this, $prober, $ownedEvm, ghsa385wSolana())->assertStatus(422);
        ghsa385wRegister($this, $prober, $ownedEvm, ghsa385wSolana())->assertStatus(429);

        expect(ghsa385wRowCount($prober))->toBe(0);
    });

    it('never rate limits re-posting addresses the user already owns', function (): void {
        $user = User::factory()->create();
        $evm = ghsa385wEvm();
        $solana = ghsa385wSolana();

        ghsa385wRegister($this, $user, $evm, $solana)->assertStatus(201);
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(201);
        ghsa385wRegister($this, $user, $evm, ghsa385wSolana())->assertStatus(429);

        foreach (range(1, 5) as $_) {
            ghsa385wRegister($this, $user, $evm, $solana)->assertStatus(201);
        }
    });
});

describe('C15/C16 Privy ownership verification', function (): void {
    beforeEach(function (): void {
        config([
            'wallet.address_registration.ownership_verification' => 'enforce',
            // Helius configured: an accepted Solana address is pushed to the watch list.
            'services.helius.webhook_id' => 'test-webhook-id',
            'services.helius.api_key'    => 'test-api-key',
        ]);
        Http::fake(['https://api.helius.xyz/*' => Http::response(ghsa385wHeliusConfig([]), 200)]);
    });

    it('refuses an address that is not a wallet of the caller\'s Privy account and never watches it', function (): void {
        $user = User::factory()->create(['privy_user_id' => 'did:privy:attacker']);
        $busyThirdParty = ghsa385wSolana();
        ghsa385wFakePrivy([ghsa385wPrivyUser([
            ['type' => 'email', 'address' => 'a@example.com'],
            ['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => ghsa385wEvm()],
            ['type' => 'wallet', 'chain_type' => 'solana', 'address' => ghsa385wSolana()],
        ])]);

        ghsa385wRegister($this, $user, ghsa385wEvm(), $busyThirdParty)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => 'This address cannot be registered to your account.'],
            ]);

        expect(ghsa385wRowCount($user))->toBe(0)
            ->and(BlockchainAddress::where('address', $busyThirdParty)->exists())->toBeFalse();
        Http::assertNothingSent();
    });

    it('registers the caller\'s own Privy embedded wallets', function (): void {
        $user = User::factory()->create(['privy_user_id' => 'did:privy:owner']);
        $evm = '0x' . str_repeat('aB', 20);
        $solana = ghsa385wSolana();
        ghsa385wFakePrivy([ghsa385wPrivyUser([
            ['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => $evm],
            ['type' => 'wallet', 'chain_type' => 'solana', 'address' => $solana],
        ])]);

        ghsa385wRegister($this, $user, $evm, $solana)->assertStatus(201);

        expect(ghsa385wRowCount($user))->toBe(5);
        Http::assertSent(fn (HttpRequest $r): bool => $r->method() === 'PUT'
            && in_array($solana, (array) ($r->data()['accountAddresses'] ?? []), true));
    });

    it('accepts a Privy smart wallet as the EVM address', function (): void {
        $user = User::factory()->create(['privy_user_id' => 'did:privy:owner']);
        $smart = ghsa385wEvm();
        $solana = ghsa385wSolana();
        ghsa385wFakePrivy([ghsa385wPrivyUser([
            ['type' => 'smart_wallet', 'address' => $smart, 'smart_wallet_type' => 'kernel'],
            ['type' => 'wallet', 'chain_type' => 'solana', 'address' => $solana],
        ])]);

        ghsa385wRegister($this, $user, $smart, $solana)->assertStatus(201);
    });

    it('fails closed with 503 when Privy cannot be asked, writing nothing', function (): void {
        $user = User::factory()->create(['privy_user_id' => 'did:privy:owner']);
        ghsa385wFakePrivy([new GuzzleResponse(500, [], 'upstream down')]);

        ghsa385wRegister($this, $user, ghsa385wEvm(), ghsa385wSolana())
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'error'   => [
                    'code'    => 'ADDRESS_VERIFICATION_UNAVAILABLE',
                    'message' => 'Wallet ownership could not be verified right now. Please try again shortly.',
                ],
            ]);

        expect(ghsa385wRowCount($user))->toBe(0);
    });

    it('refuses a caller without a Privy identity', function (): void {
        $user = User::factory()->create(['privy_user_id' => null]);

        ghsa385wRegister($this, $user, ghsa385wEvm(), ghsa385wSolana())->assertStatus(422);

        expect(ghsa385wRowCount($user))->toBe(0);
    });

    it('never blocks registration in monitor mode when Privy fails', function (): void {
        config(['wallet.address_registration.ownership_verification' => 'monitor']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:owner']);
        ghsa385wFakePrivy([new GuzzleResponse(500, [], 'upstream down')]);

        ghsa385wRegister($this, $user, ghsa385wEvm(), ghsa385wSolana())->assertStatus(201);

        expect(ghsa385wRowCount($user))->toBe(5);
    });

    it('alerts operators when a Privy-verified owner is blocked by an earlier claim', function (): void {
        $squatted = ghsa385wSolana();
        $ownerEvm = ghsa385wEvm();
        // Bound before the first request: the route caches its controller
        // (and so the Privy HTTP client) for the rest of the test. Only the
        // owner's registration asks Privy.
        ghsa385wFakePrivy([ghsa385wPrivyUser([
            ['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => $ownerEvm],
            ['type' => 'wallet', 'chain_type' => 'solana', 'address' => $squatted],
        ])]);

        config(['wallet.address_registration.ownership_verification' => 'off']);
        $squatter = User::factory()->create();
        ghsa385wRegister($this, $squatter, ghsa385wEvm(), $squatted)->assertStatus(201);

        config(['wallet.address_registration.ownership_verification' => 'monitor']);
        $owner = User::factory()->create(['privy_user_id' => 'did:privy:owner']);
        $logSpy = Log::spy();

        ghsa385wRegister($this, $owner, $ownerEvm, $squatted)
            ->assertStatus(422)
            ->assertJsonPath('error.message', AddressRegistrationRejectedException::MESSAGE);

        $logSpy->shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Privy-verified owner blocked'))
            ->once();
        expect(ghsa385wRowCount($owner))->toBe(0)
            ->and(BlockchainAddress::where('address', $squatted)->value('user_uuid'))->toBe($squatter->uuid);
    });

    it('only logs a mismatch in monitor mode (registration still succeeds)', function (): void {
        config(['wallet.address_registration.ownership_verification' => 'monitor']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:owner']);
        ghsa385wFakePrivy([ghsa385wPrivyUser([])]);

        ghsa385wRegister($this, $user, ghsa385wEvm(), ghsa385wSolana())->assertStatus(201);

        expect(ghsa385wRowCount($user))->toBe(5);
    });
});

// ─── C17: attribution of other users' transfers ─────────────────────────────

describe('C17 attribution', function (): void {
    it('refuses to register another user\'s relayer smart account address', function (): void {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $smart = ghsa385wEvm();
        SmartAccount::create([
            'user_id'         => $owner->id,
            'owner_address'   => ghsa385wEvm(),
            'account_address' => $smart,
            'network'         => 'polygon',
        ]);

        ghsa385wRegister($this, $attacker, $smart, ghsa385wSolana())
            ->assertStatus(422)
            ->assertJsonPath('error.message', AddressRegistrationRejectedException::MESSAGE);

        expect(ghsa385wRowCount($attacker))->toBe(0);
    });

    it('lets the smart account owner register that address', function (): void {
        $owner = User::factory()->create();
        $smart = ghsa385wEvm();
        SmartAccount::create([
            'user_id'         => $owner->id,
            'owner_address'   => ghsa385wEvm(),
            'account_address' => $smart,
            'network'         => 'polygon',
        ]);

        ghsa385wRegister($this, $owner, $smart, ghsa385wSolana())->assertStatus(201);

        expect(ghsa385wRowCount($owner))->toBe(5);
    });

    it('attributes an address claimed in both tables to the earliest claim', function (): void {
        $victim = User::factory()->create();
        $squatter = User::factory()->create();
        $smart = ghsa385wEvm();

        $this->travelTo(now()->subDay());
        SmartAccount::create([
            'user_id'         => $victim->id,
            'owner_address'   => ghsa385wEvm(),
            'account_address' => $smart,
            'network'         => 'polygon',
        ]);
        $this->travelBack();

        // A pre-fix squat: blockchain_addresses row created later by another user.
        BlockchainAddress::create([
            'user_uuid'  => $squatter->uuid,
            'chain'      => 'polygon',
            'address'    => $smart,
            'public_key' => $smart,
            'is_active'  => true,
        ]);

        $resolve = new ReflectionMethod(ProcessAlchemyWebhookJob::class, 'resolveUserId');

        expect($resolve->invoke(new ProcessAlchemyWebhookJob([]), $smart))->toBe($victim->id);
    });

    it('still attributes a Privy address to its (only) owner', function (): void {
        $owner = User::factory()->create();
        $evm = ghsa385wEvm();
        ghsa385wRegister($this, $owner, $evm, ghsa385wSolana())->assertStatus(201);

        $resolve = new ReflectionMethod(ProcessAlchemyWebhookJob::class, 'resolveUserId');

        expect($resolve->invoke(new ProcessAlchemyWebhookJob([]), strtolower($evm)))->toBe($owner->id);
    });

    it('keeps the recipient\'s Solana feed entry when the sender address is a registered counterparty', function (): void {
        $squatter = User::factory()->create();
        $victim = User::factory()->create();
        $commonSender = ghsa385wSolana();
        $victimAddress = ghsa385wSolana();
        $squatterRow = ghsa385wSolanaRow($squatter, $commonSender);
        $victimRow = ghsa385wSolanaRow($victim, $victimAddress);

        $signature = Str::random(88);
        $tx = [
            'signature'      => $signature,
            'tokenTransfers' => [[
                'fromUserAccount' => $commonSender,
                'toUserAccount'   => $victimAddress,
                'mint'            => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                'tokenAmount'     => 250,
            ]],
        ];

        $processor = app(HeliusTransactionProcessor::class);
        // `from` is processed first, exactly as ProcessHeliusWebhookJob does.
        $processor->processTransaction($commonSender, $squatterRow, $squatter->id, $tx);
        $processor->processTransaction($victimAddress, $victimRow, $victim->id, $tx);
        // A webhook retry must not duplicate either side.
        $processor->processTransaction($victimAddress, $victimRow, $victim->id, $tx);

        $victimItems = ActivityFeedItem::where('user_id', $victim->id)->where('reference_type', 'solana_tx')->get();

        expect($victimItems)->toHaveCount(1)
            ->and($victimItems->first()->activity_type->value)->toBe('transfer_in')
            ->and(ActivityFeedItem::where('user_id', $squatter->id)->count())->toBe(1);
    });

    it('keeps the recipient\'s EVM feed entry when the sender address is a registered counterparty', function (): void {
        $squatter = User::factory()->create();
        $victim = User::factory()->create();
        $commonSender = strtolower(ghsa385wEvm());
        $victimAddress = strtolower(ghsa385wEvm());
        $squatterRow = BlockchainAddress::create(['user_uuid' => $squatter->uuid, 'chain' => 'polygon', 'address' => $commonSender, 'public_key' => $commonSender, 'is_active' => true]);
        $victimRow = BlockchainAddress::create(['user_uuid' => $victim->uuid, 'chain' => 'polygon', 'address' => $victimAddress, 'public_key' => $victimAddress, 'is_active' => true]);

        $activity = [
            'hash'        => '0x' . bin2hex(random_bytes(32)),
            'fromAddress' => $commonSender,
            'toAddress'   => $victimAddress,
            'asset'       => 'USDC',
            'value'       => 12.5,
            'category'    => 'token',
        ];

        $processor = app(EvmTransactionProcessor::class);
        $processor->processActivity($commonSender, $squatterRow, $squatter->id, 'polygon', $activity);
        $processor->processActivity($victimAddress, $victimRow, $victim->id, 'polygon', $activity);
        $processor->processActivity($victimAddress, $victimRow, $victim->id, 'polygon', $activity);

        $victimItems = ActivityFeedItem::where('user_id', $victim->id)->where('reference_type', 'evm_tx')->get();

        expect($victimItems)->toHaveCount(1)
            ->and($victimItems->first()->activity_type->value)->toBe('transfer_in');
    });
});

// ─── C62: Helius single-address updates fail closed ─────────────────────────

describe('C62 Helius add/remove never writes from a failed read', function (): void {
    beforeEach(function (): void {
        config([
            'services.helius.webhook_id' => 'test-webhook-id',
            'services.helius.api_key'    => 'test-api-key',
        ]);
    });

    it('does not PUT when the webhook GET fails, even if a later GET would succeed', function (): void {
        Http::fake(['https://api.helius.xyz/*' => Http::sequence()
            ->push(['error' => 'unavailable'], 503)
            ->push(ghsa385wHeliusConfig(['a', 'b', 'c']), 200)
            ->push([], 200)]);

        expect((new HeliusWebhookSyncService())->addAddress(ghsa385wSolana()))->toBeFalse();

        Http::assertNotSent(fn (HttpRequest $r): bool => $r->method() === 'PUT');
    });

    it('does not PUT when the webhook config carries no address list', function (): void {
        Http::fake(['https://api.helius.xyz/*' => Http::sequence()
            ->push(ghsa385wHeliusConfig(null), 200)
            ->push([], 200)]);

        expect((new HeliusWebhookSyncService())->addAddress(ghsa385wSolana()))->toBeFalse()
            ->and((new HeliusWebhookSyncService())->removeAddress('a'))->toBeFalse();

        Http::assertNotSent(fn (HttpRequest $r): bool => $r->method() === 'PUT');
    });

    it('adds to and removes from the current list read in the same snapshot', function (): void {
        $new = ghsa385wSolana();
        Http::fake(['https://api.helius.xyz/*' => Http::sequence()
            ->push(ghsa385wHeliusConfig(['a', 'b']), 200)
            ->push([], 200)
            ->push(ghsa385wHeliusConfig(['a', 'b', $new]), 200)
            ->push([], 200)]);

        $sync = new HeliusWebhookSyncService();

        expect($sync->addAddress($new))->toBeTrue()
            ->and($sync->removeAddress('a'))->toBeTrue();

        $putBodies = [];
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === 'PUT') {
                $putBodies[] = $request->data();
            }
        }

        expect($putBodies)->toHaveCount(2)
            ->and($putBodies[0]['accountAddresses'] ?? null)->toBe(['a', 'b', $new])
            ->and($putBodies[0]['webhookURL'] ?? null)->toBe('https://zelta.app/api/webhooks/helius/solana')
            ->and($putBodies[1]['accountAddresses'] ?? null)->toBe(['b', $new]);
    });
});

// ─── C63: no ownership oracle ───────────────────────────────────────────────

describe('C63 generic refusal', function (): void {
    it('does not reveal that an address belongs to another customer', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $evm = ghsa385wEvm();
        $solana = ghsa385wSolana();
        ghsa385wRegister($this, $owner, $evm, $solana)->assertStatus(201);

        $response = ghsa385wRegister($this, $other, ghsa385wEvm(), $solana)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => 'This address cannot be registered to your account.'],
            ]);
        $message = (string) $response->json('error.message');

        expect($message)->toBe(AddressRegistrationRejectedException::MESSAGE)
            ->and($message)->not->toContain($solana)
            ->and($message)->not->toContain('different user')
            ->and(ghsa385wRowCount($other))->toBe(0)
            ->and(BlockchainAddress::where('address', $solana)->value('user_uuid'))->toBe($owner->uuid);
    });

    it('answers a claimed address and an over-cap address identically', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $claimed = ghsa385wSolana();
        ghsa385wRegister($this, $owner, ghsa385wEvm(), $claimed)->assertStatus(201);

        $otherEvm = ghsa385wEvm();
        ghsa385wRegister($this, $other, $otherEvm, ghsa385wSolana())->assertStatus(201);
        ghsa385wRegister($this, $other, $otherEvm, ghsa385wSolana())->assertStatus(201);

        $claimedResponse = ghsa385wRegister($this, $other, $otherEvm, $claimed)->json();
        $overCapResponse = ghsa385wRegister($this, $other, $otherEvm, ghsa385wSolana())->json();

        expect($claimedResponse)->toBe($overCapResponse);
    });

    it('lets the owner keep re-registering their address', function (): void {
        $owner = User::factory()->create();
        $evm = ghsa385wEvm();
        $solana = ghsa385wSolana();

        ghsa385wRegister($this, $owner, $evm, $solana)->assertStatus(201);
        ghsa385wRegister($this, $owner, $evm, $solana)->assertStatus(201);
    });
});

// ─── C64: reserved / malformed addresses ────────────────────────────────────

describe('C64 reserved and malformed addresses', function (): void {
    it('refuses reserved Solana addresses', function (string $reserved): void {
        $user = User::factory()->create();

        ghsa385wRegister($this, $user, ghsa385wEvm(), $reserved)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => 'This address cannot be registered to your account.'],
            ]);

        expect(ghsa385wRowCount($user))->toBe(0);
    })->with([
        'system program' => '11111111111111111111111111111111',
        'USDC mint'      => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        'token program'  => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
    ]);

    it('refuses reserved EVM addresses', function (string $reserved): void {
        $user = User::factory()->create();

        ghsa385wRegister($this, $user, $reserved, ghsa385wSolana())->assertStatus(422);

        expect(ghsa385wRowCount($user))->toBe(0);
    })->with([
        'zero address'  => '0x0000000000000000000000000000000000000000',
        'polygon USDC'  => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
        'entrypoint v6' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
    ]);

    it('refuses a base58 string that does not decode to a 32-byte key', function (): void {
        $user = User::factory()->create();

        ghsa385wRegister($this, $user, ghsa385wEvm(), str_repeat('z', 44))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_ADDRESS');

        expect(ghsa385wRowCount($user))->toBe(0);
    });

    it('keeps the reconcile alarm quiet for a reserved row but still flags real drift', function (): void {
        config([
            'services.helius.webhook_id' => 'test-webhook-id',
            'services.helius.api_key'    => 'test-api-key',
        ]);
        $user = User::factory()->create();
        $watched = ghsa385wSolana();
        ghsa385wSolanaRow($user, $watched);
        // A pre-fix registration of a reserved address.
        BlockchainAddress::create([
            'user_uuid'  => $user->uuid,
            'chain'      => 'solana',
            'address'    => '11111111111111111111111111111111',
            'public_key' => '11111111111111111111111111111111',
            'is_active'  => true,
        ]);

        Http::fake(['https://api.helius.xyz/*' => Http::response(ghsa385wHeliusConfig([$watched]), 200)]);
        expect(Artisan::call('solana:reconcile-helius'))->toBe(0);

        $missing = ghsa385wSolana();
        ghsa385wSolanaRow(User::factory()->create(), $missing);
        expect(Artisan::call('solana:reconcile-helius'))->toBe(1);
    });
});

// ─── C13: global sponsorship unit only for real broadcasts ──────────────────

describe('C13 sponsorship units', function (): void {
    it('does not let failed prepares use up any sponsorship unit', function (): void {
        config(['wallet.sponsorship.per_user_daily_limit' => 2, 'wallet.sponsorship.global_daily_limit' => 2]);
        $user = User::factory()->create(); // no registered sender address

        foreach (range(1, 4) as $_) {
            ghsa385wPrepare($this, $user)->assertStatus(422)->assertJsonPath('error.code', 'NO_SENDER_ADDRESS');
        }

        expect(ghsa385wUserCount($user))->toBe(0)
            ->and(ghsa385wGlobalCount())->toBe(0);
    });

    it('reserves only the caller\'s own unit at prepare — never the shared global one', function (): void {
        $user = User::factory()->create();
        ghsa385wSolanaRow($user, ghsa385wSolana());
        ghsa385wMockPreparer(ghsa385wPendingRecord($user), ghsa385wPendingRecord($user));

        ghsa385wPrepare($this, $user)->assertStatus(201);
        ghsa385wPrepare($this, $user)->assertStatus(201);

        expect(ghsa385wUserCount($user))->toBe(2)
            ->and(ghsa385wGlobalCount())->toBe(0);
    });

    it('gives the per-user unit back for an idempotent replay', function (): void {
        $user = User::factory()->create();
        ghsa385wSolanaRow($user, ghsa385wSolana());
        $existing = ghsa385wPendingRecord($user);
        $existing = WalletSendRecord::query()->findOrFail($existing->id); // not "recently created"
        ghsa385wMockPreparer($existing);

        ghsa385wPrepare($this, $user, (string) Str::uuid())->assertStatus(201);

        expect(ghsa385wUserCount($user))->toBe(0);
    });

    it('gives back exactly the unit it took, and only once', function (): void {
        $userKey = SponsorshipBudget::perUserCountCacheKey(4242);
        $globalKey = SponsorshipCostTracker::globalSendCountCacheKey();
        Cache::put($userKey, 5, now()->addDay());
        Cache::put($globalKey, 7, now()->addDay());

        $budget = app(SponsorshipBudget::class);
        expect($budget->reserveUser(4242))->toBeNull()
            ->and($budget->consumeGlobal())->toBeNull()
            ->and((int) Cache::get($userKey))->toBe(6)
            ->and((int) Cache::get($globalKey))->toBe(8);

        $budget->releaseUser();
        $budget->releaseUser();
        $budget->releaseGlobal();
        $budget->releaseGlobal();
        // An instance that took nothing gives nothing back.
        app(SponsorshipBudget::class)->releaseUser();
        app(SponsorshipBudget::class)->releaseGlobal();

        expect((int) Cache::get($userKey))->toBe(5)
            ->and((int) Cache::get($globalKey))->toBe(7);
    });

    it('still refuses prepare with 429 once the global ceiling is reached, without using a user unit', function (): void {
        config(['wallet.sponsorship.global_daily_limit' => 1]);
        Cache::put(SponsorshipCostTracker::globalSendCountCacheKey(), 1, now()->addDay());
        $user = User::factory()->create();

        ghsa385wPrepare($this, $user)->assertStatus(429)->assertJsonPath('error.code', 'SEND_TEMPORARILY_UNAVAILABLE');

        expect(ghsa385wUserCount($user))->toBe(0);
    });

    it('takes the global unit at submit when the send is broadcast', function (): void {
        $user = User::factory()->create();
        $record = ghsa385wPendingRecord($user);
        ghsa385wMockSubmitter((clone $record)->forceFill(['status' => WalletSendRecord::STATUS_SUBMITTED, 'tx_hash' => 'sig']));

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/wallet/transactions/submit', ['intent_id' => $record->public_id, 'signature' => base64_encode(random_bytes(64))])
            ->assertOk();

        expect(ghsa385wGlobalCount())->toBe(1);
    });

    it('gives the global unit back when the broadcast is refused', function (): void {
        $user = User::factory()->create();
        $record = ghsa385wPendingRecord($user);
        ghsa385wMockSubmitter((clone $record)->forceFill(['status' => WalletSendRecord::STATUS_FAILED, 'error_code' => 'SOLANA_RPC_REJECTED']));

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/wallet/transactions/submit', ['intent_id' => $record->public_id, 'signature' => base64_encode(random_bytes(64))])
            ->assertOk();

        expect(ghsa385wGlobalCount())->toBe(0);
    });

    it('refuses submit with 429 once the global ceiling is reached and leaves the intent pending', function (): void {
        config(['wallet.sponsorship.global_daily_limit' => 1]);
        Cache::put(SponsorshipCostTracker::globalSendCountCacheKey(), 1, now()->addDay());
        $user = User::factory()->create();
        $record = ghsa385wPendingRecord($user);
        ghsa385wMockSubmitter(null);

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/wallet/transactions/submit', ['intent_id' => $record->public_id, 'signature' => base64_encode(random_bytes(64))])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'SEND_TEMPORARILY_UNAVAILABLE');

        expect($record->fresh()?->status)->toBe(WalletSendRecord::STATUS_PENDING)
            ->and(ghsa385wGlobalCount())->toBe(1);
    });

    it('takes no unit for a re-submit of an already submitted intent', function (): void {
        $user = User::factory()->create();
        $record = ghsa385wPendingRecord($user, WalletSendRecord::STATUS_SUBMITTED);
        ghsa385wMockSubmitter($record);

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/wallet/transactions/submit', ['intent_id' => $record->public_id, 'signature' => base64_encode(random_bytes(64))])
            ->assertOk();

        expect(ghsa385wGlobalCount())->toBe(0);
    });
});

// ─── C43: generic sponsorship moves the USD budget ──────────────────────────

describe('C43 generic sponsorship spend', function (): void {
    it('charges a sponsored relayer op to the daily USD budget so the budget can be exhausted by it', function (): void {
        config(['wallet.sponsorship.daily_budget_usd' => '0.10']);
        $budget = app(SponsorshipBudget::class);
        $tracker = app(SponsorshipCostTracker::class);

        // Before any spend the budget admits generic sponsorship.
        expect($budget->consume(1001, 'relayer.sponsor'))->toBeNull();

        event(new TransactionSponsored(ghsa385wEvm(), '0x' . str_repeat('ab', 32), SupportedNetwork::POLYGON, 0.06, 'USDC'));
        expect($tracker->todaysSpendUsdMicro())->toBe(60_000)
            ->and($budget->consume(1001, 'relayer.sponsor'))->toBeNull();

        event(new TransactionSponsored(ghsa385wEvm(), '0x' . str_repeat('cd', 32), SupportedNetwork::POLYGON, 0.05, 'USDC'));
        expect($tracker->todaysSpendUsdMicro())->toBe(110_000)
            ->and($budget->consume(1001, 'relayer.sponsor'))->toBe(SponsorshipBudget::DENIED_BUDGET_EXHAUSTED);
    });
});

// ─── C16: busy watched address breaker ──────────────────────────────────────

describe('C16 busy watched address', function (): void {
    it('stops the per-transaction push once an address is over its hourly budget but still records every transfer', function (): void {
        config(['wallet.address_registration.inbound_side_effects_per_address_per_hour' => 2]);
        $user = User::factory()->create();
        $watched = ghsa385wSolana();
        ghsa385wSolanaRow($user, $watched);

        $txs = array_map(fn (int $i): array => [
            'signature'      => 'sig_busy_' . $i . '_' . Str::random(40),
            'type'           => 'TRANSFER',
            'tokenTransfers' => [[
                'fromUserAccount' => ghsa385wSolana(),
                'toUserAccount'   => $watched,
                'tokenAmount'     => '5',
                'mint'            => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            ]],
            'nativeTransfers' => [],
        ], range(1, 4));

        /** @var PushNotificationService&MockInterface $push */
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendTransactionReceived')->times(2);

        (new ProcessHeliusWebhookJob($txs))->handle(app(HeliusTransactionProcessor::class), $push);

        expect(ActivityFeedItem::where('user_id', $user->id)->where('reference_type', 'solana_tx')->count())->toBe(4);
    });

    it('keeps pushing for an address under its hourly budget', function (): void {
        $user = User::factory()->create();
        $watched = ghsa385wSolana();
        ghsa385wSolanaRow($user, $watched);

        $txs = array_map(fn (int $i): array => [
            'signature'      => 'sig_normal_' . $i . '_' . Str::random(40),
            'type'           => 'TRANSFER',
            'tokenTransfers' => [[
                'fromUserAccount' => ghsa385wSolana(),
                'toUserAccount'   => $watched,
                'tokenAmount'     => '5',
                'mint'            => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            ]],
            'nativeTransfers' => [],
        ], range(1, 3));

        /** @var PushNotificationService&MockInterface $push */
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendTransactionReceived')->times(3);

        (new ProcessHeliusWebhookJob($txs))->handle(app(HeliusTransactionProcessor::class), $push);
    });
});

// ─── C63: operator recovery for a squatted address ──────────────────────────

describe('C63 wallet:reassign-address', function (): void {
    beforeEach(function (): void {
        Role::firstOrCreate(['name' => UserRoles::ADMIN->value, 'guard_name' => 'web']);
        $this->operator = User::factory()->create(['email' => 'ghsa-op@example.com']);
        $this->operator->assignRole(UserRoles::ADMIN->value);
    });

    it('moves a squatted address to its owner, who can then register it', function (): void {
        $squatter = User::factory()->create();
        $owner = User::factory()->create(['email' => 'ghsa-owner@example.com']);
        $evm = ghsa385wEvm();
        $solana = ghsa385wSolana();
        ghsa385wRegister($this, $squatter, $evm, $solana)->assertStatus(201);
        Cache::put('webhook:addr_to_user:' . strtolower($evm), $squatter->id, 3600);

        expect(Artisan::call('wallet:reassign-address', [
            // Operators may paste a checksummed (mixed-case) address.
            '--address'        => '0x' . strtoupper(substr($evm, 2)),
            '--to-user'        => 'ghsa-owner@example.com',
            '--operator-email' => 'ghsa-op@example.com',
            '--confirm'        => true,
        ]))->toBe(0);

        expect(BlockchainAddress::where('address', strtolower($evm))->pluck('user_uuid')->unique()->all())->toBe([$owner->uuid])
            ->and(Cache::has('webhook:addr_to_user:' . strtolower($evm)))->toBeFalse()
            // The Solana row was not named, so it stays where it is.
            ->and(BlockchainAddress::where('address', $solana)->value('user_uuid'))->toBe($squatter->uuid);

        ghsa385wRegister($this, $owner, $evm, ghsa385wSolana())->assertStatus(201);
    });

    it('refuses a non-admin operator or a missing --confirm and moves nothing', function (): void {
        $squatter = User::factory()->create();
        User::factory()->create(['email' => 'ghsa-owner@example.com']);
        User::factory()->create(['email' => 'ghsa-not-admin@example.com']);
        $solana = ghsa385wSolana();
        ghsa385wRegister($this, $squatter, ghsa385wEvm(), $solana)->assertStatus(201);

        expect(Artisan::call('wallet:reassign-address', [
            '--address'        => $solana,
            '--to-user'        => 'ghsa-owner@example.com',
            '--operator-email' => 'ghsa-not-admin@example.com',
            '--confirm'        => true,
        ]))->toBe(1)
            ->and(Artisan::call('wallet:reassign-address', [
                '--address'        => $solana,
                '--to-user'        => 'ghsa-owner@example.com',
                '--operator-email' => 'ghsa-op@example.com',
            ]))->toBe(1);

        expect(BlockchainAddress::where('address', $solana)->value('user_uuid'))->toBe($squatter->uuid);
    });
});

// ─── Review follow-ups (W1–W3) ───────────────────────────────────────────────

describe('review W1 ownership verification rollout', function (): void {
    it('warns in production while ownership verification does not enforce', function (string $mode): void {
        config(['wallet.address_registration.ownership_verification' => $mode]);
        app()->detectEnvironment(fn (): string => 'production');

        $check = ghsa385wOpsCheck('wallet.address_registration.ownership_verification');

        expect($check['result'])->toBe('WARN')
            ->and($check['detail'])->toContain("WALLET_ADDRESS_OWNERSHIP_VERIFICATION={$mode}");
    })->with(['monitor', 'off']);

    it('passes enforce with the Privy server credentials and fails it without them', function (): void {
        config([
            'wallet.address_registration.ownership_verification' => 'enforce',
            'privy.app_id'                                       => 'privy-app',
            'privy.app_secret'                                   => 'privy-secret',
        ]);
        app()->detectEnvironment(fn (): string => 'production');

        expect(ghsa385wOpsCheck('wallet.address_registration.ownership_verification')['result'])->toBe('PASS');

        config(['privy.app_secret' => '']);
        $check = ghsa385wOpsCheck('wallet.address_registration.ownership_verification');

        expect($check['result'])->toBe('FAIL')
            ->and($check['detail'])->toContain('PRIVY_APP_SECRET');
    });

    it('logs a monitor mismatch with the Privy wallets it was compared against', function (): void {
        config(['wallet.address_registration.ownership_verification' => 'monitor']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:monitored']);
        $evm = ghsa385wEvm();
        $solana = ghsa385wSolana();
        $linkedEvm = ghsa385wEvm();
        ghsa385wFakePrivy([ghsa385wPrivyUser([
            ['type' => 'email', 'address' => 'm@example.com'],
            ['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => $linkedEvm, 'wallet_client_type' => 'privy'],
            ['type' => 'wallet', 'chain_type' => 'solana', 'address' => $solana, 'wallet_client_type' => 'privy'],
        ])]);
        $logSpy = Log::spy();

        ghsa385wRegister($this, $user, $evm, $solana)->assertStatus(201);

        $logSpy->shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []) use ($evm, $linkedEvm, $solana): bool {
                return str_contains($message, 'is not a wallet of the caller')
                    && ($context['privy_user_id'] ?? null) === 'did:privy:monitored'
                    && ($context['claimed'] ?? null) === ['evm' => strtolower($evm), 'solana' => $solana]
                    && ($context['unlinked'] ?? null) === ['evm' => strtolower($evm)]
                    && ($context['linked_wallets'] ?? null) === [
                        ['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => $linkedEvm, 'wallet_client_type' => 'privy'],
                        ['type' => 'wallet', 'chain_type' => 'solana', 'address' => $solana, 'wallet_client_type' => 'privy'],
                    ]
                    && ($context['linked_types'] ?? null) === ['email' => 1, 'wallet' => 2];
            })
            ->once();
    });
});

describe('review W2 smart account owner cap', function (): void {
    beforeEach(function (): void {
        config(['wallet.address_registration.max_smart_account_owners' => 3]);
    });

    it('refuses a fourth distinct owner address with the registrar\'s generic 422 and never watches it', function (string $path): void {
        $user = User::factory()->create();
        $logSpy = ghsa385wWatchSpy();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        foreach (range(1, 3) as $ignored) {
            $this->postJson($path, ['owner_address' => ghsa385wEvm(), 'network' => 'polygon'])
                ->assertOk()
                ->assertJsonPath('success', true);
        }

        $this->postJson($path, ['owner_address' => ghsa385wEvm(), 'network' => 'polygon'])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => AddressRegistrationRejectedException::MESSAGE],
            ]);

        expect(SmartAccount::where('user_id', $user->id)->count())->toBe(3);
        ghsa385wWatchAttempts($logSpy, 3);
    })->with(['relayer' => ['/api/v1/relayer/account'], 'wallet alias' => ['/api/v1/wallet/create-account']]);

    it('keeps serving an owner address the user already has, on any network', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $owners = [ghsa385wEvm(), ghsa385wEvm(), ghsa385wEvm()];

        foreach ($owners as $owner) {
            $this->postJson('/api/v1/relayer/account', ['owner_address' => $owner, 'network' => 'polygon'])->assertOk();
        }

        $this->postJson('/api/v1/relayer/account', ['owner_address' => $owners[0], 'network' => 'polygon'])
            ->assertOk()
            ->assertJsonPath('data.owner_address', strtolower($owners[0]));
        $this->postJson('/api/v1/relayer/account', ['owner_address' => $owners[0], 'network' => 'base'])
            ->assertOk()
            ->assertJsonPath('data.owner_address', strtolower($owners[0]))
            ->assertJsonPath('data.network', 'base');

        expect(SmartAccount::where('user_id', $user->id)->count())->toBe(4);
    });

    it('does not hand out another user\'s smart account for their owner address', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerAddress = ghsa385wEvm();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/account', ['owner_address' => $ownerAddress, 'network' => 'polygon'])->assertOk();

        Sanctum::actingAs($other, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/account', ['owner_address' => $ownerAddress, 'network' => 'polygon'])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => AddressRegistrationRejectedException::MESSAGE],
            ]);

        expect(SmartAccount::where('owner_address', strtolower($ownerAddress))->pluck('user_id')->all())->toBe([$owner->id]);
    });

    it('does not watch a smart account created past the cap by another path', function (): void {
        $user = User::factory()->create();
        $logSpy = ghsa385wWatchSpy();

        $last = null;
        foreach (range(1, 4) as $ignored) {
            $last = SmartAccount::create([
                'user_id'         => $user->id,
                'owner_address'   => ghsa385wEvm(),
                'account_address' => ghsa385wEvm(),
                'network'         => 'polygon',
            ]);
        }

        ghsa385wWatchAttempts($logSpy, 3);
        $logSpy->shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'over the owner-address cap')
                && ($context['address'] ?? null) === $last->account_address)
            ->once();
    });
});

describe('review W3 concurrency and null dates', function (): void {
    it('answers a claim that loses the race to another user with the generic 422, not a 500', function (): void {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $solana = ghsa385wSolana();

        // The other user's row lands between the loser's existence check and its insert.
        BlockchainAddress::creating(function (BlockchainAddress $row) use ($loser, $winner, $solana): void {
            if ($row->user_uuid === $loser->uuid && $row->address === $solana && ! BlockchainAddress::where('address', $solana)->exists()) {
                DB::table('blockchain_addresses')->insert([
                    'uuid'       => (string) Str::uuid(),
                    'user_uuid'  => $winner->uuid,
                    'chain'      => 'solana',
                    'address'    => $solana,
                    'public_key' => $solana,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        ghsa385wRegister($this, $loser, ghsa385wEvm(), $solana)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => AddressRegistrationRejectedException::MESSAGE],
            ]);

        // The simulated winner row is written inside the loser's transaction
        // here, so it is rolled back with it; the loser must end with nothing.
        expect(ghsa385wRowCount($loser))->toBe(0);
    });

    it('does not run the full Helius sync while a single-address update holds the lock', function (): void {
        // Created before Helius is configured, so the row's own watch-list add is skipped.
        ghsa385wSolanaRow(User::factory()->create(), ghsa385wSolana());
        config(['services.helius.webhook_id' => 'test-webhook-id', 'services.helius.api_key' => 'test-api-key']);
        Http::fake(['https://api.helius.xyz/*' => Http::response(ghsa385wHeliusConfig([]), 200)]);

        $lock = Cache::lock('helius_webhook_update_lock', 45);
        expect($lock->get())->toBeTrue();

        try {
            expect(app(HeliusWebhookSyncService::class)->syncAllAddresses())->toBe(0);
        } finally {
            $lock->release();
        }

        Http::assertNothingSent();
    });

    it('still runs the full Helius sync with the current database list once the lock is free', function (): void {
        $address = ghsa385wSolana();
        ghsa385wSolanaRow(User::factory()->create(), $address);
        config(['services.helius.webhook_id' => 'test-webhook-id', 'services.helius.api_key' => 'test-api-key']);
        Http::fake(['https://api.helius.xyz/*' => Http::response(ghsa385wHeliusConfig([]), 200)]);

        expect(app(HeliusWebhookSyncService::class)->syncAllAddresses())->toBe(1);

        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r): bool => $r->method() === 'PUT'
            && ($r->data()['accountAddresses'] ?? null) === [$address]);
    });

    it('attributes an address to the blockchain_addresses claim when the smart account has no creation date', function (): void {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $smart = ghsa385wEvm();

        $account = SmartAccount::create([
            'user_id'         => $other->id,
            'owner_address'   => ghsa385wEvm(),
            'account_address' => $smart,
            'network'         => 'polygon',
        ]);
        SmartAccount::query()->whereKey($account->getKey())->update(['created_at' => null]);

        BlockchainAddress::create([
            'user_uuid'  => $holder->uuid,
            'chain'      => 'polygon',
            'address'    => $smart,
            'public_key' => $smart,
            'is_active'  => true,
        ]);

        $resolve = new ReflectionMethod(ProcessAlchemyWebhookJob::class, 'resolveUserId');

        expect($resolve->invoke(new ProcessAlchemyWebhookJob([]), $smart))->toBe($holder->id);
    });
});
