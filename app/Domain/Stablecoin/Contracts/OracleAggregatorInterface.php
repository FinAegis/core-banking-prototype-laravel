<?php

namespace App\Domain\Stablecoin\Contracts;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\ValueObjects\AggregatedPrice;

interface OracleAggregatorInterface
{
    /**
     * Register an oracle connector
     *
     * @param OracleConnector $oracle
     * @return self
     */
    public function registerOracle(OracleConnector $oracle): self;

    /**
     * Get aggregated price from multiple oracles
     *
     * @param string $base
     * @param string $quote
     * @return AggregatedPrice
     */
    public function getAggregatedPrice(string $base, string $quote): AggregatedPrice;
}