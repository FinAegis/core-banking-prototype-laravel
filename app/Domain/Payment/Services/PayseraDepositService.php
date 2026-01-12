<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Production Paysera Deposit Service.
 *
 * Integrates with Paysera payment gateway for EUR SEPA deposits.
 */
class PayseraDepositService implements PayseraDepositServiceInterface
{
    private const API_URL = 'https://www.paysera.com/pay/';

    public function __construct(
        private readonly PaymentServiceInterface $paymentService
    ) {
    }

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
    public function initiateDeposit(array $data): array
    {
        $orderId = 'PAY-' . strtoupper(uniqid());

        Log::info('Initiating Paysera deposit', [
            'order_id'     => $orderId,
            'account_uuid' => $data['account_uuid'],
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
        ]);

        // Store order in cache for callback verification
        $orderData = [
            'order_id'     => $orderId,
            'account_uuid' => $data['account_uuid'],
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
            'user_id'      => $data['user_id'],
            'status'       => 'pending',
            'created_at'   => now()->toIso8601String(),
            'updated_at'   => now()->toIso8601String(),
        ];

        Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

        // Build Paysera redirect URL
        $projectId = config('services.paysera.project_id', '');
        $signPassword = config('services.paysera.sign_password', '');

        $payseraData = [
            'projectid'   => $projectId,
            'orderid'     => $orderId,
            'amount'      => $data['amount'],
            'currency'    => $data['currency'],
            'accepturl'   => $data['return_url'] ?? route('paysera.callback'),
            'cancelurl'   => $data['cancel_url'] ?? route('paysera.callback') . '?cancelled=1',
            'callbackurl' => route('paysera.callback'),
            'payment'     => 'card,bank',
            'country'     => 'LT',
            'paytext'     => $data['description'] ?? 'Deposit to FinAegis',
            'test'        => app()->environment('production') ? 0 : 1,
        ];

        // Create encoded data and sign
        $encodedData = base64_encode(http_build_query($payseraData));
        $sign = md5($encodedData . $signPassword);

        $redirectUrl = self::API_URL . '?' . http_build_query([
            'data' => $encodedData,
            'sign' => $sign,
        ]);

        return [
            'redirect_url' => $redirectUrl,
            'order_id'     => $orderId,
            'status'       => 'pending',
        ];
    }

    /**
     * Handle Paysera callback after payment.
     *
     * @param array{
     *     order_id: string,
     *     status: string,
     *     amount?: int,
     *     currency?: string,
     *     payment_type?: string,
     *     transaction_id?: string
     * } $callbackData
     * @return array{
     *     success: bool,
     *     message: string,
     *     reference?: string
     * }
     */
    public function handleCallback(array $callbackData): array
    {
        $orderId = $callbackData['order_id'];
        $status = $callbackData['status'];

        Log::info('Processing Paysera callback', [
            'order_id' => $orderId,
            'status'   => $status,
        ]);

        // Retrieve stored order data
        /** @var array{account_uuid: string, amount: int, currency: string, user_id: int, status: string}|null $orderData */
        $orderData = Cache::get("paysera_order:{$orderId}");

        if (! $orderData) {
            Log::error('Paysera order not found', ['order_id' => $orderId]);

            return [
                'success' => false,
                'message' => 'Order not found',
            ];
        }

        if ($status === 'completed' || $status === 'confirmed') {
            try {
                $reference = 'DEP-' . strtoupper(uniqid());

                // Process the deposit through payment service
                $this->paymentService->processStripeDeposit([
                    'account_uuid'        => $orderData['account_uuid'],
                    'amount'              => $callbackData['amount'] ?? $orderData['amount'],
                    'currency'            => $callbackData['currency'] ?? $orderData['currency'],
                    'reference'           => $reference,
                    'external_reference'  => $callbackData['transaction_id'] ?? $orderId,
                    'payment_method'      => 'paysera',
                    'payment_method_type' => $callbackData['payment_type'] ?? 'bank_transfer',
                    'metadata'            => [
                        'paysera_order_id'    => $orderId,
                        'paysera_transaction' => $callbackData['transaction_id'] ?? null,
                        'payment_type'        => $callbackData['payment_type'] ?? null,
                    ],
                ]);

                // Update order status
                $orderData['status'] = 'completed';
                $orderData['updated_at'] = now()->toIso8601String();
                Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

                return [
                    'success'   => true,
                    'message'   => 'Deposit processed successfully',
                    'reference' => $reference,
                ];
            } catch (Exception $e) {
                Log::error('Paysera deposit processing failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Deposit processing failed: ' . $e->getMessage(),
                ];
            }
        }

        // Payment was cancelled or failed
        $orderData['status'] = $status;
        $orderData['updated_at'] = now()->toIso8601String();
        Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

        return [
            'success' => false,
            'message' => "Payment {$status}",
        ];
    }

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
    public function getOrderStatus(string $orderId): ?array
    {
        /** @var array{order_id: string, status: string, amount: int, currency: string, created_at: string, updated_at: string}|null $orderData */
        $orderData = Cache::get("paysera_order:{$orderId}");

        return $orderData;
    }

    /**
     * Cancel a pending Paysera order.
     */
    public function cancelOrder(string $orderId): bool
    {
        /** @var array<string, mixed>|null $orderData */
        $orderData = Cache::get("paysera_order:{$orderId}");

        if (! $orderData) {
            return false;
        }

        if ($orderData['status'] !== 'pending') {
            return false;
        }

        $orderData['status'] = 'cancelled';
        $orderData['updated_at'] = now()->toIso8601String();
        Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

        Log::info('Paysera order cancelled', ['order_id' => $orderId]);

        return true;
    }
}
