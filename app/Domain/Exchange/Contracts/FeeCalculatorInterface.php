<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Account\DataObjects\Money;

interface FeeCalculatorInterface
{
    /**
     * Calculate trading fee for an order
     *
     * @param string $orderType
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param string $amount
     * @param string $price
     * @param array $accountTier
     * @return array
     */
    public function calculateTradingFee(
        string $orderType,
        string $baseCurrency,
        string $quoteCurrency,
        string $amount,
        string $price,
        array $accountTier = []
    ): array;

    /**
     * Calculate withdrawal fee
     *
     * @param string $currency
     * @param string $amount
     * @param string $method
     * @return Money
     */
    public function calculateWithdrawalFee(
        string $currency,
        string $amount,
        string $method
    ): Money;

    /**
     * Calculate deposit fee
     *
     * @param string $currency
     * @param string $amount
     * @param string $method
     * @return Money
     */
    public function calculateDepositFee(
        string $currency,
        string $amount,
        string $method
    ): Money;

    /**
     * Calculate liquidity provider fee share
     *
     * @param string $poolId
     * @param string $tradingFee
     * @return array
     */
    public function calculateLPFeeShare(string $poolId, string $tradingFee): array;

    /**
     * Calculate minimum order value
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @return Money
     */
    public function calculateMinimumOrderValue(string $baseCurrency, string $quoteCurrency): Money;

    /**
     * Get fee tiers
     *
     * @return array
     */
    public function getFeeTiers(): array;

    /**
     * Get account fee tier
     *
     * @param string $accountId
     * @return array
     */
    public function getAccountFeeTier(string $accountId): array;
}