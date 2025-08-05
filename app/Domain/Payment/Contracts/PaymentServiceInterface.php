<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

interface PaymentServiceInterface
{
    /**
     * Process a Stripe deposit.
     *
     * @param array{
     *     account_uuid: string,
     *     amount: int,
     *     currency: string,
     *     reference: string,
     *     external_reference: string,
     *     payment_method: string,
     *     payment_method_type: string,
     *     metadata?: array
     * } $data
     */
    public function processStripeDeposit(array $data): string;

    /**
     * Process a bank withdrawal.
     *
     * @param array{
     *     account_uuid: string,
     *     amount: int,
     *     currency: string,
     *     reference: string,
     *     bank_name: string,
     *     account_number: string,
     *     account_holder_name: string,
     *     routing_number?: string|null,
     *     iban?: string|null,
     *     swift?: string|null,
     *     metadata?: array
     * } $data
     * @return array{reference: string, status: string}
     */
    public function processBankWithdrawal(array $data): array;

    /**
     * Process an OpenBanking deposit.
     *
     * @param array{
     *     account_uuid: string,
     *     amount: int,
     *     currency: string,
     *     reference: string,
     *     bank_name: string,
     *     metadata?: array
     * } $data
     */
    public function processOpenBankingDeposit(array $data): string;
}
