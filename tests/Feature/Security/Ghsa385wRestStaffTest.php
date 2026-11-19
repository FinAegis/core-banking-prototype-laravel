<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Compliance\Services\RegulatoryReportingService;
use App\Domain\Custodian\Services\BankAlertingService;
use App\Domain\Custodian\Services\DailyReconciliationService;
use App\Domain\Exchange\Services\EnhancedExchangeRateService;
use App\Domain\Fraud\Models\FraudRule;
use App\Domain\Microfinance\Models\CollectionSheet;
use App\Domain\Microfinance\Models\FieldOfficer;
use App\Domain\Microfinance\Services\FieldOfficerService;
use App\Domain\Microfinance\Services\GroupLendingService;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Treasury\Models\AssetAllocation;
use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use App\Domain\Treasury\Services\YieldOptimizationService;
use App\Domain\Wallet\Services\WalletService;
use App\Domain\Webhook\Models\Webhook;
use App\Domain\Webhook\Models\WebhookDelivery;
use App\Models\ApiKey;
use App\Models\User;
use Database\Factories\FraudRuleFactory;
use Database\Factories\StablecoinCollateralPositionFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

/*
| GHSA-385w-q6j4-5qw6, REST staff surfaces: platform webhooks, the Banking
| operator groups (reconciliation, bank health, workflow monitoring, batch
| history), the regulatory report generators, treasury portfolios, fraud
| rules and statistics, microfinance collection sheets, stablecoin risk and
| liquidation endpoints and the exchange-rate refresh. Each denial is
| paired with a check that the staff role (or owner) still gets through.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

function g385User(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    return $user;
}

describe('C05 v2 webhook management', function (): void {
    beforeEach(function (): void {
        $this->webhook = Webhook::create([
            'uuid'      => Str::uuid()->toString(),
            'name'      => 'Operator webhook',
            'url'       => 'https://operator.example.test/hook',
            'events'    => ['account.frozen'],
            'secret'    => 'whsec_g385',
            'is_active' => true,
        ]);
        WebhookDelivery::create([
            'webhook_uuid'   => $this->webhook->uuid,
            'event_type'     => 'account.frozen',
            'payload'        => ['account_uuid' => 'g385-delivery-marker'],
            'attempt_number' => 1,
            'status'         => WebhookDelivery::STATUS_FAILED,
            'response_body'  => 'g385-partner-body',
        ]);
    });

    it('refuses every management route to a regular user and changes nothing', function (): void {
        $id = $this->webhook->uuid;
        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        $this->getJson('/api/v2/webhooks')->assertForbidden()->assertDontSee('operator.example.test');
        $this->getJson("/api/v2/webhooks/{$id}")->assertForbidden()->assertDontSee('operator.example.test');
        $this->getJson("/api/v2/webhooks/{$id}/deliveries")->assertForbidden()
            ->assertDontSee('g385-delivery-marker')->assertDontSee('g385-partner-body');
        $this->putJson("/api/v2/webhooks/{$id}", ['url' => 'https://attacker.example.test/h'])->assertForbidden();
        $this->deleteJson("/api/v2/webhooks/{$id}")->assertForbidden();
        $this->postJson('/api/v2/webhooks', [
            'url'    => 'https://attacker.example.test/h',
            'events' => ['account.frozen'],
        ])->assertForbidden();

        expect(Webhook::query()->count())->toBe(1)
            ->and(Webhook::query()->where('uuid', $id)->firstOrFail()->url)->toBe('https://operator.example.test/hook');
    });

    it('refuses a regular user\'s API key as well', function (): void {
        $key = ApiKey::createForUser(g385User(), ['name' => 'g385', 'permissions' => ['read', 'write', 'delete']]);

        $this->withToken($key['plain_key'])->getJson('/api/v2/webhooks')->assertForbidden();
        $this->withToken($key['plain_key'])
            ->putJson("/api/v2/webhooks/{$this->webhook->uuid}", ['url' => 'https://attacker.example.test/h'])
            ->assertForbidden();

        expect(Webhook::query()->where('uuid', $this->webhook->uuid)->firstOrFail()->url)->toBe('https://operator.example.test/hook');
    });

    it('lets platform administrators manage webhooks, by token or API key', function (): void {
        $admin = g385User('admin');
        $id = $this->webhook->uuid;
        Sanctum::actingAs($admin, ['read', 'write', 'delete']);

        $this->getJson('/api/v2/webhooks')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/v2/webhooks/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson("/api/v2/webhooks/{$id}/deliveries")->assertOk()->assertJsonPath('meta.total', 1);
        $this->putJson("/api/v2/webhooks/{$id}", ['is_active' => false])->assertOk();
        expect(Webhook::query()->where('uuid', $id)->firstOrFail()->is_active)->toBeFalse();

        $key = ApiKey::createForUser($admin, ['name' => 'g385-admin', 'permissions' => ['read', 'write', 'delete']]);
        $this->withToken($key['plain_key'])->getJson('/api/v2/webhooks')->assertOk();

        Sanctum::actingAs($admin, ['read', 'write', 'delete']);
        $this->deleteJson("/api/v2/webhooks/{$id}")->assertNoContent();
        expect(Webhook::query()->find($id))->toBeNull();
    });

    it('keeps the public event catalogue open', function (): void {
        $this->getJson('/api/v2/webhooks/events')->assertOk()->assertJsonPath('data.account.0', 'account.created');
    });
});

describe('C11 and C58 Banking operator groups', function (): void {
    it('refuses workflow monitoring and batch history to a regular user', function (): void {
        DB::table('workflows')->insert([
            'class'      => 'App\\Domain\\Payment\\Workflows\\TransferWorkflow',
            'arguments'  => 'g385-workflow-arguments',
            'status'     => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $batch = BatchJob::query()->create([
            'uuid'        => Str::uuid()->toString(),
            'user_uuid'   => Str::uuid()->toString(),
            'name'        => 'g385-batch-name',
            'type'        => 'transfer',
            'status'      => 'completed',
            'total_items' => 1,
        ]);
        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        foreach (['/api/workflows', '/api/workflows/search?query=g385&type=arguments', '/api/workflows/stats', '/api/workflows/failed'] as $path) {
            $this->getJson($path)->assertForbidden()->assertDontSee('g385-workflow-arguments');
        }
        $this->getJson('/api/batch-operations')->assertForbidden()->assertDontSee('g385-batch-name');
        $this->getJson("/api/batch-operations/{$batch->uuid}/status")->assertForbidden()->assertDontSee('g385-batch-name');
    });

    it('shows workflow monitoring and batch history to platform operators', function (string $role): void {
        Sanctum::actingAs(g385User($role), ['read', 'write', 'delete']);

        $this->getJson('/api/workflows')->assertOk();
        $this->getJson('/api/batch-operations')->assertOk();
    })->with(['admin', 'bank_admin']);

    it('refuses reconciliation and bank-health operations to a regular user without running them', function (): void {
        /** @var DailyReconciliationService&MockInterface $reconciliation */
        $reconciliation = Mockery::mock(DailyReconciliationService::class);
        $reconciliation->shouldNotReceive('performDailyReconciliation', 'getLatestReport');
        app()->instance(DailyReconciliationService::class, $reconciliation);
        /** @var BankAlertingService&MockInterface $alerting */
        $alerting = Mockery::mock(BankAlertingService::class);
        $alerting->shouldNotReceive('performHealthCheck');
        app()->instance(BankAlertingService::class, $alerting);

        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        $this->postJson('/api/reconciliation/trigger')->assertForbidden();
        $this->getJson('/api/reconciliation/latest')->assertForbidden();
        $this->postJson('/api/bank-health/check')->assertForbidden();
        $this->putJson('/api/bank-health/alerts/config', ['enabled' => false])->assertForbidden();
        $this->postJson('/api/bank-health/alerts/test')->assertForbidden();
    });

    it('lets platform operators reach reconciliation (no 500 from an unregistered middleware)', function (string $role): void {
        /** @var DailyReconciliationService&MockInterface $reconciliation */
        $reconciliation = Mockery::mock(DailyReconciliationService::class);
        $reconciliation->shouldReceive('getLatestReport')->andReturn(null);
        app()->instance(DailyReconciliationService::class, $reconciliation);

        Sanctum::actingAs(g385User($role), ['read', 'write', 'delete']);

        $this->getJson('/api/reconciliation/status')->assertOk()->assertJsonPath('data.last_run_date', null);
    })->with(['admin', 'bank_admin']);

    it('lets compliance staff generate a regulatory report through the real middleware stack', function (): void {
        /** @var RegulatoryReportingService&MockInterface $reports */
        $reports = Mockery::mock(RegulatoryReportingService::class);
        $reports->shouldReceive('generateCTR')->once()->andReturn('regulatory/ctr/ctr_g385.json');
        app()->instance(RegulatoryReportingService::class, $reports);

        Sanctum::actingAs(g385User('compliance_officer'), ['read', 'write', 'delete']);

        $this->postJson('/api/regulatory/reports/ctr', ['date' => now()->subDay()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.filename', 'regulatory/ctr/ctr_g385.json');
    });

    it('still refuses regulatory report generation to a regular user', function (): void {
        /** @var RegulatoryReportingService&MockInterface $reports */
        $reports = Mockery::mock(RegulatoryReportingService::class);
        $reports->shouldNotReceive('generateCTR');
        app()->instance(RegulatoryReportingService::class, $reports);

        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        $this->postJson('/api/regulatory/reports/ctr', ['date' => now()->subDay()->toDateString()])->assertForbidden();
    });

    it('no longer declares the unregistered admin controller middleware', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(
            fn (Illuminate\Routing\Route $route): bool => (bool) preg_match('#^api/(reconciliation|bank-health|regulatory|ctr|sar)(/|$)#', $route->uri())
        );

        expect($routes)->not->toBeEmpty();
        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->not->toContain('admin');
        }
    });
});

describe('C27 REST treasury', function (): void {
    it('refuses portfolio management to a regular user even with a session (TransientToken) login', function (): void {
        /** @var PortfolioManagementService&MockInterface $portfolios */
        $portfolios = Mockery::mock(PortfolioManagementService::class);
        $portfolios->shouldNotReceive('createPortfolio', 'getPortfolio');
        app()->instance(PortfolioManagementService::class, $portfolios);
        /** @var RebalancingService&MockInterface $rebalancing */
        $rebalancing = Mockery::mock(RebalancingService::class);
        $rebalancing->shouldNotReceive('executeRebalancing');
        app()->instance(RebalancingService::class, $rebalancing);

        $portfolioId = Str::uuid()->toString();
        $this->actingAs(g385User(), 'web');

        $this->getJson("/api/treasury/portfolios/{$portfolioId}")->assertForbidden();
        $this->postJson('/api/treasury/portfolios', [
            'treasury_id' => Str::uuid()->toString(),
            'name'        => 'G385 portfolio',
            'strategy'    => ['riskProfile' => 'conservative', 'rebalanceThreshold' => 5.0, 'targetReturn' => 0.05],
        ])->assertForbidden();
        $this->postJson("/api/treasury/portfolios/{$portfolioId}/approve-rebalancing", [
            'plan'                => ['portfolio_id' => $portfolioId, 'actions' => []],
            'risk_acknowledgment' => true,
        ])->assertForbidden();
        $this->getJson('/api/treasury/liquidity-forecast/' . Str::uuid()->toString() . '/current')->assertForbidden();
        $this->postJson('/api/treasury/yield/optimize', ['treasury_id' => Str::uuid()->toString()])->assertForbidden();
    });

    it('refuses a regular user\'s bearer token that carries the treasury scope', function (): void {
        Sanctum::actingAs(g385User(), ['read', 'write', 'delete', 'treasury']);

        $this->getJson('/api/treasury/portfolios')->assertForbidden();
    });

    it('lets platform operators use the treasury API', function (string $role): void {
        Sanctum::actingAs(g385User($role), ['read', 'write', 'delete', 'treasury']);

        $this->getJson('/api/treasury/portfolios')->assertOk()->assertJson(['success' => true]);
    })->with(['admin', 'bank_admin']);
});

describe('C34 fraud rules and statistics', function (): void {
    it('refuses fraud rules, statistics and the fraud dashboard to a regular user', function (): void {
        $rule = FraudRuleFactory::new()->createOne(['name' => 'G385 velocity threshold rule', 'ml_confidence_threshold' => 0.8]);
        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        $this->getJson('/api/fraud/rules?per_page=1000')->assertForbidden()->assertDontSee('G385 velocity threshold rule');
        $this->getJson("/api/fraud/rules/{$rule->id}")->assertForbidden()->assertDontSee('G385 velocity threshold rule');
        $this->getJson('/api/fraud/detection/statistics')->assertForbidden()->assertJsonMissingPath('data');
        $this->getJson('/api/fraud/detection/model/metrics')->assertForbidden();
        $this->getJson('/api/fraud/dashboard')->assertForbidden();
        $this->postJson('/api/fraud/rules', ['name' => 'forged'])->assertForbidden();

        expect(FraudRule::query()->count())->toBe(1);
    });

    it('shows fraud rules and statistics to compliance staff', function (string $role): void {
        FraudRuleFactory::new()->createOne(['name' => 'G385 velocity threshold rule', 'ml_confidence_threshold' => 0.8]);
        Sanctum::actingAs(g385User($role), ['read', 'write', 'delete']);

        $this->getJson('/api/fraud/rules')->assertOk()->assertSee('G385 velocity threshold rule');
        $this->getJson('/api/fraud/detection/statistics')->assertOk();
    })->with(['compliance_officer', 'admin']);
});

describe('C35 microfinance collection sheets', function (): void {
    beforeEach(function (): void {
        $officerUser = g385User();
        $this->officer = FieldOfficer::create([
            'user_id'      => $officerUser->id,
            'name'         => 'G385 officer',
            'client_count' => 0,
            'is_active'    => true,
        ]);
        $this->group = app(GroupLendingService::class)->createGroup(name: 'G385 group', meetingFrequency: 'weekly');
    });

    it('refuses collection-sheet generation to a regular user', function (): void {
        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/microfinance/collection-sheets', [
            'officer_id'      => $this->officer->id,
            'group_id'        => $this->group->id,
            'expected_amount' => 999999,
        ])->assertForbidden();

        expect(CollectionSheet::query()->count())->toBe(0);
    });

    it('lets platform operators generate a collection sheet', function (): void {
        Sanctum::actingAs(g385User('bank_admin'), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/microfinance/collection-sheets', [
            'officer_id'      => $this->officer->id,
            'group_id'        => $this->group->id,
            'expected_amount' => '150.50',
        ])->assertCreated();

        $sheet = CollectionSheet::query()->sole();
        expect((string) $sheet->expected_amount)->toBe('150.50')
            ->and($sheet->officer_id)->toBe($this->officer->id);
    });

    it('rejects an expected amount in exponent form or with more than two decimals', function (mixed $amount): void {
        Sanctum::actingAs(g385User('bank_admin'), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/microfinance/collection-sheets', [
            'officer_id'      => $this->officer->id,
            'group_id'        => $this->group->id,
            'expected_amount' => $amount,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('expected_amount')
            ->assertDontSee('bcadd')
            ->assertDontSee('well-formed');

        expect(CollectionSheet::query()->count())->toBe(0);
    })->with(['1e3', '150.505', '0.001']);

    it('still tells the operator about an unknown field officer', function (): void {
        Sanctum::actingAs(g385User('bank_admin'), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/microfinance/collection-sheets', [
            'officer_id'      => 'no-such-officer',
            'group_id'        => $this->group->id,
            'expected_amount' => '10.00',
        ])->assertUnprocessable()->assertJsonPath('error', 'Field officer not found: no-such-officer');

        expect(CollectionSheet::query()->count())->toBe(0);
    });

    it('never returns an internal error message from collection-sheet generation', function (): void {
        Sanctum::actingAs(g385User('bank_admin'), ['read', 'write', 'delete']);

        /** @var FieldOfficerService&MockInterface $officers */
        $officers = Mockery::mock(FieldOfficerService::class);
        $officers->shouldReceive('generateCollectionSheet')
            ->andThrow(new ErrorException('SQLSTATE[HY000]: internal detail in /var/www/app/Secret.php'));
        app()->instance(FieldOfficerService::class, $officers);

        $this->postJson('/api/v1/microfinance/collection-sheets', [
            'officer_id'      => $this->officer->id,
            'group_id'        => $this->group->id,
            'expected_amount' => '10.00',
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'The collection sheet could not be created.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('Secret.php');

        expect(CollectionSheet::query()->count())->toBe(0);
    });
});

describe('C26 and D01 stablecoin risk and liquidation endpoints', function (): void {
    beforeEach(function (): void {
        Feature::define('sub_product.stablecoins', true);
        config(['ledger.direct_funding_enabled' => true]);

        Asset::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
        );
        Stablecoin::create([
            'code'                   => 'G385USD',
            'name'                   => 'G385 USD',
            'symbol'                 => 'G385USD',
            'peg_asset_code'         => 'USD',
            'peg_ratio'              => 1.0,
            'target_price'           => 1.0,
            'stability_mechanism'    => 'collateralized',
            'collateral_ratio'       => 1.5,
            'min_collateral_ratio'   => 1.2,
            'liquidation_penalty'    => 0.1,
            'total_supply'           => 100000,
            'max_supply'             => 10000000,
            'total_collateral_value' => 100000,
            'mint_fee'               => 0.005,
            'burn_fee'               => 0.003,
            'precision'              => 2,
            'is_active'              => true,
            'minting_enabled'        => true,
            'burning_enabled'        => true,
        ]);

        $this->owner = g385User();
        $this->ownerAccount = Account::factory()->create(['user_uuid' => $this->owner->uuid]);
        // Under-collateralised (1.1 < 1.2): eligible for liquidation.
        $this->position = StablecoinCollateralPositionFactory::new()->createOne([
            'account_uuid'             => $this->ownerAccount->uuid,
            'stablecoin_code'          => 'G385USD',
            'collateral_asset_code'    => 'USD',
            'collateral_amount'        => 1100,
            'debt_amount'              => 1000,
            'collateral_ratio'         => 1.1,
            'auto_liquidation_enabled' => true,
            'stop_loss_ratio'          => null,
            'status'                   => 'active',
        ]);
    });

    it('refuses the risk listings, liquidation and simulation to a regular user and moves nothing', function (): void {
        /** @var WalletService&MockInterface $wallet */
        $wallet = Mockery::mock(WalletService::class);
        $wallet->shouldNotReceive('deposit');
        app()->instance(WalletService::class, $wallet);

        $attacker = g385User();
        Account::factory()->create(['user_uuid' => $attacker->uuid]);
        Sanctum::actingAs($attacker, ['read', 'write', 'delete']);
        $uuid = $this->position->uuid;

        $this->getJson('/api/v2/stablecoin-operations/positions/at-risk?buffer_ratio=1000&stablecoin_code=G385USD')
            ->assertForbidden()->assertDontSee($uuid)->assertDontSee($this->ownerAccount->uuid);
        $this->getJson('/api/v2/stablecoin-operations/liquidation/opportunities?stablecoin_code=G385USD')
            ->assertForbidden()->assertDontSee($uuid);
        $this->getJson("/api/v2/stablecoin-operations/liquidation/positions/{$uuid}/reward")->assertForbidden();
        $this->postJson("/api/v2/stablecoin-operations/liquidation/positions/{$uuid}")->assertForbidden();
        $this->postJson('/api/v2/stablecoin-operations/liquidation/execute')->assertForbidden();
        $this->postJson('/api/v2/stablecoin-operations/simulation/G385USD/mass-liquidation', ['price_drop_percentage' => 0.5])
            ->assertForbidden();

        $position = StablecoinCollateralPosition::query()->where('uuid', $uuid)->sole();
        expect($position->status)->toBe('active')
            ->and((int) $position->debt_amount)->toBe(1000)
            ->and((int) Stablecoin::findOrFail('G385USD')->total_supply)->toBe(100000);
    });

    it('answers 404 on liquidation writes while the ledger flag is off, even for an operator', function (): void {
        config(['ledger.direct_funding_enabled' => false]);
        Sanctum::actingAs(g385User('stablecoin_operator'), ['read', 'write', 'delete']);

        $this->postJson("/api/v2/stablecoin-operations/liquidation/positions/{$this->position->uuid}")->assertNotFound();
        $this->postJson('/api/v2/stablecoin-operations/liquidation/execute')->assertNotFound();

        expect(StablecoinCollateralPosition::query()->where('uuid', $this->position->uuid)->sole()->status)->toBe('active');
    });

    it('lets stablecoin operators read the risk listings and liquidate', function (): void {
        $operator = g385User('stablecoin_operator');
        $operatorAccount = Account::factory()->create(['user_uuid' => $operator->uuid]);

        // A staff liquidation pays no liquidator reward: the only deposit is
        // the remaining collateral back to the position owner.
        /** @var WalletService&MockInterface $wallet */
        $wallet = Mockery::mock(WalletService::class);
        $wallet->shouldReceive('deposit')->once()
            ->withArgs(fn ($account, string $asset, $amount): bool => (string) $account === $this->ownerAccount->uuid && $asset === 'USD' && (int) $amount === 990);
        app()->instance(WalletService::class, $wallet);

        Sanctum::actingAs($operator, ['read', 'write', 'delete']);
        $uuid = $this->position->uuid;

        $this->getJson('/api/v2/stablecoin-operations/positions/at-risk?buffer_ratio=0.5&stablecoin_code=G385USD')
            ->assertOk()->assertJsonPath('data.0.uuid', $uuid);
        $this->getJson('/api/v2/stablecoin-operations/liquidation/opportunities?stablecoin_code=G385USD')
            ->assertOk()->assertJsonPath('data.0.position_uuid', $uuid);
        $this->getJson("/api/v2/stablecoin-operations/liquidation/positions/{$uuid}/reward")
            ->assertOk()->assertJsonPath('data.eligible', true);
        $this->postJson("/api/v2/stablecoin-operations/liquidation/positions/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.liquidator_uuid', null)
            ->assertJsonPath('data.returned_to_owner', 990)
            ->assertDontSee($operatorAccount->uuid);

        expect(StablecoinCollateralPosition::query()->where('uuid', $uuid)->sole()->status)->toBe('liquidated');
    });

    it('pays no liquidator reward to the operator who runs the automatic liquidation', function (): void {
        $operator = g385User('stablecoin_operator');
        $operatorAccount = Account::factory()->create(['user_uuid' => $operator->uuid]);

        /** @var WalletService&MockInterface $wallet */
        $wallet = Mockery::mock(WalletService::class);
        $wallet->shouldReceive('deposit')->once()
            ->withArgs(fn ($account, string $asset, $amount): bool => (string) $account === $this->ownerAccount->uuid && (int) $amount === 990);
        app()->instance(WalletService::class, $wallet);

        Sanctum::actingAs($operator, ['read', 'write', 'delete']);

        $this->postJson('/api/v2/stablecoin-operations/liquidation/execute')
            ->assertOk()
            ->assertJsonPath('data.liquidated_count', 1)
            ->assertDontSee($operatorAccount->uuid);

        expect(StablecoinCollateralPosition::query()->where('uuid', $this->position->uuid)->sole()->status)->toBe('liquidated');
    });
});

describe('GraphQL createPortfolio', function (): void {
    beforeEach(function (): void {
        Cache::forget('domain_manager_disabled');
        config(['modules.disabled' => []]);
    });

    $mutation = 'mutation ($input: CreatePortfolioInput!) { createPortfolio(input: $input) { portfolio_id asset_class target_weight } }';
    $input = ['asset_class' => 'g385_fixed_income', 'target_weight' => 40.0, 'target_amount' => 1000.0];

    it('is refused to a user who is not an operator and creates nothing', function () use ($mutation, $input): void {
        /** @var YieldOptimizationService&MockInterface $yield */
        $yield = Mockery::mock(YieldOptimizationService::class);
        $yield->shouldNotReceive('optimizePortfolio');
        app()->instance(YieldOptimizationService::class, $yield);

        $json = $this->actingAs(g385User(), 'sanctum')
            ->postJson('/graphql', ['query' => $mutation, 'variables' => ['input' => $input]])
            ->assertOk()
            ->json();

        expect($json['errors'][0]['message'] ?? null)->toBe('You are not authorized to create a portfolio.')
            ->and($json['data'] ?? null)->toBeNull()
            ->and(AssetAllocation::query()->where('asset_class', 'g385_fixed_income')->exists())->toBeFalse();
    });

    // The operator gets past the role check and the portfolio is optimised.
    // (Storing the allocation row fails on its own, before and after this
    // change: the model uses HasUuids on an integer id column.)
    it('lets platform operators through', function (string $role) use ($mutation, $input): void {
        /** @var YieldOptimizationService&MockInterface $yield */
        $yield = Mockery::mock(YieldOptimizationService::class);
        $yield->shouldReceive('optimizePortfolio')->once()->andReturn([]);
        app()->instance(YieldOptimizationService::class, $yield);

        $json = $this->actingAs(g385User($role), 'sanctum')
            ->postJson('/graphql', ['query' => $mutation, 'variables' => ['input' => $input]])
            ->assertOk()
            ->json();

        expect($json['errors'][0]['message'] ?? null)->not->toBe('You are not authorized to create a portfolio.');
    })->with(['admin', 'bank_admin']);
});

describe('D13 exchange-rate refresh', function (): void {
    it('refuses a manual refresh to a regular user without fetching rates', function (): void {
        /** @var EnhancedExchangeRateService&MockInterface $rates */
        $rates = Mockery::mock(EnhancedExchangeRateService::class);
        $rates->shouldNotReceive('fetchAndStoreRate', 'refreshAllRates');
        app()->instance(EnhancedExchangeRateService::class, $rates);

        Sanctum::actingAs(g385User(), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/exchange-providers/refresh', ['pairs' => ['USD/EUR']])->assertForbidden();
        $this->postJson('/api/v1/exchange-providers/refresh')->assertForbidden();
    });

    it('lets platform operators refresh a bounded list of distinct pairs', function (): void {
        /** @var EnhancedExchangeRateService&MockInterface $rates */
        $rates = Mockery::mock(EnhancedExchangeRateService::class);
        $rates->shouldReceive('fetchAndStoreRate')->once()->with('USD', 'EUR')->andReturn(null);
        app()->instance(EnhancedExchangeRateService::class, $rates);

        Sanctum::actingAs(g385User('admin'), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/exchange-providers/refresh', ['pairs' => ['USD/EUR']])
            ->assertOk()
            ->assertJsonPath('message', 'Exchange rates refreshed');
        $this->postJson('/api/v1/exchange-providers/refresh', ['pairs' => ['USD/EUR', 'USD/EUR']])
            ->assertUnprocessable();
        $this->postJson('/api/v1/exchange-providers/refresh', ['pairs' => array_fill(0, 51, 'USD/EUR')])
            ->assertUnprocessable();
    });
});
