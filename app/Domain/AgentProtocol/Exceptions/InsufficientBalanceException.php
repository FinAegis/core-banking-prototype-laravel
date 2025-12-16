<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Exceptions;

use Throwable;

/**
 * Exception thrown when a wallet has insufficient balance.
 *
 * This exception is used when attempting to perform a transaction
 * that exceeds the available wallet balance.
 */
class InsufficientBalanceException extends AgentProtocolException
{
    private string $walletId;

    private float $requestedAmount;

    private float $availableBalance;

    private string $currency;

    /**
     * Create a new insufficient balance exception.
     *
     * @param string $walletId The wallet identifier
     * @param float $requestedAmount The requested transaction amount
     * @param float $availableBalance The available wallet balance
     * @param string $currency The currency code
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $walletId,
        float $requestedAmount,
        float $availableBalance,
        string $currency = 'USD',
        ?Throwable $previous = null
    ) {
        $this->walletId = $walletId;
        $this->requestedAmount = $requestedAmount;
        $this->availableBalance = $availableBalance;
        $this->currency = $currency;

        $shortage = $requestedAmount - $availableBalance;

        parent::__construct(
            sprintf(
                'Insufficient balance in wallet %s: requested %.2f %s, available %.2f %s (shortage: %.2f %s)',
                $walletId,
                $requestedAmount,
                $currency,
                $availableBalance,
                $currency,
                $shortage,
                $currency
            ),
            402,
            $previous
        );
    }

    /**
     * Create exception for escrow funding failure.
     *
     * @param string $walletId The wallet identifier
     * @param float $escrowAmount The escrow amount
     * @param float $availableBalance The available balance
     * @param string $currency The currency code
     * @return self
     */
    public static function forEscrow(
        string $walletId,
        float $escrowAmount,
        float $availableBalance,
        string $currency = 'USD'
    ): self {
        return new self($walletId, $escrowAmount, $availableBalance, $currency);
    }

    /**
     * Get the wallet ID.
     *
     * @return string
     */
    public function getWalletId(): string
    {
        return $this->walletId;
    }

    /**
     * Get the requested amount.
     *
     * @return float
     */
    public function getRequestedAmount(): float
    {
        return $this->requestedAmount;
    }

    /**
     * Get the available balance.
     *
     * @return float
     */
    public function getAvailableBalance(): float
    {
        return $this->availableBalance;
    }

    /**
     * Get the currency code.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get the shortage amount.
     *
     * @return float
     */
    public function getShortage(): float
    {
        return $this->requestedAmount - $this->availableBalance;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorType(): string
    {
        return 'insufficient_balance';
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return [
            'wallet_id'         => $this->walletId,
            'requested_amount'  => $this->requestedAmount,
            'available_balance' => $this->availableBalance,
            'currency'          => $this->currency,
            'shortage'          => $this->getShortage(),
        ];
    }
}
