<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Models\LiquidationAuction;
use App\Domain\Stablecoin\ValueObjects\AuctionResult;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LiquidationAuctionService
{
    /**
     * Start a new liquidation auction.
     */
    public function startAuction(
        string $positionId,
        float $collateralValue,
        float $minimumBid,
        ?array $currentPrices = null
    ): string {
        $auctionId = Str::uuid()->toString();

        // Store current prices in collateral field if provided
        $collateral = $currentPrices ?: [];

        LiquidationAuction::create([
            'auction_id'       => $auctionId,
            'position_id'      => $positionId,
            'collateral_value' => $collateralValue,
            'minimum_bid'      => $minimumBid,
            'collateral'       => $collateral,
            'status'           => 'active',
            'started_at'       => now(),
            'expires_at'       => now()->addHour(),
        ]);

        return $auctionId;
    }

    /**
     * Cancel an auction.
     */
    public function cancelAuction(string $auctionId): void
    {
        LiquidationAuction::where('auction_id', $auctionId)
            ->update(['status' => 'cancelled']);
    }

    /**
     * Get auction result.
     */
    public function getAuctionResult(string $auctionId): AuctionResult
    {
        $auction = LiquidationAuction::where('auction_id', $auctionId)->firstOrFail();

        $bids = $auction->bids()
            ->orderBy('amount', 'desc')
            ->get();

        if ($bids->isEmpty()) {
            return new AuctionResult(
                hasWinner: false,
                winnerId: null,
                bidAmount: 0,
                collateralAmount: [],
                excessCollateral: []
            );
        }

        $winningBid = $bids->first();

        return new AuctionResult(
            hasWinner: true,
            winnerId: $winningBid->bidder_id,
            bidAmount: $winningBid->amount,
            collateralAmount: $auction->collateral,
            excessCollateral: $this->calculateExcess($auction, $winningBid)
        );
    }

    /**
     * Place a bid on an auction.
     */
    public function placeBid(
        string $auctionId,
        string $bidderId,
        float $bidAmount
    ): void {
        $auction = LiquidationAuction::where('auction_id', $auctionId)
            ->where('status', 'active')
            ->firstOrFail();

        if ($bidAmount < $auction->minimum_bid) {
            throw new InvalidArgumentException('Bid amount below minimum');
        }

        $auction->bids()->create([
            'bidder_id' => $bidderId,
            'amount'    => $bidAmount,
            'placed_at' => now(),
        ]);
    }

    /**
     * Close an auction and determine winner.
     */
    public function closeAuction(string $auctionId): void
    {
        $auction = LiquidationAuction::where('auction_id', $auctionId)
            ->where('status', 'active')
            ->firstOrFail();

        $highestBid = $auction->bids()
            ->orderBy('amount', 'desc')
            ->first();

        if ($highestBid) {
            $auction->update([
                'status'       => 'completed',
                'winner_id'    => $highestBid->bidder_id,
                'winning_bid'  => $highestBid->amount,
                'completed_at' => now(),
            ]);
        } else {
            $auction->update([
                'status'       => 'no_bids',
                'completed_at' => now(),
            ]);
        }
    }

    private function calculateExcess($auction, $winningBid): array
    {
        // Calculate if there's excess collateral after covering debt + penalty
        $collateralValue = BigDecimal::of($auction->collateral_value);
        $bidAmount = BigDecimal::of($winningBid->amount);

        if ($collateralValue->isGreaterThan($bidAmount)) {
            $excessValue = $collateralValue->minus($bidAmount);

            return [
                'value'      => $excessValue->toFloat(),
                'percentage' => $excessValue->dividedBy($collateralValue, 4)->multipliedBy(100)->toFloat(),
            ];
        }

        return [];
    }
}
