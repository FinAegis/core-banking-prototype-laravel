<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Services\AccountService;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use App\Domain\Wallet\Workflows\WalletConvertWorkflow;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Str;
use Workflow\WorkflowStub;

/**
 * @OA\Tag(
 *     name="GCU Trading",
 *     description="Buy and sell Global Currency Unit operations"
 * )
 */
class GCUTradingController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly AccountService $accountService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/gcu/buy",
     *     operationId="buyGCU",
     *     tags={"GCU Trading"},
     *     summary="Buy GCU tokens",
     *     description="Purchase GCU tokens using fiat currency",
     *     security={{"sanctum":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"amount", "currency"},
     *
     * @OA\Property(property="amount",                   type="number", format="float", example=1000.00, minimum=100, description="Amount to spend in source currency"),
     * @OA\Property(property="currency",                 type="string", example="EUR", description="Source currency code (EUR, USD, GBP, CHF)"),
     * @OA\Property(property="account_uuid",             type="string", format="uuid", description="Account UUID (optional, defaults to user's primary account)")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="GCU purchase successful",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                     type="object",
     * @OA\Property(property="transaction_id",           type="string", format="uuid"),
     * @OA\Property(property="account_uuid",             type="string", format="uuid"),
     * @OA\Property(property="spent_amount",             type="number", format="float", example=1000.00),
     * @OA\Property(property="spent_currency",           type="string", example="EUR"),
     * @OA\Property(property="received_amount",          type="number", format="float", example=912.45),
     * @OA\Property(property="received_currency",        type="string", example="GCU"),
     * @OA\Property(property="exchange_rate",            type="number", format="float", example=0.91245),
     * @OA\Property(property="fee_amount",               type="number", format="float", example=10.00),
     * @OA\Property(property="fee_currency",             type="string", example="EUR"),
     * @OA\Property(property="new_gcu_balance",          type="number", format="float", example=1912.45),
     * @OA\Property(property="timestamp",                type="string", format="date-time")
     *             ),
     * @OA\Property(property="message",                  type="string", example="Successfully purchased 912.45 GCU")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=400,
     *         description="Invalid request parameters",
     *
     * @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Insufficient balance or validation error",
     *
     * @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function buy(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'amount'       => 'required|numeric|min:100',
                'currency'     => 'required|string|in:EUR,USD,GBP,CHF',
                'account_uuid' => 'sometimes|uuid|exists:accounts,uuid',
            ]
        );

        $user = $request->user();
        $accountUuid = $validated['account_uuid'] ?? $user->primaryAccount()->uuid;
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Verify account belongs to user
        if ($account->user_uuid !== $user->uuid) {
            return response()->json(
                [
                    'error'   => 'Unauthorized',
                    'message' => 'Account does not belong to authenticated user',
                ],
                403
            );
        }

        // Check if account is frozen
        if ($account->frozen) {
            return response()->json(
                [
                    'error'   => 'Account Frozen',
                    'message' => 'Cannot perform transactions on frozen account',
                ],
                422
            );
        }

        // Get source currency balance
        $sourceBalance = AccountBalance::where('account_uuid', $accountUuid)
            ->where('asset_code', $validated['currency'])
            ->first();

        if (! $sourceBalance || $sourceBalance->balance < $validated['amount']) {
            return response()->json(
                [
                    'error'   => 'Insufficient Balance',
                    'message' => "Insufficient {$validated['currency']} balance",
                ],
                422
            );
        }

        // Get current GCU value
        $gcuAsset = BasketAsset::where('code', 'GCU')->firstOrFail();
        $latestValue = BasketValue::where('basket_asset_code', 'GCU')
            ->orderBy('calculated_at', 'desc')
            ->first();

        if (! $latestValue) {
            return response()->json(
                [
                    'error'   => 'GCU Value Not Available',
                    'message' => 'Unable to determine current GCU value',
                ],
                503
            );
        }

        // Calculate exchange rate and GCU amount
        $exchangeRate = $this->calculateGCUExchangeRate($validated['currency'], $latestValue->value);

        // Apply trading fee (1%)
        $feeRate = 0.01;
        $feeAmount = $validated['amount'] * $feeRate;
        $netAmount = $validated['amount'] - $feeAmount;
        $gcuAmount = $netAmount * $exchangeRate;

        DB::beginTransaction();
        try {
            // Create transaction ID
            $transactionId = Str::uuid()->toString();

            // Execute the buy operation using workflows
            // We're converting EUR to GCU
            $workflow = WorkflowStub::make(WalletConvertWorkflow::class);
            $workflow->start(
                AccountUuid::fromString($accountUuid),
                $validated['currency'], // From currency (EUR)
                'GCU', // To currency (GCU)
                (int) ($validated['amount'] * 100) // Amount in cents
            );

            // Get updated GCU balance
            $gcuBalance = AccountBalance::where('account_uuid', $accountUuid)
                ->where('asset_code', 'GCU')
                ->first();

            $newGcuBalance = $gcuBalance ? $gcuBalance->balance : $gcuAmount;

            DB::commit();

            return response()->json(
                [
                    'data' => [
                        'transaction_id'    => $transactionId,
                        'account_uuid'      => $accountUuid,
                        'spent_amount'      => $validated['amount'],
                        'spent_currency'    => $validated['currency'],
                        'received_amount'   => round($gcuAmount, 4),
                        'received_currency' => 'GCU',
                        'exchange_rate'     => round($exchangeRate, 6),
                        'fee_amount'        => round($feeAmount, 2),
                        'fee_currency'      => $validated['currency'],
                        'new_gcu_balance'   => round($newGcuBalance, 4),
                        'timestamp'         => now()->toIso8601String(),
                    ],
                    'message' => sprintf('Successfully purchased %.4f GCU', $gcuAmount),
                ]
            );
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(
                [
                    'error'   => 'Transaction Failed',
                    'message' => 'Failed to complete GCU purchase: ' . $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/gcu/sell",
     *     operationId="sellGCU",
     *     tags={"GCU Trading"},
     *     summary="Sell GCU tokens",
     *     description="Sell GCU tokens for fiat currency",
     *     security={{"sanctum":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"amount", "currency"},
     *
     * @OA\Property(property="amount",                   type="number", format="float", example=100.00, minimum=10, description="Amount of GCU to sell"),
     * @OA\Property(property="currency",                 type="string", example="EUR", description="Target currency code (EUR, USD, GBP, CHF)"),
     * @OA\Property(property="account_uuid",             type="string", format="uuid", description="Account UUID (optional, defaults to user's primary account)")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="GCU sale successful",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                     type="object",
     * @OA\Property(property="transaction_id",           type="string", format="uuid"),
     * @OA\Property(property="account_uuid",             type="string", format="uuid"),
     * @OA\Property(property="sold_amount",              type="number", format="float", example=100.00),
     * @OA\Property(property="sold_currency",            type="string", example="GCU"),
     * @OA\Property(property="received_amount",          type="number", format="float", example=109.00),
     * @OA\Property(property="received_currency",        type="string", example="EUR"),
     * @OA\Property(property="exchange_rate",            type="number", format="float", example=1.0956),
     * @OA\Property(property="fee_amount",               type="number", format="float", example=1.10),
     * @OA\Property(property="fee_currency",             type="string", example="EUR"),
     * @OA\Property(property="new_gcu_balance",          type="number", format="float", example=812.45),
     * @OA\Property(property="timestamp",                type="string", format="date-time")
     *             ),
     * @OA\Property(property="message",                  type="string", example="Successfully sold 100.00 GCU")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=400,
     *         description="Invalid request parameters",
     *
     * @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Insufficient GCU balance or validation error",
     *
     * @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function sell(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'amount'       => 'required|numeric|min:10',
                'currency'     => 'required|string|in:EUR,USD,GBP,CHF',
                'account_uuid' => 'sometimes|uuid|exists:accounts,uuid',
            ]
        );

        $user = $request->user();
        $accountUuid = $validated['account_uuid'] ?? $user->primaryAccount()->uuid;
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Verify account belongs to user
        if ($account->user_uuid !== $user->uuid) {
            return response()->json(
                [
                    'error'   => 'Unauthorized',
                    'message' => 'Account does not belong to authenticated user',
                ],
                403
            );
        }

        // Check if account is frozen
        if ($account->frozen) {
            return response()->json(
                [
                    'error'   => 'Account Frozen',
                    'message' => 'Cannot perform transactions on frozen account',
                ],
                422
            );
        }

        // Get GCU balance
        $gcuBalance = AccountBalance::where('account_uuid', $accountUuid)
            ->where('asset_code', 'GCU')
            ->first();

        if (! $gcuBalance || $gcuBalance->balance < $validated['amount']) {
            return response()->json(
                [
                    'error'   => 'Insufficient Balance',
                    'message' => 'Insufficient GCU balance',
                ],
                422
            );
        }

        // Get current GCU value
        $latestValue = BasketValue::where('basket_asset_code', 'GCU')
            ->orderBy('calculated_at', 'desc')
            ->first();

        if (! $latestValue) {
            return response()->json(
                [
                    'error'   => 'GCU Value Not Available',
                    'message' => 'Unable to determine current GCU value',
                ],
                503
            );
        }

        // Calculate exchange rate and fiat amount
        $exchangeRate = 1 / $this->calculateGCUExchangeRate($validated['currency'], $latestValue->value);
        $grossAmount = $validated['amount'] * $exchangeRate;

        // Apply trading fee (1%)
        $feeRate = 0.01;
        $feeAmount = $grossAmount * $feeRate;
        $netAmount = $grossAmount - $feeAmount;

        DB::beginTransaction();
        try {
            // Create transaction ID
            $transactionId = Str::uuid()->toString();

            // Execute the sell operation using workflows
            $workflow = WorkflowStub::make(WalletConvertWorkflow::class);
            $workflow->start(
                AccountUuid::fromString($accountUuid),
                'GCU', // From currency (GCU)
                $validated['currency'], // To currency (EUR)
                (int) ($validated['amount'] * 10000) // GCU uses 4 decimal places
            );

            // Get updated GCU balance
            $updatedGcuBalance = AccountBalance::where('account_uuid', $accountUuid)
                ->where('asset_code', 'GCU')
                ->first();

            $newGcuBalance = $updatedGcuBalance ? $updatedGcuBalance->balance : 0;

            DB::commit();

            return response()->json(
                [
                    'data' => [
                        'transaction_id'    => $transactionId,
                        'account_uuid'      => $accountUuid,
                        'sold_amount'       => $validated['amount'],
                        'sold_currency'     => 'GCU',
                        'received_amount'   => round($netAmount, 2),
                        'received_currency' => $validated['currency'],
                        'exchange_rate'     => round($exchangeRate, 6),
                        'fee_amount'        => round($feeAmount, 2),
                        'fee_currency'      => $validated['currency'],
                        'new_gcu_balance'   => round($newGcuBalance, 4),
                        'timestamp'         => now()->toIso8601String(),
                    ],
                    'message' => sprintf('Successfully sold %.4f GCU', $validated['amount']),
                ]
            );
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(
                [
                    'error'   => 'Transaction Failed',
                    'message' => 'Failed to complete GCU sale: ' . $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/gcu/quote",
     *     operationId="getGCUQuote",
     *     tags={"GCU Trading"},
     *     summary="Get GCU trading quote",
     *     description="Get a quote for buying or selling GCU",
     *     security={{"sanctum":{}}},
     *
     * @OA\Parameter(
     *         name="operation",
     *         in="query",
     *         required=true,
     *         description="Operation type",
     *
     * @OA\Schema(type="string",                  enum={"buy", "sell"})
     *     ),
     *
     * @OA\Parameter(
     *         name="amount",
     *         in="query",
     *         required=true,
     *         description="Amount (in source currency for buy, in GCU for sell)",
     *
     * @OA\Schema(type="number",                  format="float", minimum=0.01)
     *     ),
     *
     * @OA\Parameter(
     *         name="currency",
     *         in="query",
     *         required=true,
     *         description="Fiat currency code",
     *
     * @OA\Schema(type="string",                  enum={"EUR", "USD", "GBP", "CHF"})
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Trading quote",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",              type="object",
     * @OA\Property(property="operation",         type="string", example="buy"),
     * @OA\Property(property="input_amount",      type="number", format="float", example=1000.00),
     * @OA\Property(property="input_currency",    type="string", example="EUR"),
     * @OA\Property(property="output_amount",     type="number", format="float", example=912.45),
     * @OA\Property(property="output_currency",   type="string", example="GCU"),
     * @OA\Property(property="exchange_rate",     type="number", format="float", example=0.91245),
     * @OA\Property(property="fee_amount",        type="number", format="float", example=10.00),
     * @OA\Property(property="fee_currency",      type="string", example="EUR"),
     * @OA\Property(property="fee_percentage",    type="number", format="float", example=1.0),
     * @OA\Property(property="quote_valid_until", type="string", format="date-time"),
     * @OA\Property(property="minimum_amount",    type="number", format="float"),
     * @OA\Property(property="maximum_amount",    type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'operation' => 'required|string|in:buy,sell',
                'amount'    => 'required|numeric|min:0.01',
                'currency'  => 'required|string|in:EUR,USD,GBP,CHF',
            ]
        );

        // Get current GCU value
        $latestValue = BasketValue::where('basket_asset_code', 'GCU')
            ->orderBy('calculated_at', 'desc')
            ->first();

        if (! $latestValue) {
            return response()->json(
                [
                    'error'   => 'GCU Value Not Available',
                    'message' => 'Unable to determine current GCU value',
                ],
                503
            );
        }

        $feeRate = 0.01; // 1% trading fee
        $exchangeRate = $this->calculateGCUExchangeRate($validated['currency'], $latestValue->value);

        if ($validated['operation'] === 'buy') {
            // User wants to buy GCU with fiat
            $feeAmount = $validated['amount'] * $feeRate;
            $netAmount = $validated['amount'] - $feeAmount;
            $outputAmount = $netAmount * $exchangeRate;

            $data = [
                'operation'       => 'buy',
                'input_amount'    => $validated['amount'],
                'input_currency'  => $validated['currency'],
                'output_amount'   => round($outputAmount, 4),
                'output_currency' => 'GCU',
                'exchange_rate'   => round($exchangeRate, 6),
                'fee_amount'      => round($feeAmount, 2),
                'fee_currency'    => $validated['currency'],
                'minimum_amount'  => 100.00,
                'maximum_amount'  => 1000000.00,
            ];
        } else {
            // User wants to sell GCU for fiat
            $inverseRate = 1 / $exchangeRate;
            $grossAmount = $validated['amount'] * $inverseRate;
            $feeAmount = $grossAmount * $feeRate;
            $outputAmount = $grossAmount - $feeAmount;

            $data = [
                'operation'       => 'sell',
                'input_amount'    => $validated['amount'],
                'input_currency'  => 'GCU',
                'output_amount'   => round($outputAmount, 2),
                'output_currency' => $validated['currency'],
                'exchange_rate'   => round($inverseRate, 6),
                'fee_amount'      => round($feeAmount, 2),
                'fee_currency'    => $validated['currency'],
                'minimum_amount'  => 10.00,
                'maximum_amount'  => 100000.00,
            ];
        }

        $data['fee_percentage'] = $feeRate * 100;
        $data['quote_valid_until'] = now()->addMinutes(5)->toIso8601String();

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/gcu/trading-limits",
     *     operationId="getGCUTradingLimits",
     *     tags={"GCU Trading"},
     *     summary="Get user's GCU trading limits",
     *     description="Get the authenticated user's trading limits for GCU operations",
     *     security={{"sanctum":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Trading limits",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                type="object",
     * @OA\Property(property="daily_buy_limit",     type="number", format="float", example=10000.00),
     * @OA\Property(property="daily_sell_limit",    type="number", format="float", example=10000.00),
     * @OA\Property(property="daily_buy_used",      type="number", format="float", example=2500.00),
     * @OA\Property(property="daily_sell_used",     type="number", format="float", example=0.00),
     * @OA\Property(property="monthly_buy_limit",   type="number", format="float", example=100000.00),
     * @OA\Property(property="monthly_sell_limit",  type="number", format="float", example=100000.00),
     * @OA\Property(property="monthly_buy_used",    type="number", format="float", example=15000.00),
     * @OA\Property(property="monthly_sell_used",   type="number", format="float", example=5000.00),
     * @OA\Property(property="minimum_buy_amount",  type="number", format="float", example=100.00),
     * @OA\Property(property="minimum_sell_amount", type="number", format="float", example=10.00),
     * @OA\Property(property="kyc_level",           type="integer", example=2),
     * @OA\Property(property="limits_currency",     type="string", example="EUR")
     *             )
     *         )
     *     )
     * )
     */
    public function tradingLimits(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get user's KYC level (placeholder - implement actual KYC check)
        $kycLevel = 2; // Basic verified

        // Define limits based on KYC level
        $limits = match ($kycLevel) {
            0 => [ // Unverified
                'daily_buy'    => 0,
                'daily_sell'   => 0,
                'monthly_buy'  => 0,
                'monthly_sell' => 0,
            ],
            1 => [ // Basic
                'daily_buy'    => 1000,
                'daily_sell'   => 1000,
                'monthly_buy'  => 10000,
                'monthly_sell' => 10000,
            ],
            2 => [ // Verified
                'daily_buy'    => 10000,
                'daily_sell'   => 10000,
                'monthly_buy'  => 100000,
                'monthly_sell' => 100000,
            ],
            3 => [ // Enhanced
                'daily_buy'    => 50000,
                'daily_sell'   => 50000,
                'monthly_buy'  => 500000,
                'monthly_sell' => 500000,
            ],
            default => [ // Corporate/Unlimited
                'daily_buy'    => 1000000,
                'daily_sell'   => 1000000,
                'monthly_buy'  => 10000000,
                'monthly_sell' => 10000000,
            ],
        };

        // Calculate used amounts (placeholder - implement actual calculation)
        $dailyBuyUsed = 0;
        $dailySellUsed = 0;
        $monthlyBuyUsed = 0;
        $monthlySellUsed = 0;

        return response()->json(
            [
                'data' => [
                    'daily_buy_limit'     => $limits['daily_buy'],
                    'daily_sell_limit'    => $limits['daily_sell'],
                    'daily_buy_used'      => $dailyBuyUsed,
                    'daily_sell_used'     => $dailySellUsed,
                    'monthly_buy_limit'   => $limits['monthly_buy'],
                    'monthly_sell_limit'  => $limits['monthly_sell'],
                    'monthly_buy_used'    => $monthlyBuyUsed,
                    'monthly_sell_used'   => $monthlySellUsed,
                    'minimum_buy_amount'  => 100.00,
                    'minimum_sell_amount' => 10.00,
                    'kyc_level'           => $kycLevel,
                    'limits_currency'     => 'EUR',
                ],
            ]
        );
    }

    /**
     * Calculate the exchange rate from a fiat currency to GCU.
     *
     * @param  string  $currency  The fiat currency code
     * @param  float  $gcuValueInUSD  The current GCU value in USD
     * @return float The exchange rate (how many GCU per 1 unit of currency)
     */
    private function calculateGCUExchangeRate(string $currency, float $gcuValueInUSD): float
    {
        // If currency is USD, direct calculation
        if ($currency === 'USD') {
            return 1 / $gcuValueInUSD;
        }

        // Get exchange rate from currency to USD
        $currencyToUsdRate = $this->exchangeRateService->getRate($currency, 'USD');
        if (! $currencyToUsdRate) {
            throw new Exception("Exchange rate not available for {$currency} to USD");
        }

        // Calculate how many GCU per 1 unit of currency
        // 1 EUR = X USD, 1 GCU = Y USD, so 1 EUR = X/Y GCU
        return $currencyToUsdRate->rate / $gcuValueInUSD;
    }
}
