<?php

declare(strict_types=1);

namespace App\Domain\DeFi\Services\Connectors;

use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\DeFi\Contracts\SwapProtocolInterface;
use App\Domain\DeFi\Enums\DeFiProtocol;
use App\Domain\DeFi\ValueObjects\SwapQuote;
use App\Support\DecimalString;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class DemoSwapConnector implements SwapProtocolInterface
{
    public function getProtocol(): DeFiProtocol
    {
        return DeFiProtocol::DEMO;
    }

    public function getQuote(
        CrossChainNetwork $chain,
        string $fromToken,
        string $toToken,
        string $amount,
        float $slippageTolerance = 0.5,
    ): SwapQuote {
        $feeRate = DecimalString::fromNumeric(config('defi.demo.simulated_swap_fee', 0.003), 18) ?? '0';
        $fee = bcmul($amount, $feeRate, 8);
        $outputAmount = bcsub($amount, $fee, 8);

        return new SwapQuote(
            quoteId: 'demo-swap-' . Str::uuid()->toString(),
            chain: $chain,
            inputToken: $fromToken,
            outputToken: $toToken,
            inputAmount: $amount,
            outputAmount: $outputAmount,
            priceImpact: '0.05',
            protocol: DeFiProtocol::DEMO,
            gasEstimate: '0.50',
            feeTier: 3000,
            expiresAt: CarbonImmutable::now()->addSeconds((int) config('defi.swap.quote_ttl_seconds', 60)),
        );
    }

    public function executeSwap(SwapQuote $quote, string $walletAddress): array
    {
        return [
            'tx_hash'       => '0x' . Str::random(64),
            'input_amount'  => $quote->inputAmount,
            'output_amount' => $quote->outputAmount,
            'price_impact'  => $quote->priceImpact,
        ];
    }

    public function getSupportedPairs(CrossChainNetwork $chain): array
    {
        $tokens = ['USDC', 'USDT', 'WETH', 'WBTC', 'DAI'];
        $pairs = [];

        foreach ($tokens as $from) {
            foreach ($tokens as $to) {
                if ($from !== $to) {
                    $pairs[] = ['from' => $from, 'to' => $to, 'fee_tier' => 3000];
                }
            }
        }

        return $pairs;
    }
}
