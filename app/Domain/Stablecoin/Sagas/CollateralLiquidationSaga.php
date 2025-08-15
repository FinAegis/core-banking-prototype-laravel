<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Sagas;

use App\Domain\Stablecoin\Aggregates\CollateralPositionAggregate;
use App\Domain\Stablecoin\Events\CollateralLiquidationStarted;
use App\Domain\Stablecoin\Events\MarginCallIssued;
use App\Domain\Stablecoin\Services\LiquidationAuctionService;
use App\Domain\Stablecoin\Services\PriceOracleService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;

class CollateralLiquidationSaga extends Reactor
{
    public function __construct(
        private readonly LiquidationAuctionService $auctionService,
        private readonly PriceOracleService $priceOracle
    ) {
    }

    /**
     * Step 1: Handle margin call issued.
     */
    public function onMarginCallIssued(MarginCallIssued $event): void
    {
        Log::info('Margin call issued for position', [
            'position_id'    => $event->positionId,
            'owner_id'       => $event->ownerId,
            'current_ratio'  => $event->currentRatio,
            'required_ratio' => $event->requiredRatio,
        ]);

        // Send notification to position owner
        $this->notifyOwner($event->ownerId, 'margin_call', [
            'position_id'     => $event->positionId,
            'time_to_respond' => $event->timeToRespond,
            'current_ratio'   => $event->currentRatio,
            'required_ratio'  => $event->requiredRatio,
        ]);

        // Schedule automatic liquidation check after response period
        $this->scheduleAutomaticLiquidationCheck(
            $event->positionId,
            $event->timeToRespond
        );
    }

    /**
     * Step 2: Handle liquidation started.
     */
    public function onCollateralLiquidationStarted(CollateralLiquidationStarted $event): void
    {
        Log::info('Liquidation started for position', [
            'position_id'      => $event->positionId,
            'collateral_value' => $event->collateralValue,
            'debt_amount'      => $event->debtAmount,
        ]);

        try {
            // Step 2.1: Freeze the position to prevent modifications
            $this->freezePosition($event->positionId);

            // Step 2.2: Get current collateral prices from oracle
            $currentPrices = $this->priceOracle->getCurrentPrices();

            // Step 2.3: Calculate liquidation penalty (typically 10-15%)
            $liquidationPenalty = $this->calculateLiquidationPenalty(
                BigDecimal::of($event->debtAmount)
            );

            // Step 2.4: Start auction process with current market prices
            $auctionId = $this->auctionService->startAuction(
                $event->positionId,
                $event->collateralValue,
                $event->debtAmount + $liquidationPenalty->toFloat(),
                $currentPrices // Pass current prices to auction service
            );

            // Step 2.5: Notify potential liquidators
            $this->notifyLiquidators($auctionId, [
                'position_id'      => $event->positionId,
                'collateral_value' => $event->collateralValue,
                'minimum_bid'      => $event->debtAmount + $liquidationPenalty->toFloat(),
            ]);

            // Step 2.6: Set auction timeout (typically 1 hour)
            $this->scheduleAuctionTimeout($auctionId, 3600);
        } catch (\Exception $e) {
            Log::error('Failed to start liquidation auction', [
                'position_id' => $event->positionId,
                'error'       => $e->getMessage(),
            ]);

            // Compensate by unfreezing position
            $this->unfreezePosition($event->positionId);

            // Retry liquidation with exponential backoff
            $this->retryLiquidation($event->positionId);
        }
    }

    /**
     * Step 3: Execute liquidation when auction completes.
     */
    public function executeLiquidation(string $positionId, string $auctionId): void
    {
        try {
            // Step 3.1: Get auction results
            $auctionResult = $this->auctionService->getAuctionResult($auctionId);

            if (! $auctionResult->hasWinner()) {
                // No bids received, use backstop liquidation
                $this->executeBackstopLiquidation($positionId);

                return;
            }

            // Step 3.2: Transfer collateral to winning bidder
            $collateralTransferred = $this->transferCollateralToBidder(
                $positionId,
                $auctionResult->getWinnerId(),
                $auctionResult->getCollateralAmount()
            );

            // Step 3.3: Use bid amount to repay debt
            $debtRepaid = $this->repayDebt(
                $positionId,
                BigDecimal::of($auctionResult->getBidAmount())
            );

            // Step 3.4: Calculate remaining debt (if any)
            $remainingDebt = $this->calculateRemainingDebt(
                $positionId,
                $debtRepaid
            );

            // Step 3.5: Return excess collateral to owner (if any)
            if ($auctionResult->hasExcessCollateral()) {
                $this->returnExcessCollateral(
                    $positionId,
                    $auctionResult->getExcessCollateral()
                );
            }

            // Step 3.6: Complete liquidation
            $aggregate = CollateralPositionAggregate::retrieve($positionId);
            $aggregate->completeLiquidation(
                BigDecimal::of($auctionResult->getBidAmount()),
                $remainingDebt,
                [
                    'auction_id'             => $auctionId,
                    'winner_id'              => $auctionResult->getWinnerId(),
                    'collateral_transferred' => $collateralTransferred,
                    'debt_repaid'            => $debtRepaid->toFloat(),
                    'excess_returned'        => $auctionResult->getExcessCollateral(),
                ]
            );
            $aggregate->persist();

            Log::info('Liquidation completed successfully', [
                'position_id'    => $positionId,
                'auction_id'     => $auctionId,
                'remaining_debt' => $remainingDebt->toFloat(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to execute liquidation', [
                'position_id' => $positionId,
                'auction_id'  => $auctionId,
                'error'       => $e->getMessage(),
            ]);

            // Compensate by rolling back transfers
            $this->compensateLiquidation($positionId, $auctionId);
        }
    }

    /**
     * Backstop liquidation when no bids received.
     */
    private function executeBackstopLiquidation(string $positionId): void
    {
        Log::warning('Executing backstop liquidation', ['position_id' => $positionId]);

        // Use protocol reserves to cover debt
        $aggregate = CollateralPositionAggregate::retrieve($positionId);

        // Transfer collateral to protocol treasury
        $this->transferCollateralToTreasury($positionId);

        // Mark position as liquidated with bad debt
        $aggregate->completeLiquidation(
            BigDecimal::zero(),
            BigDecimal::zero(),
            [
                'type'           => 'backstop',
                'transferred_to' => 'treasury',
                'timestamp'      => now(),
            ]
        );
        $aggregate->persist();
    }

    /**
     * Compensation logic if liquidation fails.
     */
    private function compensateLiquidation(string $positionId, string $auctionId): void
    {
        Log::info('Compensating failed liquidation', [
            'position_id' => $positionId,
            'auction_id'  => $auctionId,
        ]);

        // Step 1: Cancel the auction
        $this->auctionService->cancelAuction($auctionId);

        // Step 2: Unfreeze the position
        $this->unfreezePosition($positionId);

        // Step 3: Revert any partial transfers
        $this->revertTransfers($positionId);

        // Step 4: Notify owner of failed liquidation
        $this->notifyOwner($positionId, 'liquidation_failed', [
            'reason'          => 'System error during liquidation',
            'action_required' => 'Please add collateral or reduce debt',
        ]);
    }

    private function calculateLiquidationPenalty(BigDecimal $debtAmount): BigDecimal
    {
        // 13% liquidation penalty (industry standard)
        return $debtAmount->multipliedBy('0.13');
    }

    private function freezePosition(string $positionId): void
    {
        // Implementation would freeze the position in database
        Log::info('Position frozen', ['position_id' => $positionId]);
    }

    private function unfreezePosition(string $positionId): void
    {
        // Implementation would unfreeze the position
        Log::info('Position unfrozen', ['position_id' => $positionId]);
    }

    private function notifyOwner(string $ownerId, string $type, array $data): void
    {
        // Implementation would send notification via email/SMS/push
        Log::info('Owner notified', [
            'owner_id' => $ownerId,
            'type'     => $type,
            'data'     => $data,
        ]);
    }

    private function notifyLiquidators(string $auctionId, array $details): void
    {
        // Broadcast to registered liquidators
        Log::info('Liquidators notified of auction', [
            'auction_id' => $auctionId,
            'details'    => $details,
        ]);
    }

    private function scheduleAutomaticLiquidationCheck(string $positionId, int $hours): void
    {
        // Schedule job to check position after margin call period
        Log::info('Scheduled liquidation check', [
            'position_id'       => $positionId,
            'check_after_hours' => $hours,
        ]);
    }

    private function scheduleAuctionTimeout(string $auctionId, int $seconds): void
    {
        // Schedule auction to close after timeout
        Log::info('Scheduled auction timeout', [
            'auction_id'      => $auctionId,
            'timeout_seconds' => $seconds,
        ]);
    }

    private function transferCollateralToBidder(
        string $positionId,
        string $bidderId,
        array $collateral
    ): array {
        // Implementation would transfer collateral ownership
        Log::info('Collateral transferred to bidder', [
            'position_id' => $positionId,
            'bidder_id'   => $bidderId,
            'collateral'  => $collateral,
        ]);

        return $collateral;
    }

    private function repayDebt(string $positionId, BigDecimal $amount): BigDecimal
    {
        // Implementation would repay the debt
        Log::info('Debt repaid', [
            'position_id' => $positionId,
            'amount'      => $amount->toFloat(),
        ]);

        return $amount;
    }

    private function calculateRemainingDebt(string $positionId, BigDecimal $repaid): BigDecimal
    {
        // Get original debt and subtract repaid amount
        return BigDecimal::zero(); // Simplified for now
    }

    private function returnExcessCollateral(string $positionId, array $excess): void
    {
        Log::info('Returning excess collateral', [
            'position_id' => $positionId,
            'excess'      => $excess,
        ]);
    }

    private function transferCollateralToTreasury(string $positionId): void
    {
        Log::info('Collateral transferred to treasury', ['position_id' => $positionId]);
    }

    private function revertTransfers(string $positionId): void
    {
        Log::info('Reverting transfers', ['position_id' => $positionId]);
    }

    private function retryLiquidation(string $positionId): void
    {
        Log::info('Retrying liquidation', ['position_id' => $positionId]);
    }
}
