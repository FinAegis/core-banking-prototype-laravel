<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianTransfer;
use App\Domain\Custodian\Models\CustodianWebhook;
use App\Domain\Custodian\Services\CustodianAccountService;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\Fraud\Models\FraudCase;
use App\Domain\Fraud\Services\FraudCaseService;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Services\ChartOfAccountsService;
use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Wallet\Exceptions\AddressRegistrationRejectedException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| GHSA-385w-q6j4-5qw6, GraphQL cluster: staff-only GraphQL reads (regulatory
| reports, custodian webhooks, FI partners, general ledger), MODULES_DISABLED
| on every domain's GraphQL queries and mutations, createSmartAccount owner
| rule, per-user card creation limit over GraphQL, staff paths under the
| BelongsToTeam scope, active custodian links for GraphQL transfers, and the
| denial tests for smartAccountNonce and the BIAN payment reads.
*/

beforeEach(function (): void {
    Cache::forget('domain_manager_disabled');
    config([
        'modules.disabled'              => [],
        'ledger.direct_funding_enabled' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $variables
 * @return array<string, mixed>
 */
function g385wGraphql(PHPUnit\Framework\TestCase $test, User $user, string $query, array $variables = []): array
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
function g385wError(array $json): ?string
{
    /** @var array<int, array{message?: string}> $errors */
    $errors = $json['errors'] ?? [];

    return $errors[0]['message'] ?? null;
}

function g385wStaff(string $role, bool $withTeam = false): User
{
    Role::findOrCreate($role, 'web');
    $factory = User::factory();
    $user = ($withTeam ? $factory->withPersonalTeam() : $factory)->create();
    $user->assignRole($role);

    return $user;
}

function g385wCustomer(bool $withTeam = false): User
{
    $factory = User::factory();

    return ($withTeam ? $factory->withPersonalTeam() : $factory)->create();
}

/**
 * Bind a Mockery double of the given class in the container.
 *
 * @param  class-string  $abstract
 * @param  Closure(MockInterface): void  $expectations
 */
function g385wMock(string $abstract, Closure $expectations): MockInterface
{
    $mock = Mockery::mock($abstract);
    $expectations($mock);
    app()->instance($abstract, $mock);

    return $mock;
}

/**
 * Run $callback with the BelongsToTeam global scope active, as it is for a
 * production request (the trait skips the scope only while running the
 * test suite).
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function g385wWithTeamScope(Closure $callback): mixed
{
    $previous = app()->environment();
    app()->instance('env', 'local');

    try {
        return $callback();
    } finally {
        app()->instance('env', $previous);
    }
}

/**
 * Query and Mutation type blocks of a GraphQL SDL file, plain (`type Query`)
 * or extensions, with the directives on one line or several.
 *
 * Returns one [type, header] pair per block, where header is everything
 * between the type name and the opening brace (the block's directives),
 * and the number of Query / Mutation type declarations in the file, so a
 * block the pattern cannot read fails the check instead of being skipped.
 *
 * @return array{0: list<array{0: string, 1: string}>, 1: int}
 */
function g385wSchemaBlocks(string $sdl): array
{
    // Descriptions and comments are not declarations.
    $sdl = (string) preg_replace(['/"""[\s\S]*?"""/', '/#[^\n]*/'], '', $sdl);

    preg_match_all(
        '/^[ \t]*(?:extend[ \t]+)?type[ \t]+(Query|Mutation)\b((?:(?!\b(?:type|input|enum|interface|union|scalar|extend)\b)[^{}])*)\{/m',
        $sdl,
        $matches,
        PREG_SET_ORDER
    );
    $blocks = array_map(static fn (array $match): array => [$match[1], $match[2]], $matches);

    return [$blocks, (int) preg_match_all('/(?<![\w"])type\s+(?:Query|Mutation)\b/', $sdl)];
}

function g385wDeclaresModule(string $header, string $domain): bool
{
    return preg_match('/@module\(\s*name:\s*"' . preg_quote($domain, '/') . '"\s*\)/', $header) === 1;
}

function g385wRegulatoryReport(string $type = 'SAR'): RegulatoryReport
{
    /** @var RegulatoryReport $report */
    $report = RegulatoryReport::query()->create([
        'report_type'            => $type,
        'jurisdiction'           => 'US',
        'status'                 => 'draft',
        'priority'               => 4,
        'is_mandatory'           => true,
        'file_format'            => 'json',
        'generated_at'           => now(),
        'reporting_period_start' => '2025-01-01',
        'reporting_period_end'   => '2025-03-31',
        'due_date'               => now()->addDays(30),
    ]);

    return $report;
}

function g385wPartner(string $name = 'Partner Bank'): FinancialInstitutionPartner
{
    $suffix = Str::random(6);
    $application = FinancialInstitutionApplication::query()->create([
        'application_number'       => 'FIA-2025-' . $suffix,
        'institution_name'         => $name,
        'legal_name'               => "{$name} Ltd",
        'registration_number'      => 'REG-' . $suffix,
        'tax_id'                   => 'TAX-' . $suffix,
        'country'                  => 'GB',
        'institution_type'         => 'fintech',
        'years_in_operation'       => 5,
        'contact_name'             => 'Contact',
        'contact_email'            => "contact-{$suffix}@example.com",
        'contact_phone'            => '+44123456789',
        'contact_position'         => 'CEO',
        'headquarters_address'     => '1 London Rd',
        'headquarters_city'        => 'London',
        'headquarters_postal_code' => 'EC1A 1BB',
        'headquarters_country'     => 'GB',
        'business_description'     => 'Fintech',
        'target_markets'           => ['GB'],
        'product_offerings'        => ['lending'],
        'required_currencies'      => ['GBP'],
        'integration_requirements' => ['api'],
        'status'                   => 'approved',
    ]);

    /** @var FinancialInstitutionPartner $partner */
    $partner = FinancialInstitutionPartner::query()->create([
        'application_id'     => $application->id,
        'institution_name'   => $name,
        'legal_name'         => "{$name} Ltd",
        'institution_type'   => 'fintech',
        'country'            => 'GB',
        'status'             => 'active',
        'tier'               => 'enterprise',
        'fee_structure'      => ['type' => 'flat', 'amount' => '0.5'],
        'risk_rating'        => 'high',
        'risk_score'         => 80.00,
        'primary_contact'    => ['name' => 'Contact', 'email' => "contact-{$suffix}@bank.example"],
        'sandbox_enabled'    => true,
        'production_enabled' => false,
    ]);

    return $partner;
}

function g385wSmartAccount(User $owner, string $ownerAddress, string $network = 'polygon'): SmartAccount
{
    /** @var SmartAccount $account */
    $account = SmartAccount::query()->create([
        'user_id'         => $owner->id,
        'owner_address'   => strtolower($ownerAddress),
        'account_address' => '0x' . str_repeat('a', 40),
        'network'         => $network,
        'deployed'        => false,
        'nonce'           => 7,
        'pending_ops'     => 1,
    ]);

    return $account;
}

function g385wDemoCard(string $token): VirtualCard
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

/**
 * Store a MoneyTransferred event between two accounts, as the ledger does.
 */
function g385wTransferEvent(Account $from, Account $to): void
{
    DB::table('stored_events')->insert([
        'aggregate_uuid'    => $from->uuid,
        'aggregate_version' => 1,
        'event_version'     => 1,
        'event_class'       => 'App\\Domain\\Account\\Events\\MoneyTransferred',
        'event_properties'  => json_encode([
            'from_uuid' => $from->uuid,
            'to_uuid'   => $to->uuid,
            'money'     => ['amount' => 2500],
            'hash'      => ['hash' => 'g385w-hash'],
        ]),
        'meta_data'  => '{}',
        'created_at' => now(),
    ]);
}

function g385wMoneyEventCount(): int
{
    return DB::table('stored_events')->pluck('event_class')
        ->filter(fn (mixed $class): bool => str_starts_with((string) $class, 'App\\Domain\\Account\\Events\\'))
        ->count();
}

describe('C21 regulatory reports are compliance-only on GraphQL', function (): void {
    it('hides regulatory reports from callers without a compliance role', function (): void {
        $report = g385wRegulatoryReport('SAR');
        $customer = g385wCustomer();

        $json = g385wGraphql($this, $customer, 'query ($id: ID!) { regulatoryReport(id: $id) { id report_type submitted_by } }', ['id' => (string) $report->id]);
        expect($json['data']['regulatoryReport'])->toBeNull();

        $json = g385wGraphql($this, $customer, '{ regulatoryReports(first: 20) { data { id report_type } paginatorInfo { total } } }');
        expect($json['data']['regulatoryReports']['data'])->toBe([])
            ->and($json['data']['regulatoryReports']['paginatorInfo']['total'])->toBe(0);

        expect(RegulatoryReport::query()->count())->toBe(1)
            ->and($report->fresh()?->status)->toBe('draft');
    });

    it('returns regulatory reports to compliance staff', function (): void {
        $report = g385wRegulatoryReport('SAR');
        $officer = g385wStaff('compliance_officer');

        $json = g385wGraphql($this, $officer, 'query ($id: ID!) { regulatoryReport(id: $id) { id report_type } }', ['id' => (string) $report->id]);
        expect($json['data']['regulatoryReport']['report_type'] ?? null)->toBe('SAR');

        $json = g385wGraphql($this, $officer, '{ regulatoryReports(first: 20) { data { report_type } paginatorInfo { total } } }');
        expect($json['data']['regulatoryReports']['paginatorInfo']['total'])->toBe(1);
    });

    it('limits the RegTech report status endpoint to compliance staff', function (): void {
        Sanctum::actingAs(g385wCustomer(), ['read', 'write', 'delete']);
        $this->getJson('/api/regtech/reports/RPT-US-2025-abc/status')->assertForbidden();

        Sanctum::actingAs(g385wStaff('compliance_officer'), ['read', 'write', 'delete']);
        $this->getJson('/api/regtech/reports/RPT-US-2025-abc/status')->assertOk();
    });
});

describe('C32 custodian webhooks and FI partners are operator-only on GraphQL', function (): void {
    it('hides custodian webhook records from non-operators', function (): void {
        CustodianWebhook::query()->create([
            'uuid'           => (string) Str::uuid(),
            'custodian_name' => 'paysera',
            'event_type'     => 'transaction.completed',
            'event_id'       => 'evt-g385w-1',
            'payload'        => ['id' => 'evt-g385w-1'],
            'status'         => 'failed',
            'attempts'       => 3,
            'error_message'  => 'upstream detail',
        ]);

        $json = g385wGraphql($this, g385wCustomer(), '{ custodianWebhooks(first: 20) { data { event_id error_message } } }');
        expect($json['data']['custodianWebhooks']['data'])->toBe([]);

        $json = g385wGraphql($this, g385wStaff('compliance_officer'), '{ custodianWebhooks(first: 20) { data { event_id } } }');
        expect($json['data']['custodianWebhooks']['data'])->toBe([]);
        expect(CustodianWebhook::query()->where('event_id', 'evt-g385w-1')->value('status'))->toBe('failed');
    });

    it('returns custodian webhook records to operators', function (): void {
        CustodianWebhook::query()->create([
            'uuid'           => (string) Str::uuid(),
            'custodian_name' => 'paysera',
            'event_type'     => 'transaction.completed',
            'event_id'       => 'evt-g385w-2',
            'payload'        => ['id' => 'evt-g385w-2'],
            'status'         => 'processed',
            'attempts'       => 1,
        ]);

        $json = g385wGraphql($this, g385wStaff('bank_admin'), '{ custodianWebhooks(first: 20) { data { event_id } } }');
        expect(array_column($json['data']['custodianWebhooks']['data'], 'event_id'))->toBe(['evt-g385w-2']);
    });

    it('hides financial-institution partners from non-operators', function (): void {
        $partner = g385wPartner('Hidden Bank');
        $customer = g385wCustomer();

        $json = g385wGraphql($this, $customer, 'query ($id: ID!) { partner(id: $id) { institution_name risk_rating } }', ['id' => (string) $partner->id]);
        expect($json['data']['partner'])->toBeNull();

        $json = g385wGraphql($this, $customer, '{ partners(first: 20) { data { institution_name } paginatorInfo { total } } }');
        expect($json['data']['partners']['data'])->toBe([])
            ->and($json['data']['partners']['paginatorInfo']['total'])->toBe(0);
        expect($partner->fresh()?->risk_rating)->toBe('high');
    });

    it('returns financial-institution partners to operators', function (): void {
        $partner = g385wPartner('Visible Bank');
        $admin = g385wStaff('admin');

        $json = g385wGraphql($this, $admin, 'query ($id: ID!) { partner(id: $id) { institution_name } }', ['id' => (string) $partner->id]);
        expect($json['data']['partner']['institution_name'] ?? null)->toBe('Visible Bank');

        $json = g385wGraphql($this, $admin, '{ partners(first: 20) { data { institution_name } } }');
        expect(array_column($json['data']['partners']['data'], 'institution_name'))->toBe(['Visible Bank']);
    });
});

describe('C33 general-ledger reads are operator-only', function (): void {
    beforeEach(function (): void {
        app(ChartOfAccountsService::class)->createAccount(code: '1000', name: 'Cash', type: AccountType::ASSET);
    });

    it('refuses the REST ledger reads to non-operators', function (string $path): void {
        Sanctum::actingAs(g385wCustomer(), ['read', 'write', 'delete']);
        $this->getJson($path)->assertForbidden();
    })->with([
        '/api/v1/ledger/accounts',
        '/api/v1/ledger/accounts/1000/balance',
        '/api/v1/ledger/accounts/1000/history',
        '/api/v1/ledger/trial-balance',
        '/api/v1/ledger/reconciliation/wallet',
    ]);

    it('serves the REST ledger reads to operators', function (string $path): void {
        Sanctum::actingAs(g385wStaff('bank_admin'), ['read', 'write', 'delete']);
        $this->getJson($path)->assertOk();
    })->with([
        '/api/v1/ledger/accounts',
        '/api/v1/ledger/accounts/1000/balance',
        '/api/v1/ledger/accounts/1000/history',
        '/api/v1/ledger/trial-balance',
        '/api/v1/ledger/reconciliation/wallet',
    ]);

    it('refuses the GraphQL ledger queries to non-operators and serves them to operators', function (string $query, string $field): void {
        $json = g385wGraphql($this, g385wCustomer(), $query);
        expect(g385wError($json))->toBe('You are not authorized to view the general ledger.')
            ->and($json['data'] ?? null)->toBeNull();

        $json = g385wGraphql($this, g385wStaff('admin'), $query);
        expect($json)->not->toHaveKey('errors')
            ->and($json['data'][$field])->not->toBeNull();
    })->with([
        'ledgerAccounts'     => ['{ ledgerAccounts { code name } }', 'ledgerAccounts'],
        'ledgerBalance'      => ['{ ledgerBalance(account_code: "1000") { amount currency } }', 'ledgerBalance'],
        'ledgerTrialBalance' => ['{ ledgerTrialBalance { account_code balance } }', 'ledgerTrialBalance'],
    ]);
});

describe('C22 / C31 MODULES_DISABLED covers every GraphQL query and mutation of a domain', function (): void {
    it('declares @module on every Query and Mutation block of a domain schema', function (): void {
        $domains = [];
        foreach (File::directories(base_path('app/Domain')) as $dir) {
            if (File::exists("{$dir}/module.json")) {
                $domains[strtolower(basename($dir))] = basename($dir);
            }
        }

        $checked = [];
        foreach (File::glob(base_path('graphql/*.graphql')) as $path) {
            $key = str_replace('-', '', basename($path, '.graphql'));
            if (! isset($domains[$key])) {
                continue;
            }
            $domain = $domains[$key];

            [$blocks, $declared] = g385wSchemaBlocks((string) file_get_contents($path));
            expect($blocks)->not->toBeEmpty(basename($path) . ' declares no Query or Mutation block')
                ->and(count($blocks))->toBe($declared, basename($path) . ' has a Query or Mutation block the check cannot read');

            foreach ($blocks as [$type, $header]) {
                expect(g385wDeclaresModule($header, $domain))
                    ->toBeTrue(basename($path) . " type {$type} has no @module(name: \"{$domain}\")");
                $checked[$domain][] = $type;
            }
        }

        // Every domain of the Zelta MODULES_DISABLED list that has a schema
        // (ISO8583 has none), plus the domains named in the advisory.
        $required = [
            'Banking', 'Basket', 'Cgo', 'CrossChain', 'DeFi', 'FinancialInstitution', 'Governance', 'ISO20022',
            'Interledger', 'Ledger', 'Lending', 'Microfinance', 'OpenBanking', 'PaymentRails', 'Stablecoin', 'Treasury',
            'RegTech', 'CardIssuance', 'Privacy', 'Relayer', 'Custodian', 'Exchange', 'Commerce', 'Regulatory',
            'AgentProtocol', 'X402', 'AI', 'Wallet', 'MobilePayment', 'Rewards', 'Compliance', 'Account',
        ];
        foreach ($required as $domain) {
            expect($checked)->toHaveKey($domain);
        }
        expect(count($checked))->toBeGreaterThanOrEqual(40);
    });

    it('reads multi-line and plain Query and Mutation blocks in the structural check', function (): void {
        $sdl = <<<'SDL'
            """
            Not a declaration: type Query { x: Int }
            """
            extend type Query
                @guard
            {
                a: Int
            }

            # type Mutation { commented: Int }
            type Mutation {
                b: Int
            }

            extend type Query @module(name: "Ledger") {
                c: Int
            }

            extend type Mutation
                @module(name: "Ledger")
                @guard(with: ["sanctum"])
            {
                d(input: QueryInput!): Int
            }
            SDL;

        [$blocks, $declared] = g385wSchemaBlocks($sdl);

        expect($declared)->toBe(4)
            ->and(array_column($blocks, 0))->toBe(['Query', 'Mutation', 'Query', 'Mutation'])
            ->and(array_map(static fn (array $block): bool => g385wDeclaresModule($block[1], 'Ledger'), $blocks))
            ->toBe([false, false, true, true]);
    });

    it('answers "This operation is not available." for the queries and mutations of a disabled domain', function (string $module, string $query): void {
        $operator = g385wStaff('admin');

        config(['modules.disabled' => [$module]]);
        $json = g385wGraphql($this, $operator, $query);
        expect(g385wError($json))->toBe('This operation is not available.');

        config(['modules.disabled' => []]);
        expect(g385wError(g385wGraphql($this, $operator, $query)))->not->toBe('This operation is not available.');
    })->with([
        'Ledger ledgerTrialBalance'         => ['Ledger', '{ ledgerTrialBalance { account_code } }'],
        'Ledger ledgerAccounts'             => ['Ledger', '{ ledgerAccounts { code } }'],
        'FinancialInstitution partners'     => ['FinancialInstitution', '{ partners(first: 5) { data { id } } }'],
        'Microfinance mfiGroups'            => ['Microfinance', '{ mfiGroups(first: 5) { data { id } } }'],
        'Microfinance mfiProvisionSummary'  => ['Microfinance', '{ mfiProvisionSummary { total } }'],
        'Governance polls'                  => ['Governance', '{ polls(first: 5) { data { id } } }'],
        'Basket baskets'                    => ['Basket', '{ baskets(first: 5) { data { id } } }'],
        'Stablecoin stablecoinReserves'     => ['Stablecoin', '{ stablecoinReserves(first: 5) { data { id } } }'],
        'Banking bankAccounts'              => ['Banking', '{ bankAccounts(first: 5) { data { id } } }'],
        'Lending loanApplications'          => ['Lending', '{ loanApplications(first: 5) { data { id } } }'],
        'ISO20022 iso20022SupportedTypes'   => ['ISO20022', '{ iso20022SupportedTypes }'],
        'Interledger ilpSupportedAssets'    => ['Interledger', '{ ilpSupportedAssets { __typename } }'],
        'PaymentRails supportedRails'       => ['PaymentRails', '{ paymentRailsSupportedRails(country: "US") }'],
        'OpenBanking openBankingMyConsents' => ['OpenBanking', '{ openBankingMyConsents { __typename } }'],
        'Regulatory regulatoryReports'      => ['Regulatory', '{ regulatoryReports(first: 5) { data { id } } }'],
        'Custodian custodianWebhooks'       => ['Custodian', '{ custodianWebhooks(first: 5) { data { id } } }'],
        'RegTech runHealthChecks'           => ['RegTech', 'mutation { runHealthChecks }'],
        'CardIssuance provisionCard'        => ['CardIssuance', 'mutation { provisionCard(input: { cardholder_name: "Op" }) { id } }'],
        'Relayer createSmartAccount'        => ['Relayer', 'mutation { createSmartAccount(input: { owner_address: "0x1111111111111111111111111111111111111111", network: "polygon" }) { id } }'],
        'Relayer supportedRelayerNetworks'  => ['Relayer', '{ supportedRelayerNetworks }'],
    ]);

    it('follows a runtime module change only while runtime module management is on', function (): void {
        $operator = g385wStaff('admin');
        Cache::forever('domain_manager_disabled', ['Ledger']);

        config(['modules.runtime_management_enabled' => true]);
        expect(g385wError(g385wGraphql($this, $operator, '{ ledgerAccounts { code } }')))->toBe('This operation is not available.');

        // Off (the production default): MODULES_DISABLED stays authoritative.
        config(['modules.runtime_management_enabled' => false]);
        expect(g385wError(g385wGraphql($this, $operator, '{ ledgerAccounts { code } }')))->toBeNull();

        config(['modules.disabled' => ['Ledger']]);
        expect(g385wError(g385wGraphql($this, $operator, '{ ledgerAccounts { code } }')))->toBe('This operation is not available.');
    });
});

describe('C40 createSmartAccount never hands out or overwrites another user\'s smart account', function (): void {
    it('refuses an owner address already registered to another user (GraphQL)', function (): void {
        $victim = g385wCustomer();
        $owner = '0x' . str_repeat('b', 40);
        $account = g385wSmartAccount($victim, $owner);
        $attacker = g385wCustomer();

        $json = g385wGraphql($this, $attacker, 'mutation ($o: String!) { createSmartAccount(input: { owner_address: $o, network: "polygon" }) { id user_id account_address nonce } }', ['o' => $owner]);

        expect(g385wError($json))->toBe(AddressRegistrationRejectedException::MESSAGE)
            ->and($json['data'] ?? null)->toBeNull()
            ->and((string) json_encode($json))->not->toContain((string) $account->account_address);
        expect(SmartAccount::query()->count())->toBe(1)
            ->and((int) $account->fresh()?->user_id)->toBe($victim->id);
    });

    it('refuses an owner address already registered to another user (REST)', function (): void {
        $victim = g385wCustomer();
        $owner = '0x' . str_repeat('c', 40);
        $account = g385wSmartAccount($victim, $owner);

        Sanctum::actingAs(g385wCustomer(), ['read', 'write', 'delete']);
        $response = $this->postJson('/api/v1/relayer/account', ['owner_address' => $owner, 'network' => 'polygon']);

        // Same generic 422 as the wallet address registrar (review W2).
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_ADDRESS');
        expect($response->json('data'))->toBeNull();
        expect(SmartAccount::query()->count())->toBe(1)
            ->and((int) $account->fresh()?->user_id)->toBe($victim->id);
    });

    it('returns the caller\'s own smart account and registers a new owner address for the caller', function (): void {
        $user = g385wCustomer();
        $owner = '0x' . str_repeat('d', 40);
        $account = g385wSmartAccount($user, $owner);

        $json = g385wGraphql($this, $user, 'mutation ($o: String!) { createSmartAccount(input: { owner_address: $o, network: "polygon" }) { id user_id } }', ['o' => $owner]);
        expect($json)->not->toHaveKey('errors')
            ->and((string) $json['data']['createSmartAccount']['id'])->toBe((string) $account->id);

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/account', ['owner_address' => $owner, 'network' => 'polygon'])
            ->assertOk()
            ->assertJsonPath('data.id', (string) $account->id);

        $fresh = '0x' . str_repeat('e', 40);
        $json = g385wGraphql($this, $user, 'mutation ($o: String!) { createSmartAccount(input: { owner_address: $o, network: "polygon" }) { user_id owner_address } }', ['o' => $fresh]);
        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['createSmartAccount']['user_id'])->toBe($user->id);
        expect(SmartAccount::query()->where('owner_address', $fresh)->value('user_id'))->toBe($user->id);
    });
});

describe('C41 GraphQL card creation shares the REST card_provision limit', function (): void {
    beforeEach(function (): void {
        config(['rate_limiting.enabled' => true, 'rate_limiting.force_in_tests' => true]);
    });

    it('stops provisionCard and createCard after the hourly card_provision limit', function (string $field, string $input): void {
        $user = g385wCustomer();
        // Exactly 15 calls reach the card issuer; Mockery fails the test on a 16th.
        $cards = array_map(fn (int $i): VirtualCard => g385wDemoCard("tok_{$i}"), range(1, 15));
        g385wMock(CardProvisioningService::class, function (MockInterface $mock) use ($cards): void {
            $mock->shouldReceive('createCard')->times(15)->andReturn(...$cards);
        });

        // 16 aliased calls in one request: the 16th is over the 15/hour limit.
        $fields = [];
        for ($i = 1; $i <= 16; $i++) {
            $fields[] = "c{$i}: {$field}(input: {$input}) { id }";
        }
        $json = g385wGraphql($this, $user, 'mutation { ' . implode(' ', $fields) . ' }');

        expect(g385wError($json))->toBe("Rate limit for Mutation.{$field} exceeded. Try again later.")
            ->and($json['data'] ?? null)->toBeNull();
    })->with([
        'provisionCard' => ['provisionCard', '{ cardholder_name: "Card Holder" }'],
        'createCard'    => ['createCard', '{ cardholder_id: "Card Holder" }'],
    ]);

    it('lets a user create cards within the limit', function (): void {
        $user = g385wCustomer();
        g385wMock(CardProvisioningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCard')->twice()->andReturn(g385wDemoCard('tok_a'), g385wDemoCard('tok_b'));
        });

        $json = g385wGraphql($this, $user, 'mutation { a: provisionCard(input: { cardholder_name: "A" }) { id } b: createCard(input: { cardholder_id: "B" }) { id } }');

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['a']['id'])->toBe('tok_a')
            ->and($json['data']['b']['id'])->toBe('tok_b');
    });
});

describe('C53 smartAccountNonce and BIAN payment reads by a non-party', function (): void {
    it('refuses smartAccountNonce for another user\'s smart account without querying the chain', function (): void {
        $victim = g385wCustomer();
        $account = g385wSmartAccount($victim, '0x' . str_repeat('f', 40));
        g385wMock(SmartAccountService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getNonceInfo');
        });

        $json = g385wGraphql($this, g385wCustomer(), 'query ($id: ID!) { smartAccountNonce(account_id: $id) { nonce pending_ops } }', ['id' => (string) $account->id]);

        expect($json['data']['smartAccountNonce'])->toBeNull()
            ->and(g385wError($json))->not->toBeNull();
        expect($account->fresh()?->nonce)->toBe(7);
    });

    it('returns smartAccountNonce to the owner', function (): void {
        $owner = g385wCustomer();
        $account = g385wSmartAccount($owner, '0x' . str_repeat('9', 40));
        g385wMock(SmartAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getNonceInfo')->once()->andReturn(['nonce' => 7, 'pending_ops' => 1, 'deployed' => false]);
        });

        $json = g385wGraphql($this, $owner, 'query ($id: ID!) { smartAccountNonce(account_id: $id) { nonce pending_ops } }', ['id' => (string) $account->id]);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['smartAccountNonce'])->toBe(['nonce' => 7, 'pending_ops' => 1]);
    });

    it('answers a BIAN payment retrieve and status request by a non-party like an unknown reference', function (): void {
        $payer = g385wCustomer();
        $payee = g385wCustomer();
        $from = Account::factory()->create(['user_uuid' => $payer->uuid]);
        $to = Account::factory()->create(['user_uuid' => $payee->uuid]);
        g385wTransferEvent($from, $to);
        $outsider = g385wCustomer();
        $events = g385wMoneyEventCount();

        Sanctum::actingAs($outsider, ['read', 'write', 'delete']);
        $this->getJson("/api/bian/payment-initiation/{$from->uuid}/retrieve")->assertNotFound();
        $this->postJson("/api/bian/payment-initiation/{$from->uuid}/payment-status/request")
            ->assertOk()
            ->assertJsonPath('paymentStatusRecord.paymentStatus', 'not_found')
            ->assertJsonPath('paymentStatusRecord.eventCount', 0);

        expect(g385wMoneyEventCount())->toBe($events);
    });

    it('serves the BIAN payment retrieve and status request to the payer and the payee', function (): void {
        $payer = g385wCustomer();
        $payee = g385wCustomer();
        $from = Account::factory()->create(['user_uuid' => $payer->uuid]);
        $to = Account::factory()->create(['user_uuid' => $payee->uuid]);
        g385wTransferEvent($from, $to);

        foreach ([$payer, $payee] as $party) {
            Sanctum::actingAs($party, ['read', 'write', 'delete']);
            $this->getJson("/api/bian/payment-initiation/{$from->uuid}/retrieve")
                ->assertOk()
                ->assertJsonPath('paymentInitiationTransaction.paymentDetails.payeeReference', $to->uuid);
            $this->postJson("/api/bian/payment-initiation/{$from->uuid}/payment-status/request")
                ->assertOk()
                ->assertJsonPath('paymentStatusRecord.paymentStatus', 'completed')
                ->assertJsonPath('paymentStatusRecord.eventCount', 1);
        }
    });
});

describe('C54 staff paths reach customer records with the BelongsToTeam scope on', function (): void {
    it('lets an operator from another team freeze and unfreeze a customer account', function (): void {
        $customer = g385wCustomer(withTeam: true);
        $account = Account::factory()->create(['user_uuid' => $customer->uuid, 'team_id' => $customer->personalTeam()->id, 'frozen' => false]);
        $admin = g385wStaff('admin', withTeam: true);

        g385wWithTeamScope(function () use ($admin, $account): void {
            $json = g385wGraphql($this, $admin, 'mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }', ['input' => ['id' => (string) $account->id, 'reason' => 'compliance']]);
            expect($json)->not->toHaveKey('errors');
        });
        expect((bool) $account->fresh()?->frozen)->toBeTrue();

        g385wWithTeamScope(function () use ($admin, $account): void {
            $json = g385wGraphql($this, $admin, 'mutation ($id: ID!) { unfreezeAccount(id: $id) { id frozen } }', ['id' => (string) $account->id]);
            expect($json)->not->toHaveKey('errors');
        });
        expect((bool) $account->fresh()?->frozen)->toBeFalse();
    });

    it('still refuses a customer from another team, with the scope on', function (): void {
        $customer = g385wCustomer(withTeam: true);
        $account = Account::factory()->create(['user_uuid' => $customer->uuid, 'team_id' => $customer->personalTeam()->id, 'frozen' => false]);
        $attacker = g385wCustomer(withTeam: true);

        g385wWithTeamScope(function () use ($attacker, $account): void {
            $json = g385wGraphql($this, $attacker, 'mutation ($input: FreezeAccountInput!) { freezeAccount(input: $input) { id frozen } }', ['input' => ['id' => (string) $account->id, 'reason' => 'x']]);
            expect(g385wError($json))->not->toBeNull();
        });
        expect((bool) $account->fresh()?->frozen)->toBeFalse();
    });

    it('lets an operator from another team link a customer account to a custodian', function (): void {
        config(['ledger.direct_funding_enabled' => false]);
        $customer = g385wCustomer(withTeam: true);
        $account = Account::factory()->create(['user_uuid' => $customer->uuid, 'team_id' => $customer->personalTeam()->id]);
        $operator = g385wStaff('bank_admin', withTeam: true);

        g385wWithTeamScope(function () use ($operator, $account): void {
            $json = g385wGraphql($this, $operator, 'mutation ($i: LinkCustodianAccountInput!) { linkCustodianAccount(input: $i) { account_uuid } }', ['i' => [
                'account_uuid'         => $account->uuid,
                'custodian_name'       => 'mock',
                'custodian_account_id' => 'mock-account-1',
            ]]);
            expect($json)->not->toHaveKey('errors');
        });
        expect(CustodianAccount::query()->where('account_uuid', $account->uuid)->where('custodian_account_id', 'mock-account-1')->exists())->toBeTrue();
    });

    it('lets compliance staff from another team read and escalate a fraud case, and hides it from customers', function (): void {
        $subject = g385wCustomer(withTeam: true);
        $subjectAccount = Account::factory()->create(['user_uuid' => $subject->uuid, 'team_id' => $subject->personalTeam()->id]);
        $fraudCase = FraudCase::factory()->create([
            'subject_account_uuid' => $subjectAccount->uuid,
            'description'          => 'G385W-FRAUD-CASE',
            'team_id'              => $subject->personalTeam()->id,
        ]);
        $analyst = g385wStaff('risk_manager', withTeam: true);
        $customer = g385wCustomer(withTeam: true);

        // FraudCaseService::escalateCase uses priority constants, columns and
        // a note method that FraudCase does not have (it fails for every
        // caller, a separate bug), so the service is replaced here and the
        // test checks that the analyst's escalation reaches it for this case.
        g385wMock(FraudCaseService::class, function (MockInterface $mock) use ($fraudCase): void {
            $mock->shouldReceive('escalateCase')
                ->once()
                ->withArgs(fn (FraudCase $case, string $reason): bool => $case->is($fraudCase) && $reason === 'G385W-ESCALATION')
                ->andReturnUsing(fn (FraudCase $case): FraudCase => $case);
        });

        g385wWithTeamScope(function () use ($analyst, $customer, $fraudCase): void {
            $json = g385wGraphql($this, $customer, '{ fraudCases(first: 20) { data { description } } }');
            expect($json['data']['fraudCases']['data'])->toBe([]);

            $json = g385wGraphql($this, $analyst, 'query ($id: ID!) { fraudCase(id: $id) { description } }', ['id' => (string) $fraudCase->id]);
            expect($json['data']['fraudCase']['description'] ?? null)->toBe('G385W-FRAUD-CASE');

            $json = g385wGraphql($this, $analyst, '{ fraudCases(first: 20) { data { description } } }');
            expect(array_column($json['data']['fraudCases']['data'], 'description'))->toBe(['G385W-FRAUD-CASE']);

            $json = g385wGraphql($this, $analyst, 'mutation ($i: EscalateFraudCaseInput!) { escalateFraudCase(input: $i) { id } }', ['i' => ['id' => (string) $fraudCase->id, 'severity' => 'high', 'notes' => 'G385W-ESCALATION']]);
            expect($json)->not->toHaveKey('errors')
                ->and($json['data']['escalateFraudCase']['id'] ?? null)->toBe((string) $fraudCase->id);
        });
    });
});

describe('D06 GraphQL initiateCustodianTransfer requires active links on one custodian', function (): void {
    /**
     * @return array{0: User, 1: Account, 2: Account}
     */
    function g385wTransferAccounts(): array
    {
        $owner = g385wCustomer();
        $from = Account::factory()->create(['user_uuid' => $owner->uuid]);
        $to = Account::factory()->create(['user_uuid' => $owner->uuid]);

        return [$owner, $from, $to];
    }

    function g385wRecordCustodianTransfer(CustodianAccount $fromLink, CustodianAccount $toLink, Money $amount, string $assetCode): string
    {
        Asset::query()->firstOrCreate(['code' => $assetCode], ['name' => $assetCode, 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        CustodianTransfer::query()->create([
            'id'                        => 'g385w-custodian-transfer',
            'from_account_uuid'         => $fromLink->account_uuid,
            'to_account_uuid'           => $toLink->account_uuid,
            'from_custodian_account_id' => $fromLink->id,
            'to_custodian_account_id'   => $toLink->id,
            'amount'                    => $amount->getAmount(),
            'asset_code'                => $assetCode,
            'status'                    => 'pending',
            'transfer_type'             => 'internal',
        ]);

        return 'g385w-custodian-transfer';
    }

    /**
     * @return array<string, mixed>
     */
    function g385wInitiateTransfer(PHPUnit\Framework\TestCase $test, User $user, Account $from, Account $to): array
    {
        return g385wGraphql($test, $user, 'mutation ($i: InitiateCustodianTransferInput!) { initiateCustodianTransfer(input: $i) { id } }', ['i' => [
            'from_account_uuid' => $from->uuid,
            'to_account_uuid'   => $to->uuid,
            'amount'            => 50.0,
            'asset_code'        => 'EUR',
        ]]);
    }

    it('refuses a source account whose custodian link is not active', function (string $status): void {
        [$owner, $from, $to] = g385wTransferAccounts();
        CustodianAccount::query()->create(['account_uuid' => $from->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1', 'status' => $status, 'is_primary' => true]);
        CustodianAccount::query()->create(['account_uuid' => $to->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-2', 'status' => 'active', 'is_primary' => false]);
        g385wMock(CustodianAccountService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('initiateTransfer');
        });

        $json = g385wInitiateTransfer($this, $owner, $from, $to);

        expect(g385wError($json))->toBe('Custodian account is not linked to this account.');
    })->with(['suspended', 'closed', 'pending']);

    it('refuses a destination without an active link on the source custodian', function (): void {
        [$owner, $from, $to] = g385wTransferAccounts();
        CustodianAccount::query()->create(['account_uuid' => $from->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1', 'status' => 'active', 'is_primary' => true]);
        CustodianAccount::query()->create(['account_uuid' => $to->uuid, 'custodian_name' => 'otherbank', 'custodian_account_id' => 'other-1', 'status' => 'active', 'is_primary' => true]);
        CustodianAccount::query()->create(['account_uuid' => $to->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-2', 'status' => 'closed', 'is_primary' => false]);
        g385wMock(CustodianAccountService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('initiateTransfer');
        });

        $json = g385wInitiateTransfer($this, $owner, $from, $to);

        expect(g385wError($json))->toBe('The destination account has no active link with this custodian.');
    });

    it('passes active links on the same custodian to the custodian service and returns the transfer', function (): void {
        [$owner, $from, $to] = g385wTransferAccounts();
        CustodianAccount::query()->create(['account_uuid' => $from->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-1', 'status' => 'active', 'is_primary' => true]);
        CustodianAccount::query()->create(['account_uuid' => $to->uuid, 'custodian_name' => 'otherbank', 'custodian_account_id' => 'other-1', 'status' => 'active', 'is_primary' => true]);
        CustodianAccount::query()->create(['account_uuid' => $to->uuid, 'custodian_name' => 'mock', 'custodian_account_id' => 'mock-account-2', 'status' => 'active', 'is_primary' => false]);
        // The stand-in service records the transfer it was asked to make, so
        // the mutation can return it (the connector receipt id).
        g385wMock(CustodianAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn (CustodianAccount $fromLink, CustodianAccount $toLink, Money $amount, string $assetCode): bool => $fromLink->custodian_account_id === 'mock-account-1'
                    && $toLink->custodian_account_id === 'mock-account-2'
                    && $amount->getAmount() === 5000
                    && $assetCode === 'EUR')
                ->andReturnUsing(fn (CustodianAccount $fromLink, CustodianAccount $toLink, Money $amount, string $assetCode): string => g385wRecordCustodianTransfer($fromLink, $toLink, $amount, $assetCode));
        });

        $json = g385wInitiateTransfer($this, $owner, $from, $to);

        expect($json)->not->toHaveKey('errors')
            ->and($json['data']['initiateCustodianTransfer']['id'] ?? null)->toBe('g385w-custodian-transfer');
        $transfer = CustodianTransfer::query()->findOrFail('g385w-custodian-transfer');
        expect($transfer->from_account_uuid)->toBe($from->uuid)
            ->and($transfer->to_account_uuid)->toBe($to->uuid)
            ->and($transfer->amount)->toBe(5000);
    });
});
