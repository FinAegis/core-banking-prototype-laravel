<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Account\Services\Cache\AccountCacheService;
use App\Domain\Asset\Models\Asset;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianTransfer;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Domain\OpenBanking\Enums\ConsentStatus;
use App\Domain\OpenBanking\Models\Consent;
use App\Domain\PaymentRails\Enums\PaymentRail;
use App\Domain\PaymentRails\Enums\RailStatus;
use App\Domain\PaymentRails\Models\PaymentRailTransaction;
use App\Domain\TrustCert\Events\Broadcast\TrustCertStatusChanged;
use App\Domain\TrustCert\Events\CertificateIssued;
use App\Domain\TrustCert\Events\CertificateRevoked;
use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Domain\TrustCert\ValueObjects\Certificate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
| GHSA-385w-q6j4-5qw6, REST read paths: owner (or, where a sibling endpoint
| allows it, operator) scoping on the account reads, custodian reads, open
| banking consent / payment rail transaction / bank transfer status lookups,
| the public BaaS onboarding status and documents endpoints and the TrustCert
| present / certificate / export-pdf endpoints.
*/

beforeEach(function (): void {
    Cache::flush();
    Cache::forget('domain_manager_disabled');
    config([
        'cache.default'    => 'array',
        'modules.disabled' => [],
    ]);
});

function ghsaRrUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    return $user;
}

function ghsaRrUsd(): Asset
{
    return Asset::firstOrCreate(
        ['code' => 'USD'],
        [
            'name'         => 'US Dollar',
            'type'         => 'fiat',
            'precision'    => 2,
            'is_active'    => true,
            'is_tradeable' => true,
            'symbol'       => '$',
            'metadata'     => ['symbol' => '$'],
        ]
    );
}

function ghsaRrMoneyTransferred(string $fromUuid, string $toUuid, int $amount = 1500): string
{
    $eventUuid = (string) Str::uuid();

    DB::table('stored_events')->insert([
        'aggregate_uuid'    => $eventUuid,
        'aggregate_version' => 1,
        'event_version'     => 1,
        'event_class'       => 'App\\Domain\\Account\\Events\\MoneyTransferred',
        'event_properties'  => json_encode([
            'from_uuid' => $fromUuid,
            'to_uuid'   => $toUuid,
            'money'     => ['amount' => $amount],
            'hash'      => ['hash' => 'hash-' . $eventUuid],
        ]),
        'meta_data'  => '{}',
        'created_at' => now(),
    ]);

    return $eventUuid;
}

function ghsaRrIssueCertificate(string $subjectId): Certificate
{
    Event::fake([CertificateIssued::class, CertificateRevoked::class, TrustCertStatusChanged::class]);

    return app(CertificateAuthorityService::class)->issueCertificate(
        subjectId: $subjectId,
        subject: ['name' => 'Holder', 'type' => 'individual'],
        validFrom: now()->subDay(),
        validUntil: now()->addYear(),
    );
}

// ---------------------------------------------------------------------------
// C04: REST account reads (v1 auth:sanctum and v2 auth.api_or_sanctum)
// ---------------------------------------------------------------------------

describe('C04 account transaction history', function (): void {
    it('refuses another user\'s transaction history on v1 and v2', function (string $prefix): void {
        $victim = ghsaRrUser();
        $account = Account::factory()->forUser($victim)->create();
        DB::table('stored_events')->insert([
            'aggregate_uuid'    => $account->uuid,
            'aggregate_version' => 1,
            'event_version'     => 1,
            'event_class'       => 'App\\Domain\\Account\\Events\\MoneyAdded',
            'event_properties'  => json_encode(['money' => ['amount' => 99999], 'hash' => ['hash' => 'secret-hash']]),
            'meta_data'         => '{}',
            'created_at'        => now(),
        ]);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("{$prefix}/accounts/{$account->uuid}/transactions");

        $response->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
        expect($response->getContent())->not->toContain('secret-hash')->not->toContain('99999');
    })->with(['v1' => '/api', 'v2' => '/api/v2']);

    it('still returns the owner\'s transaction history on v1 and v2', function (string $prefix): void {
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();
        DB::table('stored_events')->insert([
            'aggregate_uuid'    => $account->uuid,
            'aggregate_version' => 1,
            'event_version'     => 1,
            'event_class'       => 'App\\Domain\\Account\\Events\\MoneyAdded',
            'event_properties'  => json_encode(['money' => ['amount' => 4200], 'hash' => ['hash' => 'own-hash']]),
            'meta_data'         => '{}',
            'created_at'        => now(),
        ]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson("{$prefix}/accounts/{$account->uuid}/transactions")
            ->assertOk()
            ->assertJsonPath('data.0.amount', 4200)
            ->assertJsonPath('data.0.hash', 'own-hash');
    })->with(['v1' => '/api', 'v2' => '/api/v2']);

    it('answers 404 for an unknown account', function (): void {
        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $this->getJson('/api/accounts/' . Str::uuid() . '/transactions')->assertNotFound();
    });
});

describe('C04 account transfer history and transfer details', function (): void {
    it('refuses another user\'s transfer history on v1 and v2', function (string $prefix): void {
        $victim = ghsaRrUser();
        $account = Account::factory()->forUser($victim)->create();
        $other = Account::factory()->create();
        ghsaRrMoneyTransferred($account->uuid, $other->uuid, 31337);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("{$prefix}/accounts/{$account->uuid}/transfers");

        $response->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
        expect($response->getContent())->not->toContain('31337');
    })->with(['v1' => '/api', 'v2' => '/api/v2']);

    it('still returns the owner\'s transfer history', function (string $prefix): void {
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson("{$prefix}/accounts/{$account->uuid}/transfers")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    })->with(['v1' => '/api', 'v2' => '/api/v2']);

    it('hides a transfer from a caller who is not a party to it', function (string $prefix): void {
        $from = Account::factory()->forUser(ghsaRrUser())->create();
        $to = Account::factory()->forUser(ghsaRrUser())->create();
        $transferUuid = ghsaRrMoneyTransferred($from->uuid, $to->uuid, 27182);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("{$prefix}/transfers/{$transferUuid}");

        $response->assertNotFound();
        expect($response->getContent())->not->toContain('27182')->not->toContain($from->uuid);
    })->with(['v1' => '/api', 'v2' => '/api/v2']);

    it('shows a transfer to the sender and to the recipient', function (string $prefix): void {
        $sender = ghsaRrUser();
        $recipient = ghsaRrUser();
        $from = Account::factory()->forUser($sender)->create();
        $to = Account::factory()->forUser($recipient)->create();
        $transferUuid = ghsaRrMoneyTransferred($from->uuid, $to->uuid, 1618);

        foreach ([$sender, $recipient] as $party) {
            Sanctum::actingAs($party, ['read', 'write', 'delete']);

            $this->getJson("{$prefix}/transfers/{$transferUuid}")
                ->assertOk()
                ->assertJsonPath('data.uuid', $transferUuid)
                ->assertJsonPath('data.from_account_uuid', $from->uuid)
                ->assertJsonPath('data.to_account_uuid', $to->uuid)
                ->assertJsonPath('data.amount', 1618);
        }
    })->with(['v1' => '/api', 'v2' => '/api/v2']);
});

describe('C04 cached balance endpoints', function (): void {
    it('refuses another user\'s balance and summary even when the account cache is warm', function (string $path): void {
        ghsaRrUsd();
        $victim = ghsaRrUser();
        $account = Account::factory()->forUser($victim)->create();
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 777777]);

        // Warm the uuid-keyed account cache the way the owner's own reads do.
        app(AccountCacheService::class)->get($account->uuid);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson(str_replace('{uuid}', $account->uuid, $path));

        $response->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
        expect($response->getContent())->not->toContain('777777');
    })->with([
        'balance' => '/api/accounts/{uuid}/balance',
        'summary' => '/api/accounts/{uuid}/balance/summary',
    ]);

    it('still returns the owner\'s balance and summary', function (): void {
        ghsaRrUsd();
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 5000]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson("/api/accounts/{$account->uuid}/balance")
            ->assertOk()
            ->assertJsonPath('data.account_uuid', $account->uuid)
            ->assertJsonPath('data.balance', 5000);

        $this->getJson("/api/accounts/{$account->uuid}/balance/summary")
            ->assertOk()
            ->assertJsonPath('data.account_uuid', $account->uuid)
            ->assertJsonPath('data.current_balance', 5000);
    });
});

describe('C04 multi-asset balances', function (): void {
    it('refuses another user\'s balances on v1 and v2', function (string $prefix): void {
        ghsaRrUsd();
        $victim = ghsaRrUser();
        $account = Account::factory()->forUser($victim)->create();
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 424242]);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("{$prefix}/accounts/{$account->uuid}/balances");

        $response->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
        expect($response->getContent())->not->toContain('424242');
    })->with(['v1' => '/api', 'v2' => '/api/v2']);

    it('still returns the owner\'s balances on v1 and v2', function (string $prefix): void {
        ghsaRrUsd();
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 12345]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson("{$prefix}/accounts/{$account->uuid}/balances")
            ->assertOk()
            ->assertJsonPath('data.account_uuid', $account->uuid)
            ->assertJsonPath('data.balances.0.balance', 12345)
            ->assertJsonPath('data.summary.total_usd_equivalent', '123.45');
    })->with(['v1' => '/api', 'v2' => '/api/v2']);
});

describe('C04 GET /api/balances', function (): void {
    it('lists only the caller\'s own balances and own totals for a regular user', function (): void {
        ghsaRrUsd();
        $caller = ghsaRrUser();
        $mine = Account::factory()->forUser($caller)->create();
        $victim = ghsaRrUser();
        $theirs = Account::factory()->forUser($victim)->create();
        AccountBalance::create(['account_uuid' => $mine->uuid, 'asset_code' => 'USD', 'balance' => 1000]);
        AccountBalance::create(['account_uuid' => $theirs->uuid, 'asset_code' => 'USD', 'balance' => 900000]);

        Sanctum::actingAs($caller, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/balances');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account_uuid', $mine->uuid)
            ->assertJsonPath('meta.total_accounts', 1)
            ->assertJsonPath('meta.total_balances', 1)
            ->assertJsonPath('meta.asset_totals.USD', '10.00');
        expect($response->getContent())->not->toContain($theirs->uuid)->not->toContain((string) $victim->uuid);
    });

    it('ignores another user\'s user_uuid filter for a regular user', function (): void {
        ghsaRrUsd();
        $victim = ghsaRrUser();
        $theirs = Account::factory()->forUser($victim)->create();
        AccountBalance::create(['account_uuid' => $theirs->uuid, 'asset_code' => 'USD', 'balance' => 900000]);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("/api/balances?user_uuid={$victim->uuid}");

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_accounts', 0)
            ->assertJsonPath('meta.total_balances', 0);
        expect($response->getContent())->not->toContain($theirs->uuid)->not->toContain('9000.00');
    });

    it('keeps the platform-wide listing, user_uuid filter and totals for an operator', function (): void {
        ghsaRrUsd();
        $victim = ghsaRrUser();
        $theirs = Account::factory()->forUser($victim)->create();
        $other = Account::factory()->forUser(ghsaRrUser())->create();
        AccountBalance::create(['account_uuid' => $theirs->uuid, 'asset_code' => 'USD', 'balance' => 900000]);
        AccountBalance::create(['account_uuid' => $other->uuid, 'asset_code' => 'USD', 'balance' => 100000]);

        Sanctum::actingAs(ghsaRrUser('admin'), ['read', 'write', 'delete']);

        $this->getJson('/api/balances')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.account.user_uuid', (string) $victim->uuid)
            ->assertJsonPath('meta.asset_totals.USD', '10000.00');

        $this->getJson("/api/balances?user_uuid={$victim->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account_uuid', $theirs->uuid);
    });
});

// ---------------------------------------------------------------------------
// C07: REST custodian reads
// ---------------------------------------------------------------------------

function ghsaRrCustodianTransfer(string $transactionId, string $fromAccountUuid, string $toAccountUuid): CustodianTransfer
{
    ghsaRrUsd();
    $fromLink = CustodianAccount::create([
        'account_uuid'         => $fromAccountUuid,
        'custodian_name'       => 'mock',
        'custodian_account_id' => 'mock-account-1',
        'status'               => CustodianAccount::STATUS_ACTIVE,
    ]);
    $toLink = CustodianAccount::create([
        'account_uuid'         => $toAccountUuid,
        'custodian_name'       => 'mock',
        'custodian_account_id' => 'mock-account-2',
        'status'               => CustodianAccount::STATUS_ACTIVE,
    ]);

    return CustodianTransfer::create([
        'id'                        => $transactionId,
        'from_account_uuid'         => $fromAccountUuid,
        'to_account_uuid'           => $toAccountUuid,
        'from_custodian_account_id' => $fromLink->id,
        'to_custodian_account_id'   => $toLink->id,
        'amount'                    => 100,
        'asset_code'                => 'USD',
        'status'                    => 'completed',
        'transfer_type'             => 'external',
    ]);
}

function ghsaRrMockTransaction(): string
{
    $receipt = app(CustodianRegistry::class)->get('mock')->initiateTransfer(new TransferRequest(
        fromAccount: 'mock-account-1',
        toAccount: 'mock-account-2',
        assetCode: 'USD',
        amount: new Money(100),
    ));

    return $receipt->id;
}

describe('C07 custodian reads', function (): void {
    it('refuses custodian reads for an account id not linked to the caller', function (string $path): void {
        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson($path);

        $response->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
        expect($response->getContent())->not->toContain('Mock Business Account')->not->toContain('1000000');
    })->with([
        'account-info' => '/api/custodians/mock/account-info?account_id=mock-account-1',
        'balance'      => '/api/custodians/mock/balance?account_id=mock-account-1&asset_code=USD',
        'transactions' => '/api/custodians/mock/transactions?account_id=mock-account-1',
    ]);

    it('refuses a link that belongs to another user\'s account or is not active', function (): void {
        $victimAccount = Account::factory()->forUser(ghsaRrUser())->create();
        CustodianAccount::create([
            'account_uuid'         => $victimAccount->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
            'status'               => CustodianAccount::STATUS_ACTIVE,
        ]);
        $caller = ghsaRrUser();
        $callerAccount = Account::factory()->forUser($caller)->create();
        CustodianAccount::create([
            'account_uuid'         => $callerAccount->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-2',
            'status'               => CustodianAccount::STATUS_SUSPENDED,
        ]);

        Sanctum::actingAs($caller, ['read', 'write', 'delete']);

        $this->getJson('/api/custodians/mock/balance?account_id=mock-account-1&asset_code=USD')->assertStatus(403);
        $this->getJson('/api/custodians/mock/balance?account_id=mock-account-2&asset_code=USD')->assertStatus(403);
    });

    it('lets the owner of an active link read that custodian account', function (): void {
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();
        CustodianAccount::create([
            'account_uuid'         => $account->uuid,
            'custodian_name'       => 'mock',
            'custodian_account_id' => 'mock-account-1',
            'status'               => CustodianAccount::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson('/api/custodians/mock/balance?account_id=mock-account-1&asset_code=USD')
            ->assertOk()
            ->assertJsonPath('data.balance', 1000000);
        $this->getJson('/api/custodians/mock/account-info?account_id=mock-account-1')
            ->assertOk()
            ->assertJsonPath('data.name', 'Mock Business Account');
        $this->getJson('/api/custodians/mock/transactions?account_id=mock-account-1&limit=5')
            ->assertOk()
            ->assertJsonPath('meta.limit', 5);
    });

    it('lets an operator read any custodian account', function (): void {
        Sanctum::actingAs(ghsaRrUser('admin'), ['read', 'write', 'delete']);

        $this->getJson('/api/custodians/mock/balance?account_id=mock-account-1&asset_code=USD')
            ->assertOk()
            ->assertJsonPath('data.balance', 1000000);
    });

    it('does not echo connector error details', function (): void {
        Sanctum::actingAs(ghsaRrUser('admin'), ['read', 'write', 'delete']);

        $response = $this->getJson('/api/custodians/mock/account-info?account_id=upstream-secret-detail');

        $response->assertStatus(400)->assertJsonPath('error', 'Failed to retrieve account information');
        expect($response->getContent())->not->toContain('upstream-secret-detail');
    });

    it('refuses the status of a custodian transaction the caller is not a party to', function (): void {
        $transactionId = ghsaRrMockTransaction();
        $victimAccount = Account::factory()->forUser(ghsaRrUser())->create();
        ghsaRrCustodianTransfer($transactionId, $victimAccount->uuid, Account::factory()->create()->uuid);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("/api/custodians/mock/transactions/{$transactionId}");
        $response->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
        expect($response->json('data'))->toBeNull();
        $this->getJson('/api/custodians/mock/transactions/unknown-tx')->assertStatus(403);
    });

    it('lets a party to the transfer, or an operator, read the custodian transaction status', function (): void {
        $transactionId = ghsaRrMockTransaction();
        $owner = ghsaRrUser();
        $ownerAccount = Account::factory()->forUser($owner)->create();
        ghsaRrCustodianTransfer($transactionId, $ownerAccount->uuid, Account::factory()->create()->uuid);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson("/api/custodians/mock/transactions/{$transactionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId);

        Sanctum::actingAs(ghsaRrUser('admin'), ['read', 'write', 'delete']);
        $this->getJson("/api/custodians/mock/transactions/{$transactionId}")->assertOk();
    });
});

// ---------------------------------------------------------------------------
// C23: open banking consent, payment rail transaction, bank transfer status
// ---------------------------------------------------------------------------

function ghsaRrConsent(User $owner): Consent
{
    DB::table('tpp_registrations')->insertOrIgnore([
        'id'                 => (string) Str::uuid(),
        'tpp_id'             => 'tpp-ghsa',
        'name'               => 'GHSA TPP',
        'client_id'          => 'client-ghsa',
        'client_secret_hash' => hash('sha256', 'secret'),
        'redirect_uris'      => json_encode(['https://tpp.example/callback']),
        'roles'              => json_encode(['AISP']),
        'status'             => 'active',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    return Consent::create([
        'tpp_id'              => 'tpp-ghsa',
        'user_id'             => $owner->id,
        'status'              => ConsentStatus::AWAITING_AUTHORIZATION,
        'permissions'         => ['ReadAccountsDetail'],
        'account_ids'         => ['acct-secret-1'],
        'expires_at'          => now()->addDays(30),
        'frequency_per_day'   => 4,
        'recurring_indicator' => false,
    ]);
}

function ghsaRrRailTransaction(User $owner): PaymentRailTransaction
{
    return PaymentRailTransaction::create([
        'user_id'     => $owner->id,
        'rail'        => PaymentRail::ACH,
        'external_id' => 'ext-secret-1',
        'amount'      => '250.00',
        'currency'    => 'USD',
        'status'      => RailStatus::PENDING,
        'direction'   => 'outbound',
    ]);
}

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function ghsaRrGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
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

describe('C23 open banking consent', function (): void {
    it('returns null over GraphQL and 404 over REST for another user\'s consent', function (): void {
        $consent = ghsaRrConsent(ghsaRrUser());
        $caller = ghsaRrUser();

        $json = ghsaRrGraphql($this, $caller, 'query ($id: ID!) { openBankingConsent(id: $id) { id tpp_id user_id permissions } }', ['id' => $consent->id]);
        expect($json)->not->toHaveKey('errors');
        expect($json['data'])->toHaveKey('openBankingConsent');
        expect($json['data']['openBankingConsent'])->toBeNull();

        Sanctum::actingAs($caller, ['read', 'write', 'delete']);
        $response = $this->getJson("/api/v1/open-banking/consents/{$consent->id}");
        $response->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        expect($response->getContent())->not->toContain('acct-secret-1');
    });

    it('still returns the owner\'s consent over GraphQL and REST', function (): void {
        $owner = ghsaRrUser();
        $consent = ghsaRrConsent($owner);

        $json = ghsaRrGraphql($this, $owner, 'query ($id: ID!) { openBankingConsent(id: $id) { id tpp_id user_id } }', ['id' => $consent->id]);
        expect($json['data']['openBankingConsent']['id'] ?? null)->toBe($consent->id);
        expect($json['data']['openBankingConsent']['tpp_id'] ?? null)->toBe('tpp-ghsa');

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson("/api/v1/open-banking/consents/{$consent->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $consent->id)
            ->assertJsonPath('data.account_ids.0', 'acct-secret-1');
    });
});

describe('C23 payment rail transaction', function (): void {
    it('returns null over GraphQL and 404 over REST for another user\'s rail transaction', function (): void {
        $transaction = ghsaRrRailTransaction(ghsaRrUser());
        $caller = ghsaRrUser();

        $json = ghsaRrGraphql($this, $caller, 'query ($id: ID!) { paymentRailsTransaction(id: $id) { rail amount external_id } }', ['id' => $transaction->id]);
        expect($json)->not->toHaveKey('errors');
        expect($json['data'])->toHaveKey('paymentRailsTransaction');
        expect($json['data']['paymentRailsTransaction'])->toBeNull();

        Sanctum::actingAs($caller, ['read', 'write', 'delete']);
        $response = $this->getJson("/api/v1/payment-rails/transactions/{$transaction->id}");
        $response->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        expect($response->getContent())->not->toContain('ext-secret-1');
    });

    it('still returns the owner\'s rail transaction over GraphQL and REST', function (): void {
        $owner = ghsaRrUser();
        $transaction = ghsaRrRailTransaction($owner);

        $json = ghsaRrGraphql($this, $owner, 'query ($id: ID!) { paymentRailsTransaction(id: $id) { rail amount external_id } }', ['id' => $transaction->id]);
        expect($json['data']['paymentRailsTransaction']['external_id'] ?? null)->toBe('ext-secret-1');

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson("/api/v1/payment-rails/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.transaction_id', $transaction->id)
            ->assertJsonPath('data.amount', '250.00');
    });
});

function ghsaRrBankTransfer(User $owner): string
{
    // bank_transfers.id is a uuid column (36 characters on MySQL).
    $id = Str::uuid()->toString();
    DB::table('bank_transfers')->insert([
        'id'              => $id,
        'user_uuid'       => $owner->uuid,
        'from_bank_code'  => 'PAYSERA',
        'from_account_id' => 'acc-1',
        'to_bank_code'    => 'REVOLUT',
        'to_account_id'   => 'acc-2',
        'amount'          => 321.5,
        'currency'        => 'EUR',
        'type'            => 'SEPA',
        'status'          => 'initiated',
        'metadata'        => json_encode(['reference' => 'REF-SECRET', 'status_history' => []]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    return $id;
}

describe('C23 bank transfer status', function (): void {
    it('answers 404 for another user\'s bank transfer', function (): void {
        $id = ghsaRrBankTransfer(ghsaRrUser());

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("/api/v2/banks/transfer/{$id}/status");

        $response->assertNotFound()->assertJsonPath('error', 'Not found.');
        expect($response->getContent())->not->toContain('REF-SECRET');
    });

    it('still returns the status to the owner and to an operator', function (): void {
        $owner = ghsaRrUser();
        $id = ghsaRrBankTransfer($owner);

        foreach ([$owner, ghsaRrUser('admin')] as $reader) {
            Sanctum::actingAs($reader, ['read', 'write', 'delete']);

            $this->getJson("/api/v2/banks/transfer/{$id}/status")
                ->assertOk()
                ->assertJsonPath('data.transfer_id', $id)
                ->assertJsonPath('data.reference', 'REF-SECRET');
        }
    });
});

// ---------------------------------------------------------------------------
// C30: public BaaS onboarding status / documents
// ---------------------------------------------------------------------------

function ghsaRrApplication(?string $token = null): FinancialInstitutionApplication
{
    $application = FinancialInstitutionApplication::factory()->create(['status' => 'pending']);

    if ($token !== null) {
        $application->forceFill(['access_token_hash' => hash('sha256', $token)])->save();
    }

    return $application;
}

describe('C30 BaaS onboarding application access', function (): void {
    it('answers 404 for the status of an application without its access token', function (?string $header): void {
        $application = ghsaRrApplication('the-right-token-0123456789abcdefghijklmn');

        $headers = $header === null ? [] : ['X-Application-Token' => $header];
        $response = $this->getJson("/api/v2/financial-institutions/application/{$application->application_number}/status", $headers);

        $response->assertNotFound()->assertJsonPath('error', 'Application not found');
        expect($response->getContent())->not->toContain($application->institution_name);
    })->with(['missing token' => [null], 'wrong token' => ['wrong-token']]);

    it('answers 404 for an application created before access tokens existed', function (): void {
        $application = ghsaRrApplication();

        $this->getJson(
            "/api/v2/financial-institutions/application/{$application->application_number}/status",
            ['X-Application-Token' => '']
        )->assertNotFound();
    });

    it('refuses a document upload without the access token and changes nothing', function (): void {
        Storage::fake('private');
        $application = ghsaRrApplication('the-right-token-0123456789abcdefghijklmn');
        $before = $application->fresh()?->submitted_documents;

        $response = $this->post(
            "/api/v2/financial-institutions/application/{$application->application_number}/documents",
            ['document_type' => 'regulatory_license', 'document' => UploadedFile::fake()->create('forged.pdf', 10)],
            ['Accept'        => 'application/json', 'X-Application-Token' => 'wrong-token'],
        );

        $response->assertNotFound();
        expect($application->fresh()?->submitted_documents)->toBe($before);
        expect(Storage::disk('private')->allFiles())->toBe([]);
    });

    it('returns an access token at apply time that unlocks status and documents', function (): void {
        Storage::fake('private');

        $response = $this->postJson('/api/v2/financial-institutions/apply', ghsaRrApplicationPayload());
        $response->assertCreated();
        $token = (string) $response->json('data.access_token');
        $number = (string) $response->json('data.application_number');
        expect(strlen($token))->toBeGreaterThanOrEqual(40);

        $stored = FinancialInstitutionApplication::where('application_number', $number)->firstOrFail();
        expect($stored->access_token_hash)->toBe(hash('sha256', $token));
        expect($stored->toArray())->not->toHaveKey('access_token_hash');

        $this->getJson("/api/v2/financial-institutions/application/{$number}/status", ['X-Application-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.application_number', $number);

        $this->post(
            "/api/v2/financial-institutions/application/{$number}/documents",
            ['document_type' => 'regulatory_license', 'document' => UploadedFile::fake()->create('license.pdf', 10)],
            ['Accept'        => 'application/json', 'X-Application-Token' => $token],
        )->assertOk()->assertJsonPath('data.uploaded', true);
    });

    it('closes the onboarding endpoints while the FinancialInstitution module is disabled', function (): void {
        config(['modules.disabled' => ['FinancialInstitution']]);
        Cache::forget('domain_manager_disabled');
        $application = ghsaRrApplication('the-right-token-0123456789abcdefghijklmn');

        $this->postJson('/api/v2/financial-institutions/apply', ghsaRrApplicationPayload())->assertNotFound();
        $this->getJson(
            "/api/v2/financial-institutions/application/{$application->application_number}/status",
            ['X-Application-Token' => 'the-right-token-0123456789abcdefghijklmn']
        )->assertNotFound();
        expect(FinancialInstitutionApplication::count())->toBe(1);
    });
});

/**
 * @return array<string, mixed>
 */
function ghsaRrApplicationPayload(): array
{
    return [
        'institution_name'           => 'Ghsa Test Bank',
        'legal_name'                 => 'Ghsa Test Bank Ltd',
        'registration_number'        => 'REG-123',
        'tax_id'                     => 'TAX-123',
        'country'                    => 'GB',
        'institution_type'           => 'bank',
        'years_in_operation'         => 5,
        'contact_name'               => 'Jane Doe',
        'contact_email'              => 'jane@example.com',
        'contact_phone'              => '+440000000',
        'contact_position'           => 'CTO',
        'headquarters_address'       => '1 Test Street',
        'headquarters_city'          => 'London',
        'headquarters_postal_code'   => 'EC1A 1AA',
        'headquarters_country'       => 'GB',
        'business_description'       => str_repeat('A licensed bank applying for API access. ', 4),
        'target_markets'             => ['GB'],
        'product_offerings'          => ['payments'],
        'required_currencies'        => ['GBP'],
        'integration_requirements'   => ['api'],
        'has_aml_program'            => true,
        'has_kyc_procedures'         => true,
        'has_data_protection_policy' => true,
        'is_pci_compliant'           => true,
        'is_gdpr_compliant'          => true,
    ];
}

// ---------------------------------------------------------------------------
// C37: TrustCert present / certificate / export-pdf
// ---------------------------------------------------------------------------

describe('C37 TrustCert holder checks', function (): void {
    it('refuses to present another user\'s certificate or an unknown id', function (): void {
        $victim = ghsaRrUser();
        $certificate = ghsaRrIssueCertificate("user:{$victim->uuid}");

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        foreach ([$certificate->certificateId, 'cert_' . Str::uuid()->toString()] as $certId) {
            $response = $this->postJson("/api/v1/trustcert/{$certId}/present", ['requested_claims' => []]);

            $response->assertNotFound()->assertJsonPath('error.code', 'CERTIFICATE_NOT_FOUND');
            expect($response->json('data'))->toBeNull();
        }
        expect($response->getContent())->not->toContain((string) $victim->uuid);
    });

    it('lets the holder present their certificate with its real claims, and verify honours revocation', function (): void {
        $holder = ghsaRrUser();
        $certificate = ghsaRrIssueCertificate("user:{$holder->uuid}");

        Sanctum::actingAs($holder, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/v1/trustcert/{$certificate->certificateId}/present", [
            'requested_claims' => ['certificate_id', 'subject_id'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.claims.certificate_id', $certificate->certificateId)
            ->assertJsonPath('data.claims.subject_id', "user:{$holder->uuid}");
        $token = (string) $response->json('data.presentation_token');

        $this->getJson("/api/v1/trustcert/verify/{$token}")->assertOk()->assertJsonPath('data.valid', true);

        app(CertificateAuthorityService::class)->revokeCertificate($certificate->certificateId, 'test');

        $this->getJson("/api/v1/trustcert/verify/{$token}")->assertJsonPath('data.valid', false);
        $this->postJson("/api/v1/trustcert/{$certificate->certificateId}/present", [])->assertStatus(400);
    });

    it('lets an employer present their agent\'s certificate', function (): void {
        $employer = ghsaRrUser();
        $certificate = ghsaRrIssueCertificate("agent:virtuals_abc:employer:{$employer->id}");

        Sanctum::actingAs($employer, ['read', 'write', 'delete']);

        $this->postJson("/api/v1/trustcert/{$certificate->certificateId}/present", [])->assertOk();
    });

    it('answers 404 for another user\'s certificate details and PDF export, and writes no file', function (): void {
        Storage::fake('public');
        $certificate = ghsaRrIssueCertificate('user:' . ghsaRrUser()->uuid);

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $this->getJson("/api/v1/trustcert/{$certificate->certificateId}/certificate")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CERTIFICATE_NOT_FOUND');
        $this->postJson("/api/v1/trustcert/{$certificate->certificateId}/export-pdf")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'EXPORT_FAILED');
        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('still returns certificate details and the PDF export to the holder', function (): void {
        Storage::fake('public');
        Storage::fake('local');
        $holder = ghsaRrUser();
        $certificate = ghsaRrIssueCertificate("user:{$holder->uuid}");

        Sanctum::actingAs($holder, ['read', 'write', 'delete']);

        $this->getJson("/api/v1/trustcert/{$certificate->certificateId}/certificate")
            ->assertOk()
            ->assertJsonPath('data.certId', $certificate->certificateId);
        $this->postJson("/api/v1/trustcert/{$certificate->certificateId}/export-pdf")
            ->assertOk()
            ->assertJsonPath('success', true);
        // Since GHSA-rrfq the PDF is kept on the private disk.
        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
    });
});

// ---------------------------------------------------------------------------
// Review follow-ups: R1 reversal status, R2 owner lookups with the
// BelongsToTeam scope on, R3 exact asset totals.
// ---------------------------------------------------------------------------

/**
 * Run $callback with the BelongsToTeam global scope active, as it is for a
 * production request (the trait skips the scope only while running the
 * test suite).
 *
 * @param  Closure(): mixed  $callback
 */
function ghsaRrWithTeamScope(Closure $callback): mixed
{
    $previous = app()->environment();
    app()->instance('env', 'local');

    try {
        return $callback();
    } finally {
        app()->instance('env', $previous);
    }
}

function ghsaRrTeamUser(?string $role = null): User
{
    $user = User::factory()->withPersonalTeam()->create();

    if ($role !== null) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    return $user;
}

function ghsaRrReversal(string $accountUuid): TransactionProjection
{
    return TransactionProjection::factory()->create([
        'account_uuid' => $accountUuid,
        'subtype'      => 'reversal',
        'status'       => 'failed',
        'metadata'     => ['reversal_reason' => 'chargeback', 'error_message' => 'G385W-REVERSAL-SECRET'],
    ]);
}

describe('R1 reversal status', function (): void {
    it('refuses the status of a reversal whose account no longer exists', function (): void {
        $reversal = ghsaRrReversal((string) Str::uuid());

        Sanctum::actingAs(ghsaRrUser(), ['read', 'write', 'delete']);

        $response = $this->getJson("/api/transactions/reversals/rev_{$reversal->uuid}/status");

        $response->assertForbidden();
        expect($response->getContent())->not->toContain('G385W-REVERSAL-SECRET');
    });

    it('refuses another team\'s reversal status with the team scope on', function (): void {
        $victim = ghsaRrTeamUser();
        $account = Account::factory()->forUser($victim)->create(['team_id' => $victim->personalTeam()->id]);
        $reversal = ghsaRrReversal($account->uuid);
        $attacker = ghsaRrTeamUser();

        $response = ghsaRrWithTeamScope(function () use ($attacker, $reversal) {
            Sanctum::actingAs($attacker, ['read', 'write', 'delete']);

            return $this->getJson("/api/transactions/reversals/rev_{$reversal->uuid}/status");
        });

        $response->assertForbidden();
        expect($response->getContent())->not->toContain('G385W-REVERSAL-SECRET');
    });

    it('returns the reversal status to the owner from any team, and to an admin', function (): void {
        $owner = ghsaRrTeamUser();
        $account = Account::factory()->forUser($owner)->create(['team_id' => null]);
        $reversal = ghsaRrReversal($account->uuid);

        foreach ([$owner, ghsaRrTeamUser('admin')] as $caller) {
            $response = ghsaRrWithTeamScope(function () use ($caller, $reversal) {
                Sanctum::actingAs($caller, ['read', 'write', 'delete']);

                return $this->getJson("/api/transactions/reversals/rev_{$reversal->uuid}/status");
            });

            $response->assertOk()
                ->assertJsonPath('data.status', 'failed')
                ->assertJsonPath('data.error_message', 'G385W-REVERSAL-SECRET');
        }
    });
});

describe('R2 owner lookups with the team scope on', function (): void {
    it('lists the owner\'s accounts that have no team or another team in GET /api/balances', function (): void {
        ghsaRrUsd();
        $owner = ghsaRrTeamUser();
        $noTeam = Account::factory()->forUser($owner)->create(['team_id' => null]);
        $otherTeam = Account::factory()->forUser($owner)->create(['team_id' => ghsaRrTeamUser()->personalTeam()->id]);
        AccountBalance::create(['account_uuid' => $noTeam->uuid, 'asset_code' => 'USD', 'balance' => 1000]);
        AccountBalance::create(['account_uuid' => $otherTeam->uuid, 'asset_code' => 'USD', 'balance' => 500]);
        $victim = ghsaRrTeamUser();
        $theirs = Account::factory()->forUser($victim)->create(['team_id' => $owner->personalTeam()->id]);
        AccountBalance::create(['account_uuid' => $theirs->uuid, 'asset_code' => 'USD', 'balance' => 900000]);

        $response = ghsaRrWithTeamScope(function () use ($owner) {
            Sanctum::actingAs($owner, ['read', 'write', 'delete']);

            return $this->getJson('/api/balances');
        });

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.account_uuid', $noTeam->uuid)
            ->assertJsonPath('data.0.account.user_uuid', (string) $owner->uuid)
            ->assertJsonPath('data.1.account_uuid', $otherTeam->uuid)
            ->assertJsonPath('data.1.account.user_uuid', (string) $owner->uuid)
            ->assertJsonPath('meta.total_accounts', 2)
            ->assertJsonPath('meta.asset_totals.USD', '15.00');
        expect($response->getContent())->not->toContain($theirs->uuid)->not->toContain('9000.00');

        $filtered = ghsaRrWithTeamScope(function () use ($owner) {
            Sanctum::actingAs($owner, ['read', 'write', 'delete']);

            return $this->getJson("/api/balances?user_uuid={$owner->uuid}");
        });

        $filtered->assertOk()->assertJsonCount(2, 'data');
    });

    it('returns the balances of an account outside the owner\'s current team, and refuses other users', function (): void {
        ghsaRrUsd();
        $owner = ghsaRrTeamUser();
        $account = Account::factory()->forUser($owner)->create(['team_id' => null]);
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 4321]);
        $attacker = ghsaRrTeamUser();

        [$own, $foreign] = ghsaRrWithTeamScope(function () use ($owner, $attacker, $account): array {
            Sanctum::actingAs($owner, ['read', 'write', 'delete']);
            $own = $this->getJson("/api/accounts/{$account->uuid}/balances");
            Sanctum::actingAs($attacker, ['read', 'write', 'delete']);

            return [$own, $this->getJson("/api/accounts/{$account->uuid}/balances")];
        });

        $own->assertOk()
            ->assertJsonPath('data.account_uuid', $account->uuid)
            ->assertJsonPath('data.balances.0.balance', 4321);
        $foreign->assertForbidden()->assertJsonPath('error', 'FORBIDDEN');
        expect($foreign->getContent())->not->toContain('4321');
    });

    it('lets the owner of an account outside their current team see their transfer', function (): void {
        $sender = ghsaRrTeamUser();
        $from = Account::factory()->forUser($sender)->create(['team_id' => null]);
        $to = Account::factory()->forUser(ghsaRrTeamUser())->create();
        $transferUuid = ghsaRrMoneyTransferred($from->uuid, $to->uuid, 3141);

        $response = ghsaRrWithTeamScope(function () use ($sender, $transferUuid) {
            Sanctum::actingAs($sender, ['read', 'write', 'delete']);

            return $this->getJson("/api/transfers/{$transferUuid}");
        });

        $response->assertOk()
            ->assertJsonPath('data.from_account_uuid', $from->uuid)
            ->assertJsonPath('data.amount', 3141);
    });

    it('finds own accounts in any team and never another user\'s account', function (): void {
        $owner = ghsaRrTeamUser();
        $mine = Account::factory()->forUser($owner)->create(['team_id' => null]);
        $theirs = Account::factory()->forUser(ghsaRrTeamUser())->create(['team_id' => $owner->personalTeam()->id]);

        [$found, $foreign] = ghsaRrWithTeamScope(function () use ($owner, $mine, $theirs): array {
            Sanctum::actingAs($owner, ['read', 'write', 'delete']);

            return [Account::findOwnedBy($mine->uuid, $owner), Account::findOwnedBy($theirs->uuid, $owner)];
        });

        expect($found?->uuid)->toBe($mine->uuid)
            ->and($foreign)->toBeNull();
    });

    it('still hides another user\'s transfer and balances with the team scope on', function (): void {
        ghsaRrUsd();
        $attacker = ghsaRrTeamUser();
        $victim = ghsaRrTeamUser();
        $from = Account::factory()->forUser($victim)->create(['team_id' => $attacker->personalTeam()->id]);
        $to = Account::factory()->forUser(ghsaRrTeamUser())->create(['team_id' => null]);
        AccountBalance::create(['account_uuid' => $from->uuid, 'asset_code' => 'USD', 'balance' => 900000]);
        $transferUuid = ghsaRrMoneyTransferred($from->uuid, $to->uuid, 2718);

        [$transfer, $balances] = ghsaRrWithTeamScope(function () use ($attacker, $transferUuid): array {
            Sanctum::actingAs($attacker, ['read', 'write', 'delete']);

            return [$this->getJson("/api/transfers/{$transferUuid}"), $this->getJson('/api/balances')];
        });

        $transfer->assertNotFound();
        $balances->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total_accounts', 0);
        expect($balances->getContent())->not->toContain($from->uuid);
    });
});

describe('R3 exact balance amounts', function (): void {
    it('formats balances and asset totals without float rounding', function (): void {
        ghsaRrUsd();
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();
        // 2^53 + 1 minor units: a float cannot hold this value exactly.
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 9007199254740993]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson('/api/balances')
            ->assertOk()
            ->assertJsonPath('data.0.formatted', '90071992547409.93 USD')
            ->assertJsonPath('meta.asset_totals.USD', '90071992547409.93');
    });

    it('keeps the usual formatting for ordinary and negative amounts', function (): void {
        ghsaRrUsd();
        $owner = ghsaRrUser();
        $account = Account::factory()->forUser($owner)->create();
        $other = Account::factory()->forUser($owner)->create();
        AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 5]);
        AccountBalance::create(['account_uuid' => $other->uuid, 'asset_code' => 'USD', 'balance' => -1050]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);

        $this->getJson('/api/balances')
            ->assertOk()
            ->assertJsonPath('data.0.formatted', '0.05 USD')
            ->assertJsonPath('data.1.formatted', '-10.50 USD')
            ->assertJsonPath('meta.asset_totals.USD', '-10.45');
    });
});
