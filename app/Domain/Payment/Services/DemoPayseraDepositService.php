<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Demo Paysera Deposit Service.
 *
 * Simulates Paysera payment gateway operations for development and testing
 * without making actual API calls to Paysera.
 *
 * Demo Behaviors:
 * - order_id containing 'fail' will fail on callback
 * - order_id containing 'timeout' will simulate timeout
 * - All other orders succeed automatically
 * - Amounts are in cents (e.g., 1000 = €10.00)
 */
class DemoPayseraDepositService implements PayseraDepositServiceInterface
{
    /**
     * In-memory store for demo orders.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $demoOrders = [];

    public function __construct(
        private readonly PaymentServiceInterface $paymentService
    ) {
    }

    /**
     * Initiate a demo Paysera deposit.
     *
     * Returns a redirect URL to a simulated payment page.
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
        $orderId = 'DEMO-PAY-' . strtoupper(uniqid());

        Log::info('Initiating demo Paysera deposit', [
            'order_id'     => $orderId,
            'account_uuid' => $data['account_uuid'],
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
            'demo_mode'    => true,
        ]);

        // Store order data
        $orderData = [
            'order_id'     => $orderId,
            'account_uuid' => $data['account_uuid'],
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
            'user_id'      => $data['user_id'],
            'description'  => $data['description'] ?? 'Demo Paysera Deposit',
            'status'       => 'pending',
            'created_at'   => now()->toIso8601String(),
            'updated_at'   => now()->toIso8601String(),
            'demo_mode'    => true,
        ];

        // Store in both static array and cache for flexibility
        self::$demoOrders[$orderId] = $orderData;
        Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

        // Build demo redirect URL - in demo mode, redirect back to callback immediately
        $returnUrl = $data['return_url'] ?? route('paysera.callback');
        $redirectUrl = $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . http_build_query([
            'order_id'       => $orderId,
            'status'         => 'completed',
            'amount'         => $data['amount'],
            'currency'       => $data['currency'],
            'transaction_id' => 'DEMO-TX-' . uniqid(),
            'payment_type'   => 'demo_bank_transfer',
            'demo'           => '1',
        ]);

        return [
            'redirect_url' => $redirectUrl,
            'order_id'     => $orderId,
            'status'       => 'pending',
        ];
    }

    /**
     * Handle demo Paysera callback.
     *
     * Simulates callback processing:
     * - order_id containing 'fail' triggers failure
     * - All other orders process successfully
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

        Log::info('Processing demo Paysera callback', [
            'order_id'  => $orderId,
            'status'    => $status,
            'demo_mode' => true,
        ]);

        // Retrieve order data
        /** @var array{account_uuid: string, amount: int, currency: string, user_id: int, status: string, demo_mode?: bool}|null $orderData */
        $orderData = self::$demoOrders[$orderId] ?? Cache::get("paysera_order:{$orderId}");

        if (! $orderData) {
            // For demo mode, create order on the fly if not found
            $orderData = [
                'order_id'     => $orderId,
                'account_uuid' => 'demo-account-uuid',
                'amount'       => $callbackData['amount'] ?? 10000,
                'currency'     => $callbackData['currency'] ?? 'EUR',
                'user_id'      => 1,
                'status'       => 'pending',
                'demo_mode'    => true,
            ];
        }

        // Simulate failure for orders containing 'fail'
        if (str_contains(strtolower($orderId), 'fail')) {
            $orderData['status'] = 'failed';
            self::$demoOrders[$orderId] = $orderData;
            Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

            return [
                'success' => false,
                'message' => 'Demo payment failed (triggered by order_id)',
            ];
        }

        // Process successful payment
        if ($status === 'completed' || $status === 'confirmed' || ($orderData['demo_mode'] ?? false)) {
            try {
                $reference = 'DEMO-DEP-' . strtoupper(uniqid());

                // Process the deposit through payment service
                $this->paymentService->processStripeDeposit([
                    'account_uuid'        => $orderData['account_uuid'],
                    'amount'              => $callbackData['amount'] ?? $orderData['amount'],
                    'currency'            => $callbackData['currency'] ?? $orderData['currency'],
                    'reference'           => $reference,
                    'external_reference'  => $callbackData['transaction_id'] ?? 'DEMO-TX-' . uniqid(),
                    'payment_method'      => 'demo_paysera',
                    'payment_method_type' => $callbackData['payment_type'] ?? 'demo_bank_transfer',
                    'metadata'            => [
                        'paysera_order_id'    => $orderId,
                        'paysera_transaction' => $callbackData['transaction_id'] ?? null,
                        'payment_type'        => $callbackData['payment_type'] ?? 'demo',
                        'demo_mode'           => true,
                    ],
                ]);

                // Update order status
                $orderData['status'] = 'completed';
                $orderData['updated_at'] = now()->toIso8601String();
                self::$demoOrders[$orderId] = $orderData;
                Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

                Log::info('Demo Paysera deposit completed successfully', [
                    'order_id'  => $orderId,
                    'reference' => $reference,
                ]);

                return [
                    'success'   => true,
                    'message'   => 'Demo deposit processed successfully',
                    'reference' => $reference,
                ];
            } catch (Exception $e) {
                Log::error('Demo Paysera deposit processing failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Demo deposit processing failed: ' . $e->getMessage(),
                ];
            }
        }

        // Handle cancelled/failed status
        $orderData['status'] = $status;
        $orderData['updated_at'] = now()->toIso8601String();
        self::$demoOrders[$orderId] = $orderData;
        Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

        return [
            'success' => false,
            'message' => "Demo payment {$status}",
        ];
    }

    /**
     * Get the status of a demo Paysera order.
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
        $orderData = self::$demoOrders[$orderId] ?? Cache::get("paysera_order:{$orderId}");

        if (! $orderData) {
            // Return demo data for any order in demo mode
            return [
                'order_id'   => $orderId,
                'status'     => 'pending',
                'amount'     => 10000,
                'currency'   => 'EUR',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
        }

        return [
            'order_id'   => $orderData['order_id'],
            'status'     => $orderData['status'],
            'amount'     => $orderData['amount'],
            'currency'   => $orderData['currency'],
            'created_at' => $orderData['created_at'],
            'updated_at' => $orderData['updated_at'],
        ];
    }

    /**
     * Cancel a demo Paysera order.
     */
    public function cancelOrder(string $orderId): bool
    {
        /** @var array<string, mixed>|null $orderData */
        $orderData = self::$demoOrders[$orderId] ?? Cache::get("paysera_order:{$orderId}");

        if (! $orderData) {
            // In demo mode, cancellation always succeeds
            Log::info('Demo Paysera order cancelled (not found, treating as success)', [
                'order_id' => $orderId,
            ]);

            return true;
        }

        $orderData['status'] = 'cancelled';
        $orderData['updated_at'] = now()->toIso8601String();
        self::$demoOrders[$orderId] = $orderData;
        Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));

        Log::info('Demo Paysera order cancelled', ['order_id' => $orderId]);

        return true;
    }

    /**
     * Reset demo orders (useful for testing).
     */
    public static function resetDemoOrders(): void
    {
        self::$demoOrders = [];
    }
}
