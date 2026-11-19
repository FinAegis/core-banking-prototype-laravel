<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use App\Domain\Payment\Services\DemoPayseraDepositService;
use App\Domain\Payment\Services\PayseraDepositService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

/**
 * Controller for Paysera deposit operations.
 *
 * Handles initiation and callback processing for Paysera payment gateway deposits.
 * Supports both production and demo modes via service injection.
 */
#[OA\Tag(
    name: 'Paysera Deposits',
    description: 'Paysera payment gateway deposit management'
)]
class PayseraDepositController extends Controller
{
    public function __construct(
        private readonly PayseraDepositServiceInterface $payseraDepositService
    ) {
    }

        #[OA\Post(
            path: '/paysera/deposits/initiate',
            operationId: 'payseraDepositsInitiate',
            tags: ['Paysera Deposits'],
            summary: 'Initiate Paysera deposit',
            description: 'Initiates a deposit via Paysera',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function initiate(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAvailable();

        $validator = Validator::make($request->all(), [
            'account_uuid' => 'required|string|uuid',
            'amount'       => 'required|integer|min:100|max:100000000', // 1.00 to 1,000,000.00
            'currency'     => 'required|string|in:EUR,USD,GBP,PLN',
            'return_url'   => 'nullable|string|url',
            'cancel_url'   => 'nullable|string|url',
            'description'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Deposits can only be started for an account the caller owns.
            if (Account::findOwnedBy((string) $request->input('account_uuid'), $user) === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this account',
                    'error'   => 'FORBIDDEN',
                ], 403);
            }

            $result = $this->payseraDepositService->initiateDeposit([
                'account_uuid' => $request->input('account_uuid'),
                'amount'       => (int) $request->input('amount'),
                'currency'     => $request->input('currency'),
                'user_id'      => $user->id,
                'return_url'   => $request->input('return_url'),
                'cancel_url'   => $request->input('cancel_url'),
                'description'  => $request->input('description'),
            ]);

            Log::info('Paysera deposit initiated', [
                'user_id'  => $user->id,
                'order_id' => $result['order_id'],
            ]);

            // If request expects JSON, return JSON response
            if ($request->expectsJson()) {
                return response()->json([
                    'success'      => true,
                    'redirect_url' => $result['redirect_url'],
                    'order_id'     => $result['order_id'],
                    'status'       => $result['status'],
                ]);
            }

            // Otherwise redirect to Paysera
            return redirect()->away($result['redirect_url']);
        } catch (Exception $e) {
            Log::error('Paysera deposit initiation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate deposit: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/paysera/deposits/callback',
            operationId: 'payseraDepositsCallback',
            tags: ['Paysera Deposits'],
            summary: 'Paysera callback',
            description: 'Handles the Paysera payment callback',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAvailable();

        Log::info('Paysera callback received', ['order_id' => $request->input('order_id')]);

        // Check for cancellation
        if ($request->has('cancelled') && $request->input('cancelled')) {
            Log::info('Paysera payment cancelled by user');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cancelled',
                ]);
            }

            return redirect()->route('dashboard')->with('warning', 'Payment was cancelled');
        }

        // Validate callback data
        $validator = Validator::make($request->all(), [
            'order_id'       => 'required_without:data|nullable|string',
            'data'           => 'nullable|string',
            'ss1'            => 'nullable|string',
            'ss2'            => 'nullable|string',
            'status'         => 'nullable|string',
            'amount'         => 'nullable|integer',
            'currency'       => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'payment_type'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::error('Paysera callback validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid callback data',
                'errors'  => $validator->errors(),
            ], 400);
        }

        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        try {
            $callbackData = [
                'order_id' => (string) $request->input('order_id', ''),
                'status'   => (string) $request->input('status', 'completed'),
                // The order is credited only to the user who started it.
                'user_id' => (int) $user->getAuthIdentifier(),
            ];

            if ($request->filled('amount')) {
                $callbackData['amount'] = (int) $request->input('amount');
            }
            if ($request->filled('currency')) {
                $callbackData['currency'] = (string) $request->input('currency');
            }
            if ($request->filled('transaction_id')) {
                $callbackData['transaction_id'] = (string) $request->input('transaction_id');
            }
            if ($request->filled('payment_type')) {
                $callbackData['payment_type'] = (string) $request->input('payment_type');
            }
            // Paysera's signed response; the Paysera service credits only
            // when it verifies.
            if ($request->filled('data')) {
                $callbackData['paysera_data'] = (string) $request->input('data');
            }
            if ($request->filled('ss1')) {
                $callbackData['paysera_ss1'] = (string) $request->input('ss1');
            }

            /** @var array{order_id: string, status: string, user_id: int, amount?: int, currency?: string, payment_type?: string, transaction_id?: string, paysera_data?: string, paysera_ss1?: string} $callbackData */
            $result = $this->payseraDepositService->handleCallback($callbackData);

            if ($request->expectsJson()) {
                return response()->json($result, $result['success'] ? 200 : 400);
            }

            // Redirect based on result
            if ($result['success']) {
                return redirect()->route('dashboard')->with('success', $result['message']);
            }

            return redirect()->route('dashboard')->with('error', $result['message']);
        } catch (Exception $e) {
            Log::error('Paysera callback processing failed', [
                'error'    => $e->getMessage(),
                'order_id' => $request->input('order_id'),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Callback processing failed: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->route('dashboard')->with('error', 'Payment processing failed');
        }
    }

        #[OA\Get(
            path: '/paysera/deposits/{id}/status',
            operationId: 'payseraDepositsStatus',
            tags: ['Paysera Deposits'],
            summary: 'Get deposit status',
            description: 'Returns the status of a Paysera deposit',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function status(Request $request, string $orderId): JsonResponse
    {
        $orderStatus = $this->payseraDepositService->getOrderStatus($orderId);

        if (! $orderStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $orderStatus,
        ]);
    }

        #[OA\Post(
            path: '/paysera/deposits/{id}/cancel',
            operationId: 'payseraDepositsCancel',
            tags: ['Paysera Deposits'],
            summary: 'Cancel deposit',
            description: 'Cancels a pending Paysera deposit',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function cancel(Request $request, string $orderId): JsonResponse
    {
        $cancelled = $this->payseraDepositService->cancelOrder($orderId);

        if (! $cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
        ]);
    }

    #[OA\Get(
        path: '/api/webhooks/paysera/deposit',
        operationId: 'payseraDepositsNotify',
        tags: ['Paysera Deposits'],
        summary: 'Paysera server-to-server deposit notification',
        description: 'The callbackurl Paysera calls when a deposit payment changes state. No session: Paysera\'s ss1 signature over data is the only authentication, and the order is credited to the user and account stored with it. Answers the plain text OK that Paysera expects. Also accepts POST.',
        parameters: [
            new OA\Parameter(name: 'data', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ss1', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ss2', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'OK: the notification was handled (credited, already credited, or payment not completed yet)'
    )]
    #[OA\Response(
        response: 400,
        description: 'Missing or invalid signature, or the signed payment does not match a stored order'
    )]
    #[OA\Response(
        response: 404,
        description: 'Paysera deposits are not enabled'
    )]
    #[OA\Response(
        response: 500,
        description: 'The credit could not be started; Paysera retries'
    )]
    public function notify(Request $request): Response
    {
        $service = $this->payseraDepositService;

        // Only the real Paysera service verifies signatures; the demo
        // service has no server notification.
        if (! $service instanceof PayseraDepositService) {
            abort(404);
        }

        $this->ensureAvailable();

        $data = $request->input('data');
        $ss1 = $request->input('ss1');

        if (! is_string($data) || $data === '' || ! is_string($ss1) || $ss1 === '') {
            return response('Error: missing signed data', 400, ['Content-Type' => 'text/plain']);
        }

        $result = $service->handleServerCallback($data, $ss1);

        if (
            $result['success']
            || in_array($result['message'], [PayseraDepositService::MESSAGE_NOT_COMPLETED, PayseraDepositService::MESSAGE_ALREADY_PROCESSED], true)
        ) {
            return response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        // A credit that could not be started answers 500 so Paysera retries.
        if (str_starts_with($result['message'], PayseraDepositService::MESSAGE_PROCESSING_FAILED)) {
            return response('Error: ' . PayseraDepositService::MESSAGE_PROCESSING_FAILED, 500, ['Content-Type' => 'text/plain']);
        }

        return response('Error: ' . $result['message'], 400, ['Content-Type' => 'text/plain']);
    }

    /**
     * The Paysera deposit flow (initiate, return callback, server
     * notification) is served only while the Paysera connector is enabled
     * (PAYSERA_ENABLED). The demo service (APP_ENV=demo) simulates the
     * payment, so it credits without one and needs the direct-funding flag
     * instead. Otherwise it answers 404.
     */
    private function ensureAvailable(): void
    {
        if ($this->payseraDepositService instanceof DemoPayseraDepositService) {
            abort_unless((bool) config('ledger.direct_funding_enabled', false), 404);

            return;
        }

        abort_unless((bool) config('custodians.connectors.paysera.enabled', false), 404);
    }
}
