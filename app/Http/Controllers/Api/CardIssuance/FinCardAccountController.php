<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Http\Requests\CreateDepositAddressRequest;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Models\FinCardDepositAddress;
use App\Domain\CardIssuance\Services\FinCardAccountService;
use App\Http\Controllers\Controller;
use App\Infrastructure\FinCard\FinCardApiException;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * FinCard funding — account balance, supported deposit coins, and crypto
 * deposit-address provisioning (Phase 3).
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §4.2
 */
class FinCardAccountController extends Controller
{
    public function __construct(
        private readonly FinCardClient $client,
        private readonly FinCardAccountService $accounts,
    ) {
    }

    /**
     * The user's funding account + balance (mirror, reconciled best-effort).
     */
    public function account(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $this->accounts->existingAccount($user);

        if (! $account instanceof FinCardAccount) {
            return response()->json([
                'success' => true,
                'data'    => ['provisioned' => false, 'currency' => 'USD', 'balance_cents' => 0],
            ]);
        }

        // Reconcile from FinCard's authoritative balance, but never fail the read
        // if FinCard is momentarily unavailable — fall back to the mirror.
        try {
            $account = $this->accounts->syncBalance($account, $this->context($request));
        } catch (FinCardApiException $e) {
            Log::warning('FinCard balance sync failed; serving mirror', ['user_id' => $user->id, 'msg' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'provisioned'   => true,
                'account_id'    => $account->fincard_account_id,
                'currency'      => $account->currency,
                'balance_cents' => $account->balance_cents,
                'status'        => $account->status,
            ],
        ]);
    }

    /**
     * Supported crypto deposit coins (live from FinCard — the runtime authority).
     */
    public function coins(Request $request): JsonResponse
    {
        try {
            $response = $this->client->getCoins($this->context($request));
        } catch (FinCardApiException $e) {
            Log::warning('FinCard coins fetch failed', ['msg' => $e->getMessage()]);

            return ErrorResponse::make('ERR_CARDS_013');
        }

        $coins = is_array($response['data'] ?? null) ? $response['data'] : [];

        return response()->json(['success' => true, 'data' => ['coins' => $coins]]);
    }

    /**
     * Get or create the user's deposit address for a coin.
     */
    public function depositAddress(CreateDepositAddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $coinKey = (string) $request->validated('coin_key');

        try {
            $address = $this->accounts->getOrCreateDepositAddress($user, $coinKey, $this->context($request));
        } catch (FinCardApiException $e) {
            Log::warning('FinCard deposit address failed', ['user_id' => $user->id, 'coin' => $coinKey, 'msg' => $e->getMessage()]);

            return ErrorResponse::make('ERR_CARDS_013');
        }

        if ($address->address === '') {
            return ErrorResponse::make('ERR_CARDS_013');
        }

        return response()->json(['success' => true, 'data' => $this->presentAddress($address)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAddress(FinCardDepositAddress $address): array
    {
        return [
            'coin_key'          => $address->coin_key,
            'chain'             => $address->chain,
            'address'           => $address->address,
            'min_deposit_cents' => $address->min_deposit_cents,
            'confirmations'     => $address->confirmations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        return [
            'forwarded_for' => $request->ip() ?? '127.0.0.1',
            'platform'      => (string) ($request->header('platform') ?? 'ios'),
            'device_id'     => (string) ($request->header('deviceId') ?? ''),
        ];
    }
}
