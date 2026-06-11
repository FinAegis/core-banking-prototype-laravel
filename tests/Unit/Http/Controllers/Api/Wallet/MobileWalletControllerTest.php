<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Domain\MobilePayment\Services\TransactionDetailService;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\Services\WalletBalanceService;
use App\Domain\Wallet\Services\PrivyAddressRegistrar;
use App\Domain\Wallet\Services\Send\EvmUserOpPreparer;
use App\Domain\Wallet\Services\Send\EvmUserOpSubmitter;
use App\Domain\Wallet\Services\Send\SolanaSendPreparer;
use App\Domain\Wallet\Services\Send\SolanaSendSubmitter;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use App\Http\Controllers\Api\Wallet\MobileWalletController;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Tests\Traits\CreatesSolanaTestTables;
use Tests\UnitTestCase;

uses(UnitTestCase::class, CreatesSolanaTestTables::class);

beforeEach(function (): void {
    $this->balanceService = Mockery::mock(WalletBalanceService::class);
    $this->smartAccountService = Mockery::mock(SmartAccountService::class);
    $this->activityFeedService = Mockery::mock(ActivityFeedService::class);
    $this->transactionDetailService = Mockery::mock(TransactionDetailService::class);
    $this->privyAddressRegistrar = Mockery::mock(PrivyAddressRegistrar::class);
    $this->solanaSendPreparer = Mockery::mock(SolanaSendPreparer::class);
    $this->solanaSendSubmitter = Mockery::mock(SolanaSendSubmitter::class);
    $this->evmUserOpPreparer = Mockery::mock(EvmUserOpPreparer::class);
    $this->evmUserOpSubmitter = Mockery::mock(EvmUserOpSubmitter::class);
    $this->sponsorshipCostTracker = Mockery::mock(SponsorshipCostTracker::class);
    $this->sponsorshipCostTracker->shouldReceive('isDailyBudgetExhausted')->andReturn(false);
});

function makeWalletController($test): MobileWalletController
{
    return new MobileWalletController(
        $test->balanceService,
        $test->smartAccountService,
        $test->activityFeedService,
        $test->transactionDetailService,
        $test->privyAddressRegistrar,
        $test->solanaSendPreparer,
        $test->solanaSendSubmitter,
        $test->evmUserOpPreparer,
        $test->evmUserOpSubmitter,
        $test->sponsorshipCostTracker,
    );
}

function walletUserRequest(string $uri = '/api/v1/wallet/tokens', string $method = 'GET', array $data = []): Request
{
    if ($method === 'POST') {
        $request = Request::create($uri, $method, $data, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
    } else {
        $request = Request::create($uri, $method, $data);
    }
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;
    $user->uuid = 'test-uuid-' . uniqid();
    $request->setUserResolver(fn () => $user);

    return $request;
}

describe('MobileWalletController tokens', function (): void {
    it('returns config-driven token list', function (): void {
        $controller = makeWalletController($this);

        $response = $controller->tokens(walletUserRequest('/api/v1/wallet/tokens'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toBeArray()
            ->and(count($data['data']))->toBe(4);

        $symbols = array_column($data['data'], 'symbol');
        expect($symbols)->toContain('USDC')
            ->and($symbols)->toContain('USDT')
            ->and($symbols)->toContain('WETH')
            ->and($symbols)->toContain('WBTC');
    });

    it('includes network, decimals, and contract addresses per token', function (): void {
        $controller = makeWalletController($this);

        $response = $controller->tokens(walletUserRequest('/api/v1/wallet/tokens'));
        $data = $response->getData(true);

        $usdc = collect($data['data'])->firstWhere('symbol', 'USDC');
        expect($usdc)->toHaveKeys(['symbol', 'name', 'decimals', 'networks', 'icon', 'addresses'])
            ->and($usdc['decimals'])->toBe(6)
            ->and($usdc['networks'])->toContain('polygon')
            ->and($usdc['addresses'])->toHaveKey('polygon');
    });

    it('filters tokens by chain_id query parameter', function (): void {
        $controller = makeWalletController($this);

        $response = $controller->tokens(walletUserRequest('/api/v1/wallet/tokens?chain_id=base'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();

        // USDC has base, USDT does not, WETH has base, WBTC does not
        $symbols = array_column($data['data'], 'symbol');
        expect($symbols)->toContain('USDC')
            ->and($symbols)->toContain('WETH')
            ->and($symbols)->not->toContain('USDT')
            ->and($symbols)->not->toContain('WBTC');

        // Each returned token should only have the 'base' network
        foreach ($data['data'] as $token) {
            expect($token['networks'])->toBe(['base']);
        }
    });
});

describe('MobileWalletController balances', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array']);
        $this->createSolanaTestTables();
    });

    afterEach(function (): void {
        $this->dropSolanaTestTables();
    });

    it('queries balances across smart accounts', function (): void {
        $account = (object) [
            'account_address' => '0xabc123',
            'network'         => 'polygon',
        ];
        $this->smartAccountService->shouldReceive('getUserAccounts')->andReturn(new Collection([$account]));
        $this->balanceService->shouldReceive('isTokenSupported')->andReturn(true);
        $this->balanceService->shouldReceive('getBalance')->andReturn('100.50');

        $controller = makeWalletController($this);

        $response = $controller->balances(walletUserRequest('/api/v1/wallet/balances'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toBeArray()
            ->and(count($data['data']))->toBeGreaterThan(0)
            ->and($data['data'][0])->toHaveKeys(['token', 'network', 'address', 'balance']);
    });

    it('handles balance query failure gracefully', function (): void {
        $account = (object) [
            'account_address' => '0xabc123',
            'network'         => 'polygon',
        ];
        $this->smartAccountService->shouldReceive('getUserAccounts')->andReturn(new Collection([$account]));
        $this->balanceService->shouldReceive('isTokenSupported')->andReturn(true);
        $this->balanceService->shouldReceive('getBalance')->andThrow(new RuntimeException('RPC error'));

        $controller = makeWalletController($this);

        $response = $controller->balances(walletUserRequest('/api/v1/wallet/balances'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'][0]['balance'])->toBe('0')
            ->and($data['data'][0])->toHaveKey('error');
    });
});

describe('MobileWalletController state', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array']);
        $this->createSolanaTestTables();
    });

    afterEach(function (): void {
        $this->dropSolanaTestTables();
    });

    it('returns aggregated wallet state', function (): void {
        $account = (object) [
            'account_address' => '0xabc123',
            'network'         => 'polygon',
            'is_deployed'     => true,
        ];
        $this->smartAccountService->shouldReceive('getUserAccounts')->andReturn(new Collection([$account]));
        $this->smartAccountService->shouldReceive('getSupportedNetworks')->andReturn(['polygon', 'base']);
        $this->balanceService->shouldReceive('isTokenSupported')->andReturn(true);
        $this->balanceService->shouldReceive('getBalance')->andReturn('100.50');

        $controller = makeWalletController($this);

        $response = $controller->state(walletUserRequest('/api/v1/wallet/state'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKeys(['addresses', 'networks', 'synced_at', 'account_count'])
            ->and($data['data']['account_count'])->toBe(1)
            ->and($data['data']['addresses'][0]['deployed'])->toBeTrue();
    });
});

describe('MobileWalletController addresses', function (): void {
    beforeEach(function (): void {
        $this->createSolanaTestTables();
    });

    afterEach(function (): void {
        $this->dropSolanaTestTables();
    });

    it('lists Privy-registered addresses for the user', function (): void {
        // The test user_uuid must match what walletUserRequest generates per-call.
        // We capture it by issuing the request first, then seeding rows for that uuid.
        $request = walletUserRequest('/api/v1/wallet/addresses');
        $user = $request->user();
        if (! $user instanceof User) {
            throw new RuntimeException('Test setup did not produce a user.');
        }
        $userUuid = $user->uuid;

        BlockchainAddress::create([
            'user_uuid'  => $userUuid,
            'chain'      => 'polygon',
            'address'    => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            'public_key' => '0x742d35cc6634c0532925a3b844bc454e4438f44e',
            'is_active'  => true,
            'metadata'   => ['provider' => 'privy', 'wallet_kind' => 'privy_smart_account'],
        ]);
        BlockchainAddress::create([
            'user_uuid'  => $userUuid,
            'chain'      => 'solana',
            'address'    => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'public_key' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'is_active'  => true,
            'metadata'   => ['provider' => 'privy', 'wallet_kind' => 'privy_embedded_solana'],
        ]);

        $controller = makeWalletController($this);

        $response = $controller->addresses($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveCount(2);

        $byNetwork = collect($data['data'])->keyBy('network');
        expect($byNetwork['polygon']['address'])->toBe('0x742d35cc6634c0532925a3b844bc454e4438f44e')
            ->and($byNetwork['polygon']['type'])->toBe('smart_account')
            ->and($byNetwork['solana']['address'])->toBe('EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z')
            ->and($byNetwork['solana']['type'])->toBe('keypair');
    });

    it('returns an empty list when the user has registered no addresses yet', function (): void {
        $controller = makeWalletController($this);

        $response = $controller->addresses(walletUserRequest('/api/v1/wallet/addresses'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toBe([]);
    });
});

describe('MobileWalletController transactions', function (): void {
    it('returns cursor-based transaction list', function (): void {
        $this->activityFeedService->shouldReceive('getFeed')
            ->andReturn([
                'items'       => [],
                'next_cursor' => null,
                'has_more'    => false,
            ]);

        $controller = makeWalletController($this);

        $response = $controller->transactions(walletUserRequest('/api/v1/wallet/transactions'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKeys(['items', 'next_cursor', 'has_more']);
    });
});

describe('MobileWalletController transactionDetail', function (): void {
    it('returns transaction detail for existing transaction', function (): void {
        $this->transactionDetailService->shouldReceive('getDetails')
            ->with('tx-123', 1)
            ->andReturn([
                'id'     => 'tx-123',
                'status' => 'confirmed',
                'amount' => '50.00',
            ]);

        $controller = makeWalletController($this);

        $response = $controller->transactionDetail('tx-123', walletUserRequest('/api/v1/wallet/transactions/tx-123'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data']['id'])->toBe('tx-123');
    });

    it('returns 404 for non-existent transaction', function (): void {
        $this->transactionDetailService->shouldReceive('getDetails')
            ->with('tx-999', 1)
            ->andReturn(null);

        $controller = makeWalletController($this);

        $response = $controller->transactionDetail('tx-999', walletUserRequest('/api/v1/wallet/transactions/tx-999'));

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('Wallet routes', function (): void {
    it('has wallet tokens route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.tokens');
        expect($route)->not->toBeNull();
    });

    it('has wallet balances route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.balances');
        expect($route)->not->toBeNull();
    });

    it('has wallet state route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.state');
        expect($route)->not->toBeNull();
    });

    it('has wallet transactions prepare route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.transactions.prepare');
        expect($route)->not->toBeNull();
    });

    it('has wallet transactions submit route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.transactions.submit');
        expect($route)->not->toBeNull();
    });

    it('has wallet addresses register route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.addresses.register');
        expect($route)->not->toBeNull();
    });
});
