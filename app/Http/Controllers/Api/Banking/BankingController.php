<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Banking;

use App\Domain\Banking\Exceptions\BankConnectionException;
use App\Domain\Banking\Exceptions\BankNotFoundException;
use App\Domain\Banking\Services\BankIntegrationService;
use App\Domain\Banking\Services\BankTransferService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StaffRoles;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankingController extends Controller
{
    public function __construct(
        private readonly BankIntegrationService $bankIntegrationService,
        private readonly BankTransferService $bankTransferService,
    ) {
    }

    /**
     * Connect the authenticated user to a bank.
     */
    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_code'   => 'required|string|max:50',
            'credentials' => 'required|array',
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $connection = $this->bankIntegrationService->connectUserToBank(
                $user,
                $validated['bank_code'],
                $validated['credentials'],
            );

            return response()->json([
                'data' => [
                    'id'        => $connection->id,
                    'bank_code' => $connection->bankCode,
                    'status'    => $connection->status,
                    'metadata'  => $connection->metadata,
                ],
                'message' => 'Bank connection established successfully.',
            ], 201);
        } catch (BankNotFoundException $e) {
            return response()->json([
                'error'   => 'Bank not found.',
                'message' => $e->getMessage(),
            ], 404);
        } catch (BankConnectionException $e) {
            return response()->json([
                'error'   => 'Connection failed.',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Disconnect the authenticated user from a bank.
     */
    public function disconnect(Request $request, string $connectionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $disconnected = $this->bankIntegrationService->disconnectUserFromBank(
                $user,
                $connectionId,
            );

            if (! $disconnected) {
                return response()->json([
                    'error'   => 'Not found.',
                    'message' => 'No active connection found for the given bank code.',
                ], 404);
            }

            return response()->json([
                'data'    => ['disconnected' => true],
                'message' => 'Bank disconnected successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to disconnect bank', [
                'connection_id' => $connectionId,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Disconnection failed.',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    /**
     * List bank connections for the authenticated user.
     */
    public function connections(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $connections = $this->bankIntegrationService->getUserBankConnections($user);

        return response()->json([
            'data' => $connections->map(fn ($conn) => [
                'id'           => $conn->id,
                'bank_code'    => $conn->bankCode,
                'status'       => $conn->status,
                'permissions'  => $conn->permissions,
                'last_sync_at' => $conn->lastSyncAt?->toIso8601String(),
                'expires_at'   => $conn->expiresAt?->toIso8601String(),
                'metadata'     => $conn->metadata,
            ])->values()->toArray(),
        ]);
    }

    /**
     * List bank accounts for the authenticated user.
     */
    public function accounts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bankCode = $request->query('bank_code');

        $accounts = $this->bankIntegrationService->getUserBankAccounts(
            $user,
            is_string($bankCode) ? $bankCode : null,
        );

        return response()->json([
            'data' => $accounts->map(fn ($account) => [
                'id'           => $account->id,
                'bank_code'    => $account->bankCode,
                'currency'     => $account->currency,
                'account_type' => $account->accountType,
                'status'       => $account->status,
                'iban'         => $account->iban,
                'swift'        => $account->swift,
            ])->values()->toArray(),
        ]);
    }

    /**
     * Sync bank accounts for a specific connection.
     */
    public function syncAccounts(Request $request, string $connectionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $accounts = $this->bankIntegrationService->syncBankAccounts(
                $user,
                $connectionId,
            );

            return response()->json([
                'data' => [
                    'synced_count' => $accounts->count(),
                ],
                'message' => 'Bank accounts synced successfully.',
            ]);
        } catch (BankNotFoundException $e) {
            return response()->json([
                'error'   => 'Bank not found.',
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            Log::error('Failed to sync bank accounts', [
                'connection_id' => $connectionId,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Sync failed.',
                'message' => 'An unexpected error occurred during account sync.',
            ], 500);
        }
    }

    /**
     * Initiate an inter-bank transfer.
     */
    public function initiateTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|string',
            'to_account_id'   => 'sometimes|string',
            'to_iban'         => 'sometimes|string|max:34',
            'to_bank_code'    => 'sometimes|string|max:50',
            'amount'          => 'required|numeric|min:0.01',
            'currency'        => 'required|string|size:3',
            'reference'       => 'sometimes|string|max:140',
            'description'     => 'sometimes|string|max:255',
        ]);

        /** @var User $user */
        $user = $request->user();

        $validated['user_uuid'] = $user->uuid;

        try {
            $result = $this->bankTransferService->initiate($validated);

            return response()->json([
                'data'    => $result,
                'message' => 'Transfer initiated successfully.',
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to initiate transfer', [
                'user_uuid' => $user->uuid,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Transfer failed.',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get transfer status.
     */
    public function transferStatus(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // The user who initiated the transfer, or an operator (same rule as
        // the GraphQL cancelTransfer). The ownership check reads the table:
        // the cached status payload carries no owner. Not owned answers the
        // same 404 as an unknown id.
        $owned = DB::table('bank_transfers')
            ->where('id', $id)
            ->where('user_uuid', $user->uuid)
            ->exists();

        if (! $owned && ! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            return response()->json([
                'error'   => 'Not found.',
                'message' => 'Transfer not found.',
            ], 404);
        }

        $status = $this->bankTransferService->getStatus($id);

        if ($status['status'] === 'not_found') {
            return response()->json([
                'error'   => 'Not found.',
                'message' => 'Transfer not found.',
            ], 404);
        }

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Check bank health.
     */
    public function bankHealth(string $bankCode): JsonResponse
    {
        try {
            $health = $this->bankIntegrationService->checkBankHealth($bankCode);

            return response()->json([
                'data' => $health,
            ]);
        } catch (BankNotFoundException $e) {
            return response()->json([
                'error'   => 'Bank not found.',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
