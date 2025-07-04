<?php

namespace App\Domain\Wallet\Contracts;

interface WalletServiceInterface
{
    /**
     * Deposit funds to an account for a specific asset
     *
     * @param mixed $accountUuid
     * @param string $assetCode
     * @param mixed $amount
     * @return void
     */
    public function deposit(mixed $accountUuid, string $assetCode, mixed $amount): void;

    /**
     * Withdraw funds from an account for a specific asset
     *
     * @param mixed $accountUuid
     * @param string $assetCode
     * @param mixed $amount
     * @return void
     */
    public function withdraw(mixed $accountUuid, string $assetCode, mixed $amount): void;

    /**
     * Transfer funds between accounts for a specific asset
     *
     * @param mixed $fromAccountUuid
     * @param mixed $toAccountUuid
     * @param string $assetCode
     * @param mixed $amount
     * @param string|null $reference
     * @return void
     */
    public function transfer(mixed $fromAccountUuid, mixed $toAccountUuid, string $assetCode, mixed $amount, ?string $reference = null): void;

    /**
     * Convert between different assets within the same account
     *
     * @param mixed $accountUuid
     * @param string $fromAssetCode
     * @param string $toAssetCode
     * @param mixed $amount
     * @return void
     */
    public function convert(mixed $accountUuid, string $fromAssetCode, string $toAssetCode, mixed $amount): void;
}