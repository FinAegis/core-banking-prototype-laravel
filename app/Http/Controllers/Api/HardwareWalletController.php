<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\PendingSigningRequest;
use App\Domain\Wallet\Services\HardwareWallet\HardwareWalletManager;
use App\Domain\Wallet\ValueObjects\HardwareWalletDevice;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Hardware Wallet API Controller.
 *
 * Handles hardware wallet device registration, signing requests,
 * and signature submission for Ledger and Trezor devices.
 *
 * @OA\Tag(
 *     name="Hardware Wallets",
 *     description="Hardware wallet management and signing operations"
 * )
 */
class HardwareWalletController extends Controller
{
    public function __construct(
        private readonly HardwareWalletManager $hardwareWalletManager
    ) {
    }

    /**
     * Register a hardware wallet device.
     *
     * @OA\Post(
     *     path="/api/hardware-wallet/register",
     *     summary="Register a hardware wallet device",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_type", "device_id", "public_key", "address", "chain"},
     *             @OA\Property(property="device_type", type="string", enum={"ledger_nano_s", "ledger_nano_x", "trezor_one", "trezor_model_t"}),
     *             @OA\Property(property="device_id", type="string"),
     *             @OA\Property(property="device_label", type="string"),
     *             @OA\Property(property="firmware_version", type="string"),
     *             @OA\Property(property="public_key", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="chain", type="string", enum={"ethereum", "bitcoin", "polygon", "bsc"}),
     *             @OA\Property(property="derivation_path", type="string"),
     *             @OA\Property(property="supported_chains", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Device registered successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_type'        => ['required', 'string', Rule::in(HardwareWalletDevice::SUPPORTED_TYPES)],
            'device_id'          => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'device_label'       => ['nullable', 'string', 'max:100'],
            'firmware_version'   => ['nullable', 'string', 'max:50', 'regex:/^[\d\.]+$/'],
            'public_key'         => ['required', 'string', 'regex:/^(0x)?[a-fA-F0-9]{64,130}$/'],
            'address'            => ['required', 'string', 'regex:/^(0x[a-fA-F0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-zA-HJ-NP-Z0-9]{25,90})$/'],
            'chain'              => ['required', 'string', Rule::in(['ethereum', 'bitcoin', 'polygon', 'bsc'])],
            'derivation_path'    => ['nullable', 'string', 'regex:/^m?\/44\'\/\d+\'\/\d+\'\/\d+\/\d+$/'],
            'supported_chains'   => ['nullable', 'array', 'max:10'],
            'supported_chains.*' => ['string', Rule::in(['ethereum', 'bitcoin', 'polygon', 'bsc'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Security: Check for duplicate registration
        $existingAssociation = HardwareWalletAssociation::where('address', $request->input('address'))
            ->where('chain', $request->input('chain'))
            ->where('is_active', true)
            ->first();

        if ($existingAssociation) {
            return response()->json([
                'error' => 'This address is already registered for this chain',
            ], 422);
        }

        $device = HardwareWalletDevice::create(
            type: $request->input('device_type'),
            deviceId: $request->input('device_id'),
            label: $request->input('device_label', ''),
            firmwareVersion: $request->input('firmware_version', ''),
            supportedChains: $request->input('supported_chains', [$request->input('chain')]),
            publicKey: $request->input('public_key'),
            address: $request->input('address')
        );

        $derivationPath = $request->input('derivation_path')
            ?? $this->hardwareWalletManager->getSupportedChains($request->input('device_type'))[0] ?? '';

        try {
            $association = $this->hardwareWalletManager->registerDevice(
                userId: $user->id,
                device: $device,
                chain: $request->input('chain'),
                derivationPath: $derivationPath ?: $this->getDefaultDerivationPath($request->input('chain'))
            );

            return response()->json([
                'message' => 'Device registered successfully',
                'data'    => [
                    'association_id' => $association->id,
                    'device_type'    => $association->device_type,
                    'address'        => $association->address,
                    'chain'          => $association->chain,
                    'is_verified'    => $association->is_verified,
                    'created_at'     => $association->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a signing request for a hardware wallet transaction.
     *
     * @OA\Post(
     *     path="/api/hardware-wallet/signing-request",
     *     summary="Create a signing request",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"association_id", "transaction"},
     *             @OA\Property(property="association_id", type="string", format="uuid"),
     *             @OA\Property(
     *                 property="transaction",
     *                 type="object",
     *                 required={"from", "to", "value", "chain"},
     *                 @OA\Property(property="from", type="string"),
     *                 @OA\Property(property="to", type="string"),
     *                 @OA\Property(property="value", type="string"),
     *                 @OA\Property(property="chain", type="string"),
     *                 @OA\Property(property="data", type="string"),
     *                 @OA\Property(property="gas_limit", type="string"),
     *                 @OA\Property(property="gas_price", type="string"),
     *                 @OA\Property(property="nonce", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Signing request created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Association not found")
     * )
     */
    public function createSigningRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'association_id'                       => ['required', 'uuid', 'exists:hardware_wallet_associations,id'],
            'transaction.from'                     => ['required', 'string', 'regex:/^(0x[a-fA-F0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-zA-HJ-NP-Z0-9]{25,90})$/'],
            'transaction.to'                       => ['required', 'string', 'regex:/^(0x[a-fA-F0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-zA-HJ-NP-Z0-9]{25,90})$/'],
            'transaction.value'                    => ['required', 'string', 'regex:/^\d+$/'],
            'transaction.chain'                    => ['required', 'string', Rule::in(['ethereum', 'bitcoin', 'polygon', 'bsc'])],
            'transaction.data'                     => ['nullable', 'string', 'regex:/^(0x)?[a-fA-F0-9]*$/'],
            'transaction.gas_limit'                => ['nullable', 'string', 'regex:/^\d+$/'],
            'transaction.gas_price'                => ['nullable', 'string', 'regex:/^\d+$/'],
            'transaction.max_fee_per_gas'          => ['nullable', 'string', 'regex:/^\d+$/'],
            'transaction.max_priority_fee_per_gas' => ['nullable', 'string', 'regex:/^\d+$/'],
            'transaction.nonce'                    => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $association = HardwareWalletAssociation::where('id', $request->input('association_id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $association) {
            return response()->json(['error' => 'Hardware wallet association not found'], 404);
        }

        $txInput = $request->input('transaction');

        // Security: Verify transaction 'from' address matches association address
        $fromAddress = strtolower((string) $txInput['from']);
        $assocAddress = strtolower((string) $association->address);
        if ($fromAddress !== $assocAddress) {
            return response()->json([
                'error' => 'Transaction from address does not match registered device address',
            ], 422);
        }
        $transaction = new TransactionData(
            from: $txInput['from'],
            to: $txInput['to'],
            value: $txInput['value'],
            chain: $txInput['chain'],
            data: $txInput['data'] ?? null,
            gasLimit: $txInput['gas_limit'] ?? null,
            gasPrice: $txInput['gas_price'] ?? null,
            maxFeePerGas: $txInput['max_fee_per_gas'] ?? null,
            maxPriorityFeePerGas: $txInput['max_priority_fee_per_gas'] ?? null,
            nonce: isset($txInput['nonce']) ? (int) $txInput['nonce'] : null
        );

        try {
            $signingRequest = $this->hardwareWalletManager->createSigningRequest($association, $transaction);

            return response()->json([
                'message' => 'Signing request created',
                'data'    => [
                    'request_id'       => $signingRequest->id,
                    'status'           => $signingRequest->status,
                    'raw_data_to_sign' => $signingRequest->raw_data_to_sign,
                    'chain'            => $signingRequest->chain,
                    'expires_at'       => $signingRequest->expires_at->toIso8601String(),
                    'display_data'     => $signingRequest->metadata['display_data'] ?? [],
                    'device_type'      => $association->device_type,
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit a signature for a pending signing request.
     *
     * @OA\Post(
     *     path="/api/hardware-wallet/signing-request/{id}/submit",
     *     summary="Submit signature for signing request",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"signature", "public_key"},
     *             @OA\Property(property="signature", type="string"),
     *             @OA\Property(property="public_key", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Signature submitted successfully"),
     *     @OA\Response(response=422, description="Validation error or signature invalid"),
     *     @OA\Response(response=404, description="Signing request not found")
     * )
     */
    public function submitSignature(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'signature'  => ['required', 'string', 'regex:/^(0x)?[a-fA-F0-9]{128,132}$/'],
            'public_key' => ['required', 'string', 'regex:/^(0x)?[a-fA-F0-9]{64,130}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $signingRequest = PendingSigningRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $signingRequest) {
            return response()->json(['error' => 'Signing request not found'], 404);
        }

        // Security: Verify public key matches the stored association
        $association = $signingRequest->association;
        if ($association) {
            $submittedKey = ltrim($request->input('public_key'), '0x');
            $storedKey = ltrim($association->public_key, '0x');
            if (strcasecmp($submittedKey, $storedKey) !== 0) {
                return response()->json(['error' => 'Public key does not match registered device'], 403);
            }
        }

        try {
            $signedTransaction = $this->hardwareWalletManager->submitSignature(
                $signingRequest,
                $request->input('signature'),
                $request->input('public_key')
            );

            return response()->json([
                'message' => 'Signature submitted successfully',
                'data'    => [
                    'request_id'       => $signingRequest->id,
                    'status'           => 'completed',
                    'transaction_hash' => $signedTransaction->hash,
                    'raw_transaction'  => $signedTransaction->rawTransaction,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get status of a signing request.
     *
     * @OA\Get(
     *     path="/api/hardware-wallet/signing-request/{id}",
     *     summary="Get signing request status",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Signing request details"),
     *     @OA\Response(response=404, description="Signing request not found")
     * )
     */
    public function getSigningRequestStatus(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $signingRequest = PendingSigningRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $signingRequest) {
            return response()->json(['error' => 'Signing request not found'], 404);
        }

        return response()->json([
            'data' => [
                'request_id'              => $signingRequest->id,
                'status'                  => $signingRequest->status,
                'chain'                   => $signingRequest->chain,
                'transaction_data'        => $signingRequest->transaction_data,
                'is_expired'              => $signingRequest->isExpired(),
                'expires_at'              => $signingRequest->expires_at->toIso8601String(),
                'signed_transaction_hash' => $signingRequest->signed_transaction_hash,
                'error_message'           => $signingRequest->error_message,
                'created_at'              => $signingRequest->created_at->toIso8601String(),
                'completed_at'            => $signingRequest->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * List user's hardware wallet associations.
     *
     * @OA\Get(
     *     path="/api/hardware-wallet/associations",
     *     summary="List user's registered hardware wallets",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="chain", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of hardware wallet associations")
     * )
     */
    public function listAssociations(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $chain = $request->query('chain');
        $associations = $this->hardwareWalletManager->getUserAssociations(
            $user->id,
            is_string($chain) ? $chain : null
        );

        return response()->json([
            'data' => $associations->map(fn (HardwareWalletAssociation $assoc) => [
                'id'              => $assoc->id,
                'device_type'     => $assoc->device_type,
                'device_label'    => $assoc->device_label,
                'address'         => $assoc->address,
                'chain'           => $assoc->chain,
                'derivation_path' => $assoc->derivation_path,
                'is_verified'     => $assoc->is_verified,
                'last_used_at'    => $assoc->last_used_at?->toIso8601String(),
                'created_at'      => $assoc->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Remove a hardware wallet association.
     *
     * @OA\Delete(
     *     path="/api/hardware-wallet/associations/{uuid}",
     *     summary="Remove a hardware wallet association",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Association removed"),
     *     @OA\Response(response=404, description="Association not found")
     * )
     */
    public function removeAssociation(Request $request, string $uuid): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $association = HardwareWalletAssociation::where('id', $uuid)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $association) {
            return response()->json(['error' => 'Hardware wallet association not found'], 404);
        }

        $this->hardwareWalletManager->removeAssociation($association);

        return response()->json(['message' => 'Association removed successfully']);
    }

    /**
     * Cancel a pending signing request.
     *
     * @OA\Post(
     *     path="/api/hardware-wallet/signing-request/{id}/cancel",
     *     summary="Cancel a pending signing request",
     *     tags={"Hardware Wallets"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Signing request cancelled"),
     *     @OA\Response(response=404, description="Signing request not found"),
     *     @OA\Response(response=422, description="Cannot cancel request")
     * )
     */
    public function cancelSigningRequest(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $signingRequest = PendingSigningRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $signingRequest) {
            return response()->json(['error' => 'Signing request not found'], 404);
        }

        try {
            $this->hardwareWalletManager->cancelSigningRequest($signingRequest);

            return response()->json(['message' => 'Signing request cancelled']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get supported device types and chains.
     *
     * @OA\Get(
     *     path="/api/hardware-wallet/supported",
     *     summary="Get supported hardware wallet types and chains",
     *     tags={"Hardware Wallets"},
     *     @OA\Response(response=200, description="Supported devices and chains")
     * )
     */
    public function supported(): JsonResponse
    {
        return response()->json([
            'data' => [
                'device_types' => [
                    'ledger_nano_s' => [
                        'name'             => 'Ledger Nano S',
                        'supported_chains' => $this->hardwareWalletManager->getSupportedChains('ledger_nano_s'),
                    ],
                    'ledger_nano_x' => [
                        'name'             => 'Ledger Nano X',
                        'supported_chains' => $this->hardwareWalletManager->getSupportedChains('ledger_nano_x'),
                    ],
                    'trezor_one' => [
                        'name'             => 'Trezor One',
                        'supported_chains' => $this->hardwareWalletManager->getSupportedChains('trezor_one'),
                    ],
                    'trezor_model_t' => [
                        'name'             => 'Trezor Model T',
                        'supported_chains' => $this->hardwareWalletManager->getSupportedChains('trezor_model_t'),
                    ],
                ],
                'chains' => [
                    'ethereum' => ['name' => 'Ethereum', 'coin_type' => 60],
                    'bitcoin'  => ['name' => 'Bitcoin', 'coin_type' => 0],
                    'polygon'  => ['name' => 'Polygon', 'coin_type' => 60],
                    'bsc'      => ['name' => 'Binance Smart Chain', 'coin_type' => 60],
                ],
                'signing_request_ttl_seconds' => config('blockchain.hardware_wallets.signing_request.ttl_seconds', 300),
            ],
        ]);
    }

    /**
     * Get default derivation path for a chain.
     */
    private function getDefaultDerivationPath(string $chain): string
    {
        $coinType = match ($chain) {
            'bitcoin' => 0,
            default   => 60,
        };

        return "m/44'/{$coinType}'/0'/0/0";
    }
}
