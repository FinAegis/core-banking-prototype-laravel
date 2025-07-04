<?php

namespace App\Domain\Exchange\Contracts;

use Brick\Math\BigDecimal;

interface FeeCalculatorInterface
{
    /**
     * Calculate trading fees for both maker and taker
     *
     * @param BigDecimal $amount
     * @param BigDecimal $price
     * @param string $takerAccountId
     * @param string $makerAccountId
     * @return object
     */
    public function calculateFees(
        BigDecimal $amount,
        BigDecimal $price,
        string $takerAccountId,
        string $makerAccountId
    ): object;

    /**
     * Calculate minimum order value
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @return BigDecimal
     */
    public function calculateMinimumOrderValue(string $baseCurrency, string $quoteCurrency): BigDecimal;
}