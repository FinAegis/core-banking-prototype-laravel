<?php

declare(strict_types=1);

namespace App\Domain\CrossChain\Services\Adapters;

use App\Domain\CrossChain\Contracts\BridgeAdapterInterface;
use App\Domain\CrossChain\Enums\BridgeProvider;
use App\Domain\CrossChain\Enums\BridgeStatus;
use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\CrossChain\ValueObjects\BridgeQuote;
use App\Domain\CrossChain\ValueObjects\BridgeRoute;
use App\Support\DecimalString;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Demo bridge adapter that simulates bridge transfers with configurable delays and success rates.
 */
class DemoBridgeAdapter implements BridgeAdapterInterface
{
    /** @var array<string, BridgeStatus> */
    private array $transactionStatuses = [];

    public function getProvider(): BridgeProvider
    {
        return BridgeProvider::DEMO;
    }

    public function estimateFee(
        CrossChainNetwork $sourceChain,
        CrossChainNetwork $destChain,
        string $token,
        string $amount,
    ): array {
        $feePercentage = DecimalString::fromNumeric(config('crosschain.demo.fee_percentage', 0.1), 18) ?? '0';
        $fee = bcmul($amount, bcdiv($feePercentage, '100', 20), 8);

        return [
            'fee'            => $fee,
            'fee_currency'   => $token,
            'estimated_time' => (int) config('crosschain.demo.simulated_delay_seconds', 5),
        ];
    }

    public function getQuote(
        CrossChainNetwork $sourceChain,
        CrossChainNetwork $destChain,
        string $token,
        string $amount,
    ): BridgeQuote {
        $feeData = $this->estimateFee($sourceChain, $destChain, $token, $amount);
        $outputAmount = bcsub($amount, $feeData['fee'], 8);

        $route = new BridgeRoute(
            $sourceChain,
            $destChain,
            $token,
            BridgeProvider::DEMO,
            $feeData['estimated_time'],
            $feeData['fee'],
        );

        return new BridgeQuote(
            quoteId: 'demo-quote-' . Str::uuid()->toString(),
            route: $route,
            inputAmount: $amount,
            outputAmount: $outputAmount,
            fee: $feeData['fee'],
            feeCurrency: $token,
            estimatedTimeSeconds: $feeData['estimated_time'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) config('crosschain.fees.quote_ttl_seconds', 300)),
        );
    }

    public function initiateBridge(
        BridgeQuote $quote,
        string $senderAddress,
        string $recipientAddress,
    ): array {
        $transactionId = 'demo-bridge-' . Str::uuid()->toString();
        $this->transactionStatuses[$transactionId] = BridgeStatus::INITIATED;

        return [
            'transaction_id' => $transactionId,
            'status'         => BridgeStatus::INITIATED,
            'source_tx_hash' => '0x' . Str::random(64),
        ];
    }

    public function getBridgeStatus(string $transactionId): array
    {
        $status = $this->transactionStatuses[$transactionId] ?? BridgeStatus::FAILED;

        return [
            'status'         => $status,
            'source_tx_hash' => '0x' . md5($transactionId . 'source'),
            'dest_tx_hash'   => $status === BridgeStatus::COMPLETED ? '0x' . md5($transactionId . 'dest') : null,
            'confirmations'  => $status === BridgeStatus::COMPLETED ? 15 : 0,
        ];
    }

    public function getSupportedRoutes(): array
    {
        $evmChains = [
            CrossChainNetwork::ETHEREUM,
            CrossChainNetwork::POLYGON,
            CrossChainNetwork::ARBITRUM,
            CrossChainNetwork::OPTIMISM,
            CrossChainNetwork::BASE,
            CrossChainNetwork::BSC,
        ];

        $routes = [];
        $tokens = ['USDC', 'USDT', 'WETH'];

        foreach ($evmChains as $source) {
            foreach ($evmChains as $dest) {
                if ($source === $dest) {
                    continue;
                }
                foreach ($tokens as $token) {
                    $routes[] = new BridgeRoute(
                        $source,
                        $dest,
                        $token,
                        BridgeProvider::DEMO,
                        (int) config('crosschain.demo.simulated_delay_seconds', 5),
                        '0.10',
                    );
                }
            }
        }

        return $routes;
    }

    public function supportsRoute(CrossChainNetwork $source, CrossChainNetwork $dest, string $token): bool
    {
        return $source->isEvm() && $dest->isEvm() && $source !== $dest;
    }
}
