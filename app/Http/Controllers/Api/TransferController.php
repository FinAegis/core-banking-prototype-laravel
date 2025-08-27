<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transfer;
use App\Domain\Asset\Models\Asset;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use App\Http\Controllers\Controller;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class TransferController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/transfers",
     *     operationId="createTransfer",
     *     tags={"Transfers"},
     *     summary="Create a money transfer",
     *     description="Transfers money from one account to another",
     *     security={{"sanctum":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"from_account_uuid", "to_account_uuid", "amount"},
     *
     * @OA\Property(property="from_account_uuid",        type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     * @OA\Property(property="to_account_uuid",          type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440000"),
     * @OA\Property(property="amount",                   type="integer", example=5000, minimum=1, description="Amount in cents"),
     * @OA\Property(property="description",              type="string", example="Payment for services", maxLength=255)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Transfer initiated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                     type="object",
     * @OA\Property(property="transfer_uuid",            type="string", format="uuid"),
     * @OA\Property(property="from_account_uuid",        type="string", format="uuid"),
     * @OA\Property(property="to_account_uuid",          type="string", format="uuid"),
     * @OA\Property(property="amount",                   type="integer", example=5000),
     * @OA\Property(property="status",                   type="string", example="pending")
     *             ),
     * @OA\Property(property="message",                  type="string", example="Transfer initiated successfully")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error or business rule violation",
     *
     * @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'from_account_uuid' => 'sometimes|uuid|exists:accounts,uuid',
                'to_account_uuid'   => 'sometimes|uuid|exists:accounts,uuid|different:from_account_uuid',
                'from_account'      => 'sometimes|uuid|exists:accounts,uuid',
                'to_account'        => 'sometimes|uuid|exists:accounts,uuid|different:from_account',
                'amount'            => 'required|numeric|min:0.01',
                'asset_code'        => 'required|string|exists:assets,code',
                'reference'         => 'sometimes|string|max:255',
                'description'       => 'sometimes|string|max:255',
            ]
        );

        // Support both field name formats for backward compatibility
        $fromAccountUuid = $validated['from_account_uuid'] ?? $validated['from_account'] ?? null;
        $toAccountUuid = $validated['to_account_uuid'] ?? $validated['to_account'] ?? null;

        if (! $fromAccountUuid || ! $toAccountUuid) {
            return response()->json(
                [
                    'message' => 'Both from and to account UUIDs are required',
                    'errors'  => [
                        'from_account_uuid' => $fromAccountUuid ? [] : ['The from account uuid field is required.'],
                        'to_account_uuid'   => $toAccountUuid ? [] : ['The to account uuid field is required.'],
                    ],
                ],
                422
            );
        }

        $fromAccount = Account::where('uuid', $fromAccountUuid)->first();
        $toAccount = Account::where('uuid', $toAccountUuid)->first();

        // Check authorization - user must own the from account
        if ($fromAccount && $fromAccount->user_uuid !== $request->user()->uuid) {
            return response()->json(
                [
                    'message' => 'Unauthorized: You can only transfer from your own accounts',
                    'error'   => 'UNAUTHORIZED_TRANSFER',
                ],
                403
            );
        }

        if ($fromAccount && $fromAccount->frozen) {
            return response()->json(
                [
                    'message' => 'Cannot transfer from frozen account',
                    'error'   => 'SOURCE_ACCOUNT_FROZEN',
                ],
                422
            );
        }

        if ($toAccount && $toAccount->frozen) {
            return response()->json(
                [
                    'message' => 'Cannot transfer to frozen account',
                    'error'   => 'DESTINATION_ACCOUNT_FROZEN',
                ],
                422
            );
        }

        $asset = Asset::where('code', $validated['asset_code'])->firstOrFail();
        $amountInMinorUnits = (int) round($validated['amount'] * (10 ** $asset->precision));

        // Check sufficient balance
        $fromBalance = $fromAccount->getBalance($validated['asset_code']);

        if ($fromBalance < $amountInMinorUnits) {
            return response()->json(
                [
                    'message'          => 'Insufficient funds',
                    'error'            => 'INSUFFICIENT_FUNDS',
                    'current_balance'  => $fromBalance,
                    'requested_amount' => $amountInMinorUnits,
                ],
                422
            );
        }

        $fromUuid = new AccountUuid($fromAccountUuid);
        $toUuid = new AccountUuid($toAccountUuid);

        try {
            // Use our wallet transfer workflow for all assets
            $workflow = WorkflowStub::make(WalletTransferWorkflow::class);
            $workflow->start($fromUuid, $toUuid, $validated['asset_code'], $amountInMinorUnits);
        } catch (Exception $e) {
            return response()->json(
                [
                    'message' => 'Transfer failed',
                    'error'   => 'TRANSFER_FAILED',
                ],
                422
            );
        }

        // Since we're using event sourcing, we don't have a traditional transfer record
        // Just use the provided data for the response
        $transferUuid = Str::uuid()->toString();

        return response()->json(
            [
                'data' => [
                    'uuid'         => $transferUuid,
                    'status'       => 'pending',
                    'from_account' => $fromAccountUuid,
                    'to_account'   => $toAccountUuid,
                    'amount'       => $validated['amount'],
                    'asset_code'   => $validated['asset_code'],
                    'reference'    => $validated['reference'] ?? $validated['description'] ?? null,
                    'created_at'   => now()->toISOString(),
                ],
                'message' => 'Transfer initiated successfully',
            ],
            201
        );
    }

    /**
     * Get transfer details.
     */
    public function show(string $uuid): JsonResponse
    {
        // Since transfers are event sourced, we need to query stored_events
        $event = DB::table('stored_events')
            ->where('event_class', 'App\Domain\Account\Events\MoneyTransferred')
            ->where('aggregate_uuid', $uuid)
            ->first();

        if (! $event) {
            abort(404, 'Transfer not found');
        }

        $properties = json_decode($event->event_properties, true);

        return response()->json(
            [
                'data' => [
                    'uuid'              => $uuid,
                    'from_account_uuid' => $properties['from_uuid'] ?? null,
                    'to_account_uuid'   => $properties['to_uuid'] ?? null,
                    'amount'            => $properties['money']['amount'] ?? 0,
                    'hash'              => $properties['hash']['hash'] ?? null,
                    'created_at'        => $event->created_at,
                    'updated_at'        => $event->created_at,
                ],
            ]
        );
    }

    /**
     * Get transfer history for an account.
     */
    public function history(string $accountUuid): JsonResponse
    {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Since transfers are event sourced, we need to query stored_events
        $events = DB::table('stored_events')
            ->where('event_class', 'App\Domain\Account\Events\MoneyTransferred')
            ->where(
                function ($query) use ($accountUuid) {
                    $query->where('aggregate_uuid', $accountUuid)
                        ->orWhereRaw("event_properties->>'$.to_uuid' = ?", [$accountUuid])
                        ->orWhereRaw("event_properties->>'$.from_uuid' = ?", [$accountUuid]);
                }
            )
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // Transform events to transfer-like format
        $transfers = collect($events->items())->map(
            function ($event) use ($accountUuid) {
                $properties = json_decode($event->event_properties, true);

                return [
                    'uuid'              => $event->aggregate_uuid,
                    'from_account_uuid' => $properties['from_uuid'] ?? $event->aggregate_uuid,
                    'to_account_uuid'   => $properties['to_uuid'] ?? null,
                    'amount'            => $properties['money']['amount'] ?? 0,
                    'direction'         => ($properties['from_uuid'] ?? $event->aggregate_uuid) === $accountUuid ? 'outgoing' : 'incoming',
                    'created_at'        => $event->created_at,
                ];
            }
        );

        return response()->json(
            [
                'data' => $transfers,
                'meta' => [
                    'current_page' => $events->currentPage(),
                    'last_page'    => $events->lastPage(),
                    'per_page'     => $events->perPage(),
                    'total'        => $events->total(),
                ],
            ]
        );
    }
}
