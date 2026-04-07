<?php

declare(strict_types=1);

use App\Domain\CrossChain\Enums\BridgeProvider;
use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\CrossChain\Services\Adapters\DemoBridgeAdapter;
use App\Domain\CrossChain\Services\BridgeOrchestratorService;
use App\Domain\CrossChain\Services\BridgeTransactionTracker;
use App\Domain\CrossChain\Services\CrossChainSwapSaga;
use App\Domain\CrossChain\Services\CrossChainSwapService;
use App\Domain\DeFi\Services\Connectors\DemoSwapConnector;
use App\Domain\DeFi\Services\Connectors\UniswapV3Connector;
use App\Domain\DeFi\Services\SwapAggregatorService;
use App\Domain\DeFi\Services\SwapRouterService;
use App\Http\Controllers\Api\CrossChain\CrossChainController;
use App\Infrastructure\Web3\AbiEncoder;
use App\Infrastructure\Web3\EthRpcClient;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

function makePostRequest(string $uri, array $data): Request
{
    $json = json_encode($data);

    return Request::create($uri, 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT'  => 'application/json',
    ], $json);
}

beforeEach(function () {
    $this->bridgeOrchestrator = new BridgeOrchestratorService();
    $this->bridgeOrchestrator->registerAdapter(new DemoBridgeAdapter());

    $this->bridgeTracker = new BridgeTransactionTracker();

    $aggregator = new SwapAggregatorService();
    $aggregator->registerConnector(new DemoSwapConnector());
    $aggregator->registerConnector(new UniswapV3Connector(new AbiEncoder(), new EthRpcClient()));
    $swapRouter = new SwapRouterService($aggregator);

    $saga = new CrossChainSwapSaga($this->bridgeOrchestrator, $swapRouter, $this->bridgeTracker);
    $this->swapService = new CrossChainSwapService($this->bridgeOrchestrator, $swapRouter, $saga);

    $this->controller = new CrossChainController(
        $this->bridgeOrchestrator,
        $this->bridgeTracker,
        $this->swapService,
    );
});

describe('CrossChainController', function () {
    it('returns supported chains', function () {
        $response = $this->controller->chains();
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data'])->toBeArray();
    });

    it('returns bridge quotes', function () {
        $request = makePostRequest('/api/v1/crosschain/bridge/quote', [
            'from_chain' => 'ethereum',
            'to_chain'   => 'polygon',
            'token'      => 'USDC',
            'amount'     => '1000.00',
        ]);

        $response = $this->controller->bridgeQuote($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data'])->toBeArray();
        expect($data['data'][0])->toHaveKeys(['quote_id', 'input_amount', 'output_amount', 'fee']);
    });

    it('initiates a bridge transfer', function () {
        $user = App\Models\User::factory()->create();

        // Register a blockchain address so sender address verification passes
        App\Domain\Account\Models\BlockchainAddress::create([
            'uuid'       => Illuminate\Support\Str::uuid()->toString(),
            'user_uuid'  => $user->uuid,
            'chain'      => 'ethereum',
            'address'    => '0xSender',
            'public_key' => '0xSender',
            'is_active'  => true,
        ]);

        $request = makePostRequest('/api/v1/crosschain/bridge/initiate', [
            'from_chain'        => 'ethereum',
            'to_chain'          => 'polygon',
            'token'             => 'USDC',
            'amount'            => '500.00',
            'sender_address'    => '0xSender',
            'recipient_address' => '0xRecipient',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->bridgeInitiate($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveKeys(['transaction_id', 'status', 'quote']);
    });

    it('returns bridge transaction status', function () {
        $this->bridgeTracker->recordTransaction(
            'test_tx_123',
            CrossChainNetwork::ETHEREUM,
            CrossChainNetwork::POLYGON,
            'USDC',
            '500.00',
            BridgeProvider::DEMO,
            '0xSender',
            '0xRecipient',
        );

        $response = $this->controller->bridgeStatus('test_tx_123');
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveKey('transaction_id');
    });

    it('returns 404 for unknown bridge transaction', function () {
        $response = $this->controller->bridgeStatus('nonexistent_tx');
        $data = $response->getData(true);

        expect($data['success'])->toBeFalse();
        expect($response->getStatusCode())->toBe(404);
    });

    it('returns cross-chain swap quote', function () {
        $request = makePostRequest('/api/v1/crosschain/swap/quote', [
            'from_chain' => 'ethereum',
            'to_chain'   => 'polygon',
            'from_token' => 'USDC',
            'to_token'   => 'WETH',
            'amount'     => '1000.00',
        ]);

        $response = $this->controller->swapQuote($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveKeys([
            'quote_id', 'source_chain', 'dest_chain', 'input_token',
            'output_token', 'bridge_quote', 'total_fee',
        ]);
    });

    it('executes cross-chain swap', function () {
        $user = App\Models\User::factory()->create();

        App\Domain\Account\Models\BlockchainAddress::create([
            'uuid'       => Illuminate\Support\Str::uuid()->toString(),
            'user_uuid'  => $user->uuid,
            'chain'      => 'ethereum',
            'address'    => '0xTestWallet',
            'public_key' => '0xTestWallet',
            'is_active'  => true,
        ]);

        $request = makePostRequest('/api/v1/crosschain/swap/execute', [
            'from_chain'     => 'ethereum',
            'to_chain'       => 'polygon',
            'from_token'     => 'USDC',
            'to_token'       => 'WETH',
            'amount'         => '1000.00',
            'wallet_address' => '0xTestWallet',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->swapExecute($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveKeys(['bridge_tx', 'swap_tx', 'output_amount', 'status']);
        expect($data['data']['status'])->toBe('completed');
    });

    it('validates chain enum for invalid chain in bridge quote', function () {
        $request = makePostRequest('/api/v1/crosschain/bridge/quote', [
            'from_chain' => 'invalid_chain',
            'to_chain'   => 'polygon',
            'token'      => 'USDC',
            'amount'     => '1000.00',
        ]);

        $this->controller->bridgeQuote($request);
    })->throws(Illuminate\Validation\ValidationException::class);

    it('validates chain enum for invalid to_chain in swap execute', function () {
        $user = App\Models\User::factory()->create();
        $request = makePostRequest('/api/v1/crosschain/swap/execute', [
            'from_chain'     => 'ethereum',
            'to_chain'       => 'nonexistent',
            'from_token'     => 'USDC',
            'to_token'       => 'WETH',
            'amount'         => '1000.00',
            'wallet_address' => '0xTest',
        ]);
        $request->setUserResolver(fn () => $user);

        $this->controller->swapExecute($request);
    })->throws(Illuminate\Validation\ValidationException::class);

    it('returns bridge-only swap when tokens match', function () {
        $request = makePostRequest('/api/v1/crosschain/swap/quote', [
            'from_chain' => 'ethereum',
            'to_chain'   => 'arbitrum',
            'from_token' => 'USDC',
            'to_token'   => 'USDC',
            'amount'     => '2000.00',
        ]);

        $response = $this->controller->swapQuote($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
        expect($data['data']['swap_quote'])->toBeNull();
    });

    it('includes fee currency in swap quote', function () {
        $request = makePostRequest('/api/v1/crosschain/swap/quote', [
            'from_chain' => 'ethereum',
            'to_chain'   => 'polygon',
            'from_token' => 'USDC',
            'to_token'   => 'WETH',
            'amount'     => '1000.00',
        ]);

        $response = $this->controller->swapQuote($request);
        $data = $response->getData(true);

        expect($data['data'])->toHaveKey('fee_currency');
    });

    it('supports different chain combinations for bridge', function () {
        $request = makePostRequest('/api/v1/crosschain/bridge/quote', [
            'from_chain' => 'arbitrum',
            'to_chain'   => 'optimism',
            'token'      => 'USDC',
            'amount'     => '500.00',
        ]);

        $response = $this->controller->bridgeQuote($request);
        $data = $response->getData(true);

        expect($data['success'])->toBeTrue();
    });
});
