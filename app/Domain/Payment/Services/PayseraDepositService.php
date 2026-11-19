<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use App\Domain\Payment\Services\Concerns\GuardsPayseraCallback;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Production Paysera Deposit Service.
 *
 * Integrates with Paysera payment gateway for EUR SEPA deposits.
 */
class PayseraDepositService implements PayseraDepositServiceInterface
{
    use GuardsPayseraCallback;

    private const API_URL = 'https://www.paysera.com/pay/';

    private const PROTOCOL_VERSION = '1.6';

    /** Paysera response status for a completed payment. */
    private const STATUS_PAID = '1';

    public const MESSAGE_NOT_COMPLETED = 'Payment not completed';

    public const MESSAGE_ALREADY_PROCESSED = 'Deposit already processed';

    public const MESSAGE_PROCESSING_FAILED = 'Deposit processing failed';

    private readonly DepositReplayGuard $replayGuard;

    public function __construct(
        private readonly PaymentServiceInterface $paymentService,
        ?DepositReplayGuard $replayGuard = null,
    ) {
        $this->replayGuard = $replayGuard ?? new DepositReplayGuard();
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
        $projectId = (string) config('services.paysera.project_id', '');
        $signPassword = (string) config('services.paysera.sign_password', '');

        if ($projectId === '' || $signPassword === '') {
            throw new RuntimeException('Paysera deposits are not configured.');
        }

        $orderId = 'PAY-' . strtoupper(bin2hex(random_bytes(10)));

        Log::info('Initiating Paysera deposit', [
            'order_id'     => $orderId,
            'account_uuid' => $data['account_uuid'],
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
        ]);

        // Paysera appends its signed response (data, ss1, ss2) to these URLs.
        // The payer's browser returns to the session route; Paysera's
        // server-to-server notification goes to the unauthenticated route,
        // which trusts only the ss1 signature.
        $callbackUrl = route('wallet.deposit.paysera.callback', ['order_id' => $orderId]);
        $notifyUrl = route('api.webhooks.paysera.deposit');

        $payseraData = [
            'projectid'   => $projectId,
            'orderid'     => $orderId,
            'amount'      => $data['amount'],
            'currency'    => $data['currency'],
            'accepturl'   => $data['return_url'] ?? $callbackUrl,
            'cancelurl'   => $data['cancel_url'] ?? route('wallet.deposit.paysera.callback', ['order_id' => $orderId, 'cancelled' => 1]),
            'callbackurl' => $notifyUrl,
            'version'     => self::PROTOCOL_VERSION,
            'payment'     => 'card,bank',
            'country'     => 'LT',
            'paytext'     => $data['description'] ?? 'Deposit to FinAegis',
            'test'        => app()->environment('production') ? 0 : 1,
        ];

        // Create encoded data and sign
        $encodedData = self::encodePayseraData(http_build_query($payseraData));
        $sign = md5($encodedData . $signPassword);

        $redirectUrl = self::API_URL . '?' . http_build_query([
            'data' => $encodedData,
            'sign' => $sign,
        ]);

        // Store the order for callback verification only once the request
        // to Paysera has been built.
        Cache::put("paysera_order:{$orderId}", [
            'order_id'     => $orderId,
            'account_uuid' => $data['account_uuid'],
            'amount'       => $data['amount'],
            'currency'     => $data['currency'],
            'user_id'      => $data['user_id'],
            'status'       => 'pending',
            'created_at'   => now()->toIso8601String(),
            'updated_at'   => now()->toIso8601String(),
        ], now()->addHours(24));

        return [
            'redirect_url' => $redirectUrl,
            'order_id'     => $orderId,
            'status'       => 'pending',
        ];
    }

    /**
     * Handle Paysera callback after payment.
     *
     * The outcome is taken only from Paysera's signed response: `data` must
     * carry a valid ss1 signature (md5(data . sign_password)) and report
     * status=1 for this order with the stored amount and currency. A callback
     * without a valid signature changes nothing.
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
    public function handleCallback(array $callbackData): array
    {
        return $this->processSignedCallback($callbackData, (int) $callbackData['user_id']);
    }

    /**
     * Handle Paysera's server-to-server notification (the callbackurl).
     *
     * It carries no session, so Paysera's ss1 signature over `data` is its
     * only authentication. The order is credited to the user and account
     * stored with it at initiate time (that user must still own the
     * account), with the same signature, amount, status and replay checks
     * as the browser return.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     reference?: string
     * }
     */
    public function handleServerCallback(string $data, string $ss1): array
    {
        return $this->processSignedCallback([
            'order_id'     => '',
            'status'       => '',
            'paysera_data' => $data,
            'paysera_ss1'  => $ss1,
        ], null);
    }

    /**
     * Credit an order from Paysera's signed response.
     *
     * @param  array<string, mixed>  $callbackData
     * @param  int|null  $callerUserId  the signed-in user (browser return), or
     *                                  null for the server notification, which
     *                                  credits the user stored with the order
     * @return array{
     *     success: bool,
     *     message: string,
     *     reference?: string
     * }
     */
    private function processSignedCallback(array $callbackData, ?int $callerUserId): array
    {
        $orderId = (string) ($callbackData['order_id'] ?? '');

        Log::info('Processing Paysera callback', [
            'order_id' => $orderId,
        ]);

        $response = $this->verifiedPayseraResponse($callbackData);

        if ($response === null) {
            Log::warning('Paysera callback refused: missing or invalid Paysera signature', [
                'order_id' => $orderId,
            ]);

            return [
                'success' => false,
                'message' => 'Payment could not be verified',
            ];
        }

        $signedOrderId = (string) ($response['orderid'] ?? '');

        if ($signedOrderId === '' || ($orderId !== '' && ! hash_equals($signedOrderId, $orderId))) {
            Log::warning('Paysera callback refused: signed order id differs from the request', [
                'order_id' => $orderId,
            ]);

            return [
                'success' => false,
                'message' => 'Payment details do not match the order',
            ];
        }

        $orderId = $signedOrderId;

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

        $projectId = (string) config('services.paysera.project_id', '');

        if (
            ! isset($response['amount'], $response['currency'])
            || ($projectId !== '' && (string) ($response['projectid'] ?? '') !== $projectId)
            || (app()->environment('production') && (string) ($response['test'] ?? '0') !== '0')
        ) {
            Log::warning('Paysera callback refused: signed payment details do not match the order', [
                'order_id' => $orderId,
            ]);

            return [
                'success' => false,
                'message' => 'Payment details do not match the order',
            ];
        }

        if ((string) ($response['status'] ?? '') !== self::STATUS_PAID) {
            return [
                'success' => false,
                'message' => self::MESSAGE_NOT_COMPLETED,
            ];
        }

        // Amount and currency are checked against the stored order using the
        // signed values only.
        $guardData = $callbackData;
        $guardData['amount'] = (int) $response['amount'];
        $guardData['currency'] = (string) $response['currency'];
        $guardData['user_id'] = $callerUserId ?? (int) ($orderData['user_id'] ?? 0);

        $rejection = $this->payseraCallbackRejection($orderId, $orderData, $guardData);
        if ($rejection !== null) {
            return [
                'success' => false,
                'message' => $rejection,
            ];
        }

        if (! $this->replayGuard->claim(DepositReplayGuard::SOURCE_PAYSERA, $orderId)) {
            return [
                'success' => false,
                'message' => self::MESSAGE_ALREADY_PROCESSED,
            ];
        }

        $payseraReference = isset($response['requestid']) ? (string) $response['requestid'] : null;
        $paymentType = isset($response['payment']) ? (string) $response['payment'] : null;
        $reference = 'DEP-' . strtoupper(uniqid());

        // Only a failure to start the credit gives the claim back. Once the
        // credit is dispatched, the claim stays, whatever happens next.
        try {
            // Process the deposit through payment service (amount and
            // currency always come from the stored order)
            $this->paymentService->processStripeDeposit([
                'account_uuid'        => $orderData['account_uuid'],
                'amount'              => $orderData['amount'],
                'currency'            => $orderData['currency'],
                'reference'           => $reference,
                'external_reference'  => $payseraReference ?? $orderId,
                'payment_method'      => 'paysera',
                'payment_method_type' => $paymentType ?? 'bank_transfer',
                'metadata'            => [
                    'paysera_order_id'    => $orderId,
                    'paysera_transaction' => $payseraReference,
                    'payment_type'        => $paymentType,
                ],
            ]);
        } catch (Exception $e) {
            $this->replayGuard->release(DepositReplayGuard::SOURCE_PAYSERA, $orderId);

            Log::error('Paysera deposit processing failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => self::MESSAGE_PROCESSING_FAILED . ': ' . $e->getMessage(),
            ];
        }

        // The cached status is informational: the replay claim above is what
        // stops a second credit, so a failed cache write is only logged.
        try {
            $orderData['status'] = 'completed';
            $orderData['updated_at'] = now()->toIso8601String();
            Cache::put("paysera_order:{$orderId}", $orderData, now()->addHours(24));
        } catch (Exception $e) {
            Log::warning('Paysera order status could not be cached after the credit was started', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        return [
            'success'   => true,
            'message'   => 'Deposit processed successfully',
            'reference' => $reference,
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

    /**
     * Decode Paysera's signed response, or null when the signature is
     * missing, the sign password is not configured or ss1 does not match.
     * Only scalar (string) fields are kept.
     *
     * @param  array<string, mixed>  $callbackData
     * @return array<int|string, string>|null
     */
    private function verifiedPayseraResponse(array $callbackData): ?array
    {
        $signPassword = (string) config('services.paysera.sign_password', '');
        $data = $callbackData['paysera_data'] ?? null;
        $ss1 = $callbackData['paysera_ss1'] ?? null;

        if ($signPassword === '' || ! is_string($data) || $data === '' || ! is_string($ss1) || $ss1 === '') {
            return null;
        }

        if (! hash_equals(md5($data . $signPassword), strtolower($ss1))) {
            return null;
        }

        $decoded = base64_decode(strtr($data, ['-' => '+', '_' => '/']), true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        parse_str($decoded, $params);

        return array_filter($params, is_string(...));
    }

    /**
     * Paysera's URL-safe base64 encoding of the request/response payload.
     */
    public static function encodePayseraData(string $query): string
    {
        return strtr(base64_encode($query), ['+' => '-', '/' => '_']);
    }
}
