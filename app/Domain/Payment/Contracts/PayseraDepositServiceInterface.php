<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

/**
 * Contract for Paysera deposit operations.
 *
 * Implementations provide initiation and callback handling for Paysera
 * payment gateway deposits.
 */
interface PayseraDepositServiceInterface
{
    /**
     * Initiate a Paysera deposit.
     *
     * @param array{
     *     account_uuid: string,
     *     amount: int,
     *     currency: string,
     *     user_id: int,
     *     return_url?: string,
     *     cancel_url?: string,
     *     description?: string
     * } $data
     * @return array{
     *     redirect_url: string,
     *     order_id: string,
     *     status: string
     * }
     */
    public function initiateDeposit(array $data): array;

    /**
     * Handle Paysera callback after payment.
     *
     * Implementations credit the stored order amount only to the user who
     * started the order (user_id = the authenticated user), at most once.
     * The Paysera service credits only on Paysera's signed response
     * (paysera_data + paysera_ss1); the demo service simulates the payment.
     *
     * @param array{
     *     order_id: string,
     *     status: string,
     *     user_id: int,
     *     amount?: int,
     *     currency?: string,
     *     payment_type?: string,
     *     transaction_id?: string,
     *     paysera_data?: string,
     *     paysera_ss1?: string
     * } $callbackData
     * @return array{
     *     success: bool,
     *     message: string,
     *     reference?: string
     * }
     */
    public function handleCallback(array $callbackData): array;

    /**
     * Get the status of a Paysera order.
     *
     * @return array{
     *     order_id: string,
     *     status: string,
     *     amount: int,
     *     currency: string,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    public function getOrderStatus(string $orderId): ?array;

    /**
     * Cancel a pending Paysera order.
     */
    public function cancelOrder(string $orderId): bool;
}
