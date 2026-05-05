<?php

declare(strict_types=1);

use App\Domain\MobilePayment\Services\ActivityFeedService;
use App\Domain\MobilePayment\Services\TransactionDetailService;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\Services\WalletBalanceService;
use App\Domain\Wallet\Services\WalletTransferService;
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
    $this->walletTransferService = Mockery::mock(WalletTransferService::class);
});

function makeWalletController($test): MobileWalletController
{
    return new MobileWalletController(
        $test->balanceService,
        $test->smartAccountService,
        $test->activityFeedService,
        $test->transactionDetailService,
        $test->walletTransferService,
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
    it('acknowledges a Solana wallet send with the mobile contract shape', function (): void {
        $this->walletTransferService->shouldReceive('validateAddress')
            ->once()
            ->with('EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z', 'SOLANA')
            ->andReturn([
                'valid'        => true,
                'network'      => 'SOLANA',
                'address'      => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
                'address_type' => 'wallet',
                'error'        => null,
            ]);

        $controller = makeWalletController($this);

        // Exact payload mobile sends today (PR #350 — both `asset` and `token`).
        $request = walletUserRequest('/api/v1/wallet/transactions/send', 'POST', [
            'to'       => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'amount'   => '1',
            'asset'    => 'USDC',
            'token'    => 'USDC',
            'network'  => 'SOLANA',
            'quote_id' => 'quote_Qhviaxl78795XhdmPTXA',
        ]);

        $response = $controller->send($request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(201)
            ->and($data['success'])->toBeTrue()
            ->and($data['data']['intentId'])->toStartWith('pi_send_')
            ->and($data['data']['status'])->toBe('PENDING')
            ->and($data['data']['merchantId'])->toBeNull()
            ->and($data['data']['merchant'])->toBeNull()
            ->and($data['data']['asset'])->toBe('USDC')
            ->and($data['data']['network'])->toBe('SOLANA')
            ->and($data['data']['amount'])->toBe('1')
            ->and($data['data']['tx'])->toBeNull()
            ->and($data['data']['error'])->toBeNull()
            ->and($data['data']['quoteId'])->toBe('quote_Qhviaxl78795XhdmPTXA');
    });

    it('rejects an invalid recipient address with 422 and a structured error', function (): void {
        $bogus = str_repeat('Z', 44); // passes min:26 length but not the network format check
        $this->walletTransferService->shouldReceive('validateAddress')
            ->once()
            ->with($bogus, 'SOLANA')
            ->andReturn([
                'valid'        => false,
                'network'      => 'SOLANA',
                'address'      => $bogus,
                'address_type' => null,
                'error'        => 'Invalid Solana address format.',
            ]);

        $controller = makeWalletController($this);

        $request = walletUserRequest('/api/v1/wallet/transactions/send', 'POST', [
            'to'      => $bogus,
            'token'   => 'USDC',
            'amount'  => '1',
            'network' => 'SOLANA',
        ]);

        $response = $controller->send($request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(422)
            ->and($data['success'])->toBeFalse()
            ->and($data['error']['code'])->toBe('INVALID_RECIPIENT')
            ->and($data['error']['message'])->toContain('Invalid Solana address');
    });

    it('does NOT leak PHP runtime warnings into the API response', function (): void {
        // Regression: original bug surfaced "Undefined array key 'merchantId'"
        // from PaymentIntentService into the user-facing error.message. Even if
        // a downstream layer were to throw a similar warning, the controller
        // must scrub it before returning to the client.
        $this->walletTransferService->shouldReceive('validateAddress')
            ->once()
            ->andThrow(new ErrorException('Undefined array key "merchantId"'));

        $controller = makeWalletController($this);

        $request = walletUserRequest('/api/v1/wallet/transactions/send', 'POST', [
            'to'      => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'token'   => 'USDC',
            'amount'  => '1',
            'network' => 'SOLANA',
        ]);

        $response = $controller->send($request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(422)
            ->and($data['error']['code'])->toBe('SEND_FAILED')
            ->and($data['error']['message'])->not->toContain('merchantId')
            ->and($data['error']['message'])->not->toContain('Undefined array key')
            ->and($data['error']['message'])->toBe('Send could not be processed.');
    });

    it('returns the unsanitized message for non-runtime-warning failures', function (): void {
        // Domain exceptions (insufficient balance, etc.) carry intentional
        // user-facing copy and should pass through unchanged.
        $this->walletTransferService->shouldReceive('validateAddress')
            ->once()
            ->andThrow(new RuntimeException('Insufficient balance for this transfer.'));

        $controller = makeWalletController($this);

        $request = walletUserRequest('/api/v1/wallet/transactions/send', 'POST', [
            'to'      => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'token'   => 'USDC',
            'amount'  => '1000',
            'network' => 'SOLANA',
        ]);

        $response = $controller->send($request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(422)
            ->and($data['error']['message'])->toBe('Insufficient balance for this transfer.');
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
