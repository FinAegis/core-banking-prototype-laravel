<?php

declare(strict_types=1);

use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Domain\MobilePayment\Services\PaymentIntentService;
use App\Domain\MobilePayment\Services\TransactionDetailService;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\Services\WalletBalanceService;
use App\Http\Controllers\Api\Wallet\MobileWalletController;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function (): void {
    $this->balanceService = Mockery::mock(WalletBalanceService::class);
    $this->smartAccountService = Mockery::mock(SmartAccountService::class);
    $this->activityFeedService = Mockery::mock(ActivityFeedService::class);
    $this->transactionDetailService = Mockery::mock(TransactionDetailService::class);
    $this->paymentIntentService = Mockery::mock(PaymentIntentService::class);
});

function makeWalletController($test): MobileWalletController
{
    return new MobileWalletController(
        $test->balanceService,
        $test->smartAccountService,
        $test->activityFeedService,
        $test->transactionDetailService,
        $test->paymentIntentService,
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
    it('lists user addresses per network', function (): void {
        $account = (object) [
            'account_address' => '0xdef456',
            'network'         => 'base',
            'is_deployed'     => false,
            'created_at'      => now(),
        ];
        $this->smartAccountService->shouldReceive('getUserAccounts')->andReturn(new Collection([$account]));

        $controller = makeWalletController($this);

        $response = $controller->addresses(walletUserRequest('/api/v1/wallet/addresses'));
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toBeArray()
            ->and($data['data'][0])->toHaveKeys(['address', 'network', 'deployed', 'created_at'])
            ->and($data['data'][0]['address'])->toBe('0xdef456');
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

describe('MobileWalletController send', function (): void {
    it('creates and submits a payment intent', function (): void {
        $intentMock = Mockery::mock(PaymentIntent::class)->makePartial();
        $intentMock->public_id = 'pi-abc';

        $resultMock = Mockery::mock(PaymentIntent::class)->makePartial();
        $resultMock->shouldReceive('toApiResponse')->andReturn([
            'intentId' => 'pi-abc',
            'status'   => 'SUBMITTED',
            'tx'       => ['hash' => '0xdeadbeef'],
        ]);

        $this->paymentIntentService->shouldReceive('create')
            ->once()
            ->andReturn($intentMock);
        $this->paymentIntentService->shouldReceive('submit')
            ->with('pi-abc', 1, 'wallet')
            ->once()
            ->andReturn($resultMock);

        $controller = makeWalletController($this);

        $request = walletUserRequest('/api/v1/wallet/transactions/send', 'POST', [
            'to'      => '0xrecipient',
            'token'   => 'USDC',
            'amount'  => '25.00',
            'network' => 'polygon',
        ]);

        $response = $controller->send($request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(201)
            ->and($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKey('status');
    });

    it('returns 422 on send failure', function (): void {
        $this->paymentIntentService->shouldReceive('create')
            ->andThrow(new RuntimeException('Insufficient balance'));

        $controller = makeWalletController($this);

        $request = walletUserRequest('/api/v1/wallet/transactions/send', 'POST', [
            'to'      => '0xrecipient',
            'token'   => 'USDC',
            'amount'  => '25.00',
            'network' => 'polygon',
        ]);

        $response = $controller->send($request);

        expect($response->getStatusCode())->toBe(422);
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

    it('has wallet transactions send route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.wallet.transactions.send');
        expect($route)->not->toBeNull();
    });
});
