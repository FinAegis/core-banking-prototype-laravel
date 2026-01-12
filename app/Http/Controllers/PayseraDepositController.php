<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for Paysera deposit operations.
 *
 * Handles initiation and callback processing for Paysera payment gateway deposits.
 * Supports both production and demo modes via service injection.
 */
class PayseraDepositController extends Controller
{
    public function __construct(
        private readonly PayseraDepositServiceInterface $payseraDepositService
    ) {
    }

    /**
     * Initiate a Paysera deposit.
     *
     * @throws ValidationException
     */
    public function initiate(Request $request): JsonResponse|RedirectResponse
    {
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

    /**
     * Handle Paysera callback after payment.
     */
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        Log::info('Paysera callback received', $request->all());

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
            'order_id'       => 'required|string',
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

        try {
            $callbackData = [
                'order_id' => (string) $request->input('order_id'),
                'status'   => (string) $request->input('status', 'completed'),
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

            /** @var array{order_id: string, status: string, amount?: int, currency?: string, payment_type?: string, transaction_id?: string} $callbackData */
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

    /**
     * Get order status.
     */
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

    /**
     * Cancel a pending order.
     */
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
}
