<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\AI\MCP\Tools\Account\CreateAccountTool;
use App\Domain\Custodian\Connectors\MockBankConnector;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Pricing\Models\PriceQuote;
use App\Models\User;
use App\Providers\CustodianServiceProvider;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

/*
| Feature flags for demo-only ledger features. Every flag defaults to the
| current (enabled) behaviour; these tests flip each one off and assert the
| gated surface closes while route names stay resolvable.
|
| Module gates read config('modules.disabled') through
| DomainManager::isDisabledByConfig(), which never consults the
| 'domain_manager_disabled' cache key — the cache is cleared anyway so the
| ModuleRouteLoader state cannot leak between tests.
*/

beforeEach(function (): void {
    Cache::forget('domain_manager_disabled');
    config(['modules.disabled' => []]);
});

/**
 * @return array{0: User, 1: Account, 2: string}
 */
function switchesUserWithAccount(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $token = $user->createToken('switches', ['read', 'write', 'delete'])->plainTextToken;

    return [$user, $account, $token];
}

describe('ledger.direct_funding_enabled', function (): void {
    beforeEach(function (): void {
        config(['ledger.direct_funding_enabled' => false]);
    });

    it('returns 404 for v2 account deposit and withdraw', function (): void {
        [, $account, $token] = switchesUserWithAccount();

        $this->withToken($token)
            ->postJson("/api/v2/accounts/{$account->uuid}/deposit", ['amount' => 1000, 'asset_code' => 'USD'])
            ->assertNotFound();

        $this->withToken($token)
            ->postJson("/api/v2/accounts/{$account->uuid}/withdraw", ['amount' => 1000, 'asset_code' => 'USD'])
            ->assertNotFound();
    });

    it('returns 404 for v1 account deposit, withdraw and transaction reversal', function (): void {
        [$user, $account] = switchesUserWithAccount();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson("/api/accounts/{$account->uuid}/withdraw", ['amount' => 1000, 'asset_code' => 'USD'])
            ->assertNotFound();
        $this->postJson("/api/accounts/{$account->uuid}/deposit", ['amount' => 1000, 'asset_code' => 'USD'])
            ->assertNotFound();
        $this->postJson("/api/accounts/{$account->uuid}/transactions/reverse", [])
            ->assertNotFound();
    });

    it('rejects a non-zero initial balance on account creation but still creates accounts', function (): void {
        [, , $token] = switchesUserWithAccount();

        $this->withToken($token)
            ->postJson('/api/v2/accounts', ['name' => 'Funded', 'initial_balance' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['initial_balance']);

        $this->withToken($token)
            ->postJson('/api/accounts', ['name' => 'Funded v1', 'initial_balance' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['initial_balance']);

        $this->withToken($token)
            ->postJson('/api/v2/accounts', ['name' => 'Plain account'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Plain account');

        $this->withToken($token)
            ->postJson('/api/v2/accounts', ['name' => 'Zero opening', 'initial_balance' => 0])
            ->assertCreated();
    });

    it('returns 404 for the BIAN API', function (): void {
        [$user, $account] = switchesUserWithAccount();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/bian/current-account/initiate', [
            'customerReference' => $user->uuid,
            'accountName'       => 'BIAN account',
            'accountType'       => 'current',
        ])->assertNotFound();

        $this->postJson("/api/bian/current-account/{$account->uuid}/deposit/execute", ['depositAmount' => 100])
            ->assertNotFound();
        $this->getJson("/api/bian/current-account/{$account->uuid}/retrieve")
            ->assertNotFound();
        $this->postJson('/api/bian/payment-initiation/initiate', [])
            ->assertNotFound();
    });

    it('returns 404 for the custodian transfer and mock custodian webhook', function (): void {
        [$user] = switchesUserWithAccount();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/custodians/mock/transfer', [])->assertNotFound();
        $this->postJson('/api/webhooks/custodian/mock', [])->assertNotFound();
    });

    it('rejects the GraphQL initiatePayment mutation', function (): void {
        [$user, $account] = switchesUserWithAccount();

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation ($input: InitiatePaymentInput!) {
                    initiatePayment(input: $input) { id }
                }
            ',
            'variables' => [
                'input' => [
                    'account_uuid' => $account->uuid,
                    'amount'       => 100,
                    'currency'     => 'USD',
                    'type'         => 'transfer',
                ],
            ],
        ]);

        $response->assertOk();
        expect($response->json('errors.0.message'))->toBe('This operation is not available.');
        expect($response->json('data.initiatePayment'))->toBeNull();
    });

    it('returns 404 for web wallet deposit and withdraw sub-pages but keeps the index pages', function (): void {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        // Card deposits have their own switch (ledger.web_card_deposits_enabled).
        config(['ledger.web_card_deposits_enabled' => false]);
        $this->get('/wallet/deposit/card')->assertNotFound();
        $this->get('/wallet/deposit/bank')->assertNotFound();
        $this->post('/wallet/deposit/paysera/initiate', [])->assertNotFound();
        $this->get('/wallet/deposit/paysera/callback')->assertNotFound();
        $this->get('/wallet/withdraw/bank')->assertNotFound();
        $this->post('/wallet/withdraw/bank', [])->assertNotFound();

        $this->get('/wallet/deposit')->assertOk();

        foreach (['wallet.blockchain.send', 'wallet.blockchain.send.process'] as $name) {
            expect(Route::getRoutes()->getByName($name)?->gatherMiddleware())
                ->toContain('feature:ledger.direct_funding_enabled');
        }

        // Route names stay registered so the index views keep rendering.
        expect(Route::has('wallet.deposit.create'))->toBeTrue();
        expect(Route::has('wallet.withdraw.create'))->toBeTrue();
    });

    it('returns 404 for the currency conversion API and the web convert page', function (): void {
        $user = User::factory()->withPersonalTeam()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/exchange/convert', [
            'account_uuid'  => $account->uuid,
            'from_currency' => 'USD',
            'to_currency'   => 'EUR',
            'amount'        => 10,
        ])->assertNotFound();

        $this->actingAs($user)->get('/wallet/convert')->assertNotFound();

        expect(Route::has('wallet.convert'))->toBeTrue();
    });

    it('refuses an MCP account.create initial deposit', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $result = app(CreateAccountTool::class)->execute([
            'name'            => 'MCP account',
            'type'            => 'checking',
            'currency'        => 'USD',
            'initial_deposit' => 50,
        ]);

        expect($result->isFailure())->toBeTrue();
        expect(Account::query()->where('name', 'MCP account')->exists())->toBeFalse();
    });
});

describe('ledger.direct_funding_enabled default', function (): void {
    it('keeps the direct funding routes registered and reachable', function (): void {
        [$user, $account] = switchesUserWithAccount();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        expect(config('ledger.direct_funding_enabled'))->toBeTrue();

        // Validation error (not 404) proves the route is live.
        $this->postJson("/api/accounts/{$account->uuid}/withdraw", [])->assertStatus(422);
    });

    it('keeps currency conversion reachable but limited to the caller\'s own accounts', function (): void {
        Feature::define('sub_product.exchange', fn (): bool => true);
        [, $account] = switchesUserWithAccount();
        $other = User::factory()->create();
        Sanctum::actingAs($other, ['read', 'write', 'delete']);

        $payload = [
            'account_uuid'  => $account->uuid,
            'from_currency' => 'USD',
            'to_currency'   => 'EUR',
            'amount'        => 10,
        ];

        $this->postJson('/api/exchange/convert', [])->assertStatus(422);
        $this->postJson('/api/exchange/convert', $payload)
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
    });
});

describe('MODULES_DISABLED web/API gates', function (): void {
    it('returns 404 for GCU trading when Basket is disabled', function (): void {
        config(['modules.disabled' => ['Basket']]);
        [$user] = switchesUserWithAccount();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v2/gcu/buy', ['amount' => 100, 'currency' => 'EUR'])->assertNotFound();
        $this->postJson('/api/v2/gcu/sell', ['amount' => 100, 'currency' => 'EUR'])->assertNotFound();
        $this->getJson('/api/v2/gcu/quote?operation=buy&amount=100&currency=EUR')->assertNotFound();

        $this->actingAs($user)->get('/gcu/trading')->assertNotFound();
        expect(Route::has('gcu.trading'))->toBeTrue();
    });

    it('returns 404 for CGO pages when Cgo is disabled while route("cgo") still resolves', function (): void {
        config(['modules.disabled' => ['Cgo']]);

        $this->get('/cgo')->assertNotFound();
        $this->get('/cgo/terms')->assertNotFound();
        $this->post('/cgo/notify', ['email' => 'someone@example.com'])->assertNotFound();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/cgo/invest')->assertNotFound();
        $this->actingAs($user)->post('/cgo/invest', [])->assertNotFound();

        expect(route('cgo'))->toEndWith('/cgo');
        expect(Route::has('cgo.invest'))->toBeTrue();
    });

    it('rejects the GraphQL createInvestment mutation when Cgo is disabled', function (): void {
        config(['modules.disabled' => ['Cgo']]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query'     => 'mutation ($input: CreateInvestmentInput!) { createInvestment(input: $input) { id } }',
            'variables' => ['input' => ['amount' => 1000]],
        ]);

        expect($response->json('errors.0.message'))->toBe('This operation is not available.');
    });

    it('rejects GraphQL mutations of a disabled domain', function (string $module, string $query, array $variables): void {
        $user = User::factory()->create();

        $send = fn () => $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query'     => $query,
            'variables' => $variables,
        ]);

        config(['modules.disabled' => [$module]]);
        $response = $send();
        $response->assertOk();
        expect($response->json('errors.0.message'))->toBe('This operation is not available.');

        // Default (module enabled): the resolver runs and succeeds or fails
        // on its own terms.
        config(['modules.disabled' => []]);
        expect($send()->json('errors.0.message'))->not->toBe('This operation is not available.');
    })->with([
        'Ledger ledgerPostEntry' => [
            'Ledger',
            'mutation ($lines: [JournalLineInput!]!) { ledgerPostEntry(description: "x", lines: $lines) { __typename } }',
            ['lines' => [
                ['account_code' => '1000', 'debit' => '500', 'credit' => '0'],
                ['account_code' => '4000', 'debit' => '0', 'credit' => '500'],
            ]],
        ],
        'Ledger ledgerReverseEntry' => [
            'Ledger',
            'mutation { ledgerReverseEntry(id: "1", reason: "x") { __typename } }',
            [],
        ],
        'Microfinance mfiPurchaseShares' => [
            'Microfinance',
            'mutation { mfiPurchaseShares(input: { account_id: "1", shares: 10 }) { __typename } }',
            [],
        ],
        'PaymentRails paymentRailsSend' => [
            'PaymentRails',
            'mutation { paymentRailsSend(amount: "10.00", currency: "USD", country: "US", beneficiary: { name: "x", account_number: "1", routing_number: "1" }) { __typename } }',
            [],
        ],
        'Basket rebalanceBasket' => [
            'Basket',
            'mutation { rebalanceBasket(input: { id: "1" }) { __typename } }',
            [],
        ],
        'Banking disconnectBank' => [
            'Banking',
            'mutation { disconnectBank(connection_id: "1") }',
            [],
        ],
        'Governance activatePoll' => [
            'Governance',
            'mutation { activatePoll(id: "1") { __typename } }',
            [],
        ],
    ]);

    it('declares @module on every mutation of a domain that can be disabled', function (): void {
        // Resolver namespace (App\GraphQL\Mutations\<Ns>) => domain name.
        $gated = [
            'Banking'              => 'Banking',
            'Basket'               => 'Basket',
            'Cgo'                  => 'Cgo',
            'CrossChain'           => 'CrossChain',
            'DeFi'                 => 'DeFi',
            'FinancialInstitution' => 'FinancialInstitution',
            'Governance'           => 'Governance',
            'ISO20022'             => 'ISO20022',
            'Interledger'          => 'Interledger',
            'Ledger'               => 'Ledger',
            'Lending'              => 'Lending',
            'Microfinance'         => 'Microfinance',
            'OpenBanking'          => 'OpenBanking',
            'PaymentRails'         => 'PaymentRails',
            'Stablecoin'           => 'Stablecoin',
            'Treasury'             => 'Treasury',
        ];

        $mutation = app(ASTBuilder::class)->documentAST()->types['Mutation'];
        assert($mutation instanceof ObjectTypeDefinitionNode);

        $prefix = 'App\\GraphQL\\Mutations\\';
        $checked = 0;
        $seen = [];
        foreach ($mutation->fields as $field) {
            $fieldDirective = ASTHelper::directiveDefinition($field, 'field');
            $resolver = $fieldDirective === null ? null : ASTHelper::directiveArgValue($fieldDirective, 'resolver');
            if (! is_string($resolver) || ! str_starts_with($resolver, $prefix)) {
                continue;
            }
            $namespace = explode('\\', substr($resolver, strlen($prefix)))[0];
            if (! isset($gated[$namespace])) {
                continue;
            }

            $module = ASTHelper::directiveDefinition($field, 'module');
            expect($module)->not->toBeNull("{$field->name->value} has no @module directive");
            assert($module !== null);
            expect(ASTHelper::directiveArgValue($module, 'name'))->toBe($gated[$namespace]);
            $seen[$namespace] = true;
            $checked++;
        }

        expect($checked)->toBeGreaterThan(30);
        // A key that matches no resolver namespace would silently skip that
        // domain's mutations.
        expect(array_keys(array_diff_key($gated, $seen)))->toBe([]);
    });

    it('returns 404 for GCU voting pages when Governance is disabled', function (): void {
        config(['modules.disabled' => ['Governance']]);

        $this->get('/gcu/voting')->assertNotFound();
        expect(Route::has('gcu.voting.index'))->toBeTrue();
    });

    it('returns 404 for lending pages when Lending is disabled', function (): void {
        config(['modules.disabled' => ['Lending']]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/lending')->assertNotFound();
        expect(Route::has('lending.index'))->toBeTrue();
    });
});

describe('trading.enabled', function (): void {
    beforeEach(function (): void {
        config(['trading.enabled' => false]);
    });

    it('returns 404 for exchange, external-exchange and liquidity API routes', function (): void {
        [$user] = switchesUserWithAccount();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/exchange/orders', [
            'type'           => 'buy',
            'order_type'     => 'market',
            'base_currency'  => 'BTC',
            'quote_currency' => 'EUR',
            'amount'         => '0.01',
        ])->assertNotFound();
        $this->getJson('/api/exchange/markets')->assertNotFound();
        $this->getJson('/api/external-exchange/connectors')->assertNotFound();
        $this->postJson('/api/liquidity/swap', [])->assertNotFound();

        // Non-trading routes in the same file stay registered.
        expect(Route::has('status.api'))->toBeTrue();
    });

    it('returns 404 for the web exchange and liquidity pages', function (): void {
        $this->get('/exchange')->assertNotFound();
        $this->get('/liquidity')->assertNotFound();

        expect(Route::has('exchange.index'))->toBeTrue();
        expect(Route::has('liquidity.index'))->toBeTrue();
    });

    it('rejects the GraphQL placeOrder mutation', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query'     => 'mutation ($input: PlaceOrderInput!) { placeOrder(input: $input) { id } }',
            'variables' => ['input' => [
                'base_currency'  => 'BTC',
                'quote_currency' => 'EUR',
                'type'           => 'buy',
                'order_type'     => 'market',
                'amount'         => 0.01,
            ]],
        ]);

        expect($response->json('errors.0.message'))->toBe('This operation is not available.');
    });
});

describe('cards.waitlist_deposit_enabled', function (): void {
    it('answers 503 ERR_CARDS_019 for new deposits while cancel stays reachable', function (): void {
        config(['cards.waitlist_deposit_enabled' => false]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/cards/waitlist/deposit', [
            'quoteId' => (string) Str::uuid(),
        ], ['Idempotency-Key' => Str::random(24)])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'ERR_CARDS_019')
            ->assertJsonPath('error.message', 'Card waitlist deposits are not available.');

        expect(PriceQuote::query()->count())->toBe(0);

        // Cancel still runs the service (no active deposit -> ERR_CARDS_003).
        $this->postJson('/api/v1/cards/waitlist/deposit/cancel', [], ['Idempotency-Key' => Str::random(24)])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ERR_CARDS_003');

        $this->getJson('/api/v1/cards/waitlist/entry')->assertOk();
    });
});

describe('mcp.enabled', function (): void {
    it('answers 503 on the MCP endpoint when disabled, before the OAuth guard', function (): void {
        $url = 'http://' . config('mcp.host') . '/api/mcp';

        config(['mcp.enabled' => false]);

        $this->postJson($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertStatus(503)
            ->assertJsonPath('error', 'mcp_disabled');

        config(['mcp.enabled' => true]);

        $this->postJson($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertUnauthorized();
    });
});

describe('investor.page_enabled', function (): void {
    it('redirects /invest pages to the root and refuses the form post when disabled', function (): void {
        config(['investor.page_enabled' => false]);

        $this->get('/invest')->assertStatus(301)->assertRedirect('/');
        $this->get('/invest/thanks')->assertStatus(301)->assertRedirect('/');
        $this->get('/invest/data-room')->assertStatus(301)->assertRedirect('/');
        $this->post('/invest', [])->assertNotFound();

        expect(route('invest.show'))->toEndWith('/invest');
    });

    it('serves /invest by default', function (): void {
        $this->get('/invest')->assertOk();
    });
});

describe('Jetstream legal pages with promo pages off', function (): void {
    it('redirects /terms-of-service and /privacy-policy to the product legal pages', function (): void {
        $app = require base_path('bootstrap/app.php');

        $app->afterBootstrapping(
            LoadConfiguration::class,
            static function ($app): void {
                $app['config']->set('brand.show_promo_pages', false);
            }
        );

        $app->make(Kernel::class)->bootstrap();

        $this->app = $app;

        $this->get('/terms-of-service')
            ->assertStatus(301)
            ->assertRedirect(route('legal.terms'));

        $this->get('/privacy-policy')
            ->assertStatus(301)
            ->assertRedirect(route('legal.privacy'));

        expect(Route::has('terms.show'))->toBeTrue();
        expect(Route::has('policy.show'))->toBeTrue();
    });
});

/*
| Secure by default: every flag above is OFF when APP_ENV=production (or
| unset) and the env var is absent, ON for every other environment, and an
| explicit env value always wins. Config is resolved at boot, so the default
| expressions are evaluated directly from the config files under a
| controlled environment, and the gates are then exercised with exactly
| those production values.
*/

/**
 * Evaluate a config file with the given environment variables (null =
 * unset) and restore the process environment afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function switchesConfigUnderEnv(string $file, array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [
            'has_server' => array_key_exists($key, $_SERVER),
            'server'     => $_SERVER[$key] ?? null,
            'has_env'    => array_key_exists($key, $_ENV),
            'env'        => $_ENV[$key] ?? null,
            'getenv'     => getenv($key),
        ];

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
        foreach ($saved as $key => $previous) {
            if ($previous['has_server']) {
                $_SERVER[$key] = $previous['server'];
            } else {
                unset($_SERVER[$key]);
            }
            if ($previous['has_env']) {
                $_ENV[$key] = $previous['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($previous['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$previous['getenv']}");
            }
        }
    }
}

/**
 * The demo-only flags, all unset, under the given APP_ENV.
 *
 * @param  array<string, string|null>  $overrides
 * @return array<string, string|null>
 */
function switchesFlagEnv(?string $appEnv, array $overrides = []): array
{
    return array_merge([
        'APP_ENV'                        => $appEnv,
        'LEDGER_DIRECT_FUNDING_ENABLED'  => null,
        'CARDS_WAITLIST_DEPOSIT_ENABLED' => null,
        'INVESTOR_PAGE_ENABLED'          => null,
        'CUSTODIAN_MOCK_ENABLED'         => null,
        'MCP_ENABLED'                    => null,
        'MCP_TOOL_ACCOUNT_CREATE'        => null,
        'MCP_TOOL_PAYMENT_TRANSFER'      => null,
        'MCP_TOOL_EXCHANGE_TRADE'        => null,
        'MCP_TOOL_ACCOUNT_BALANCE'       => null,
        'MCP_TOOL_EXCHANGE_QUOTE'        => null,
        'MCP_TOOL_RAMP_START'            => null,
        'MCP_TOOL_SMS_SEND'              => null,
    ], $overrides);
}

/**
 * Config values the flags resolve to under the given environment.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function switchesResolvedFlags(array $env): array
{
    $mcp = switchesConfigUnderEnv('mcp', $env);
    /** @var array<string, array<string, mixed>> $tools */
    $tools = $mcp['tools'];
    $custodians = switchesConfigUnderEnv('custodians', $env);
    /** @var array<string, array<string, mixed>> $connectors */
    $connectors = $custodians['connectors'];

    return [
        'ledger.direct_funding_enabled'  => switchesConfigUnderEnv('ledger', $env)['direct_funding_enabled'],
        'cards.waitlist_deposit_enabled' => switchesConfigUnderEnv('cards', $env)['waitlist_deposit_enabled'],
        'investor.page_enabled'          => switchesConfigUnderEnv('investor', $env)['page_enabled'],
        'custodians.connectors.mock'     => $connectors['mock']['enabled'],
        'mcp.enabled'                    => $mcp['enabled'],
        'mcp.tools.account.create'       => (bool) $tools['account.create']['enabled'],
        'mcp.tools.payment.transfer'     => (bool) $tools['payment.transfer']['enabled'],
        'mcp.tools.exchange.trade'       => (bool) $tools['exchange.trade']['enabled'],
        'mcp.tools.account.balance'      => (bool) $tools['account.balance']['enabled'],
        'mcp.tools.exchange.quote'       => (bool) $tools['exchange.quote']['enabled'],
        'mcp.tools.ramp.start'           => (bool) $tools['ramp.start']['enabled'],
        'mcp.tools.sms.send'             => (bool) $tools['sms.send']['enabled'],
    ];
}

describe('secure defaults', function (): void {
    it('turns every demo-only flag off under APP_ENV=production when unset', function (?string $appEnv): void {
        $flags = switchesResolvedFlags(switchesFlagEnv($appEnv));

        expect($flags['ledger.direct_funding_enabled'])->toBeFalse()
            ->and($flags['cards.waitlist_deposit_enabled'])->toBeFalse()
            ->and($flags['investor.page_enabled'])->toBeFalse()
            ->and($flags['custodians.connectors.mock'])->toBeFalse()
            ->and($flags['mcp.tools.account.create'])->toBeFalse()
            ->and($flags['mcp.tools.payment.transfer'])->toBeFalse()
            ->and($flags['mcp.tools.exchange.trade'])->toBeFalse()
            ->and($flags['mcp.tools.sms.send'])->toBeFalse();

        // MCP itself, its read-only tools and ramp.start keep their defaults.
        expect($flags['mcp.enabled'])->toBeTrue()
            ->and($flags['mcp.tools.account.balance'])->toBeTrue()
            ->and($flags['mcp.tools.exchange.quote'])->toBeTrue()
            ->and($flags['mcp.tools.ramp.start'])->toBeTrue();
    })->with([
        'production'                                      => ['production'],
        'APP_ENV unset (Laravel treats it as production)' => [null],
        // Any value outside local/testing/demo is treated as production.
        'staging' => ['staging'],
        'a typo'  => ['prod'],
    ]);

    it('keeps every demo-only flag on for local, testing and demo', function (string $appEnv, bool $mockDefault): void {
        $flags = switchesResolvedFlags(switchesFlagEnv($appEnv));

        expect($flags['ledger.direct_funding_enabled'])->toBeTrue()
            ->and($flags['cards.waitlist_deposit_enabled'])->toBeTrue()
            ->and($flags['investor.page_enabled'])->toBeTrue()
            ->and($flags['mcp.tools.account.create'])->toBeTrue()
            ->and($flags['mcp.tools.payment.transfer'])->toBeTrue()
            ->and($flags['mcp.tools.exchange.trade'])->toBeTrue()
            ->and($flags['mcp.tools.sms.send'])->toBeTrue()
            ->and($flags['custodians.connectors.mock'])->toBe($mockDefault);
    })->with([
        'demo'    => ['demo', false],
        'local'   => ['local', true],
        'testing' => ['testing', true],
    ]);

    it('lets an explicit env value win over the environment default', function (): void {
        $production = switchesResolvedFlags(switchesFlagEnv('production', [
            'LEDGER_DIRECT_FUNDING_ENABLED'  => 'true',
            'CARDS_WAITLIST_DEPOSIT_ENABLED' => 'true',
            'INVESTOR_PAGE_ENABLED'          => 'true',
            'MCP_TOOL_PAYMENT_TRANSFER'      => 'true',
        ]));

        expect($production['ledger.direct_funding_enabled'])->toBeTrue()
            ->and($production['cards.waitlist_deposit_enabled'])->toBeTrue()
            ->and($production['investor.page_enabled'])->toBeTrue()
            ->and($production['mcp.tools.payment.transfer'])->toBeTrue()
            ->and($production['mcp.tools.account.create'])->toBeFalse();

        $demo = switchesResolvedFlags(switchesFlagEnv('demo', [
            'LEDGER_DIRECT_FUNDING_ENABLED' => 'false',
            'MCP_TOOL_EXCHANGE_TRADE'       => 'false',
        ]));

        expect($demo['ledger.direct_funding_enabled'])->toBeFalse()
            ->and($demo['mcp.tools.exchange.trade'])->toBeFalse()
            ->and($demo['cards.waitlist_deposit_enabled'])->toBeTrue();
    });

    it('enables the mock custodian only in local/testing or with an explicit demo opt-in', function (): void {
        $mock = fn (string $appEnv, ?string $flag): mixed => switchesResolvedFlags(
            switchesFlagEnv($appEnv, ['CUSTODIAN_MOCK_ENABLED' => $flag])
        )['custodians.connectors.mock'];

        expect($mock('production', 'true'))->toBeFalse()
            ->and($mock('staging', 'true'))->toBeFalse()
            ->and($mock('demo', null))->toBeFalse()
            ->and($mock('demo', 'true'))->toBeTrue()
            ->and($mock('local', null))->toBeTrue()
            ->and($mock('local', 'false'))->toBeFalse();
    });

    it('closes the gated surface with the production default values', function (): void {
        $flags = switchesResolvedFlags(switchesFlagEnv('production'));

        config([
            'ledger.direct_funding_enabled'      => $flags['ledger.direct_funding_enabled'],
            'cards.waitlist_deposit_enabled'     => $flags['cards.waitlist_deposit_enabled'],
            'investor.page_enabled'              => $flags['investor.page_enabled'],
            'custodians.connectors.mock.enabled' => $flags['custodians.connectors.mock'],
            'mcp.tools.account.create.enabled'   => $flags['mcp.tools.account.create'],
            'mcp.tools.payment.transfer.enabled' => $flags['mcp.tools.payment.transfer'],
            'mcp.tools.exchange.trade.enabled'   => $flags['mcp.tools.exchange.trade'],
        ]);

        [$user, $account, $token] = switchesUserWithAccount();

        $this->withToken($token)
            ->postJson("/api/v2/accounts/{$account->uuid}/deposit", ['amount' => 1000, 'asset_code' => 'USD'])
            ->assertNotFound();
        $this->withToken($token)
            ->postJson("/api/accounts/{$account->uuid}/withdraw", ['amount' => 1000, 'asset_code' => 'USD'])
            ->assertNotFound();
        $this->withToken($token)
            ->postJson("/api/bian/current-account/{$account->uuid}/deposit/execute", ['depositAmount' => 100])
            ->assertNotFound();
        $this->withToken($token)
            ->postJson('/api/custodians/mock/transfer', [])
            ->assertNotFound();
        $this->postJson('/api/webhooks/custodian/mock', [])->assertNotFound();

        $this->get('/invest')->assertStatus(301)->assertRedirect('/');

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/cards/waitlist/deposit', [
            'quoteId' => (string) Str::uuid(),
        ], ['Idempotency-Key' => Str::random(24)])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'ERR_CARDS_019');

        // MCP stays on; only the ledger-moving tools are off.
        expect(config('mcp.enabled'))->toBeTrue();
        foreach (['account.create', 'payment.transfer', 'exchange.trade'] as $tool) {
            expect(config("mcp.tools.{$tool}.enabled"))->toBeFalse();
        }
    });

    it('never permits the mock custodian in production, whatever the config says', function (): void {
        config(['custodians.connectors.mock.enabled' => true]);
        expect(MockBankConnector::isPermitted())->toBeTrue();

        $originalEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';
            expect(MockBankConnector::isPermitted())->toBeFalse();

            // The provider skips the mock connector on boot.
            $this->app->instance(CustodianRegistry::class, new CustodianRegistry());
            (new CustodianServiceProvider($this->app))->boot();
            expect(app(CustodianRegistry::class)->has('mock'))->toBeFalse();

            $this->app['env'] = 'demo';
            expect(MockBankConnector::isPermitted())->toBeTrue();
            config(['custodians.connectors.mock.enabled' => false]);
            expect(MockBankConnector::isPermitted())->toBeFalse();
        } finally {
            $this->app['env'] = $originalEnv;
        }
    });

    it('answers 404 on the mock custodian webhook whenever the mock is not available', function (): void {
        config(['custodians.connectors.mock.enabled' => false]);

        $this->postJson('/api/webhooks/custodian/mock', ['type' => 'balance.updated', 'id' => 'mock_1'])
            ->assertNotFound();
    });
});
