<?php

namespace App\Http\Controllers\Api;

use App\Domain\Account\Services\BankAllocationService;
use App\Domain\Banking\Models\UserBankPreference;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Bank Allocation",
 *     description="User bank allocation preferences and fund distribution management"
 * )
 */
class BankAllocationController extends Controller
{
    public function __construct(
        private BankAllocationService $bankAllocationService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/bank-allocations",
     *     tags={"Bank Allocation"},
     *     summary="Get user bank allocations",
     *     description="Get current user's bank allocation preferences and distribution",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Bank allocations retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                     type="object",
     * @OA\Property(property="allocations",              type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="bank_code",                type="string", example="PAYSERA"),
     * @OA\Property(property="bank_name",                type="string", example="Paysera Bank"),
     * @OA\Property(property="allocation_percentage",    type="number", example=40.0),
     * @OA\Property(property="is_primary",               type="boolean", example=true),
     * @OA\Property(property="status",                   type="string", example="active"),
     * @OA\Property(property="metadata",                 type="object",
     * @OA\Property(property="country",                  type="string", example="Lithuania"),
     * @OA\Property(property="currency",                 type="string", example="EUR"),
     * @OA\Property(property="insurance_limit",          type="integer", example=100000)
     *                         )
     *                     )
     *                 ),
     * @OA\Property(property="summary",                  type="object",
     * @OA\Property(property="total_percentage",         type="number", example=100.0),
     * @OA\Property(property="bank_count",               type="integer", example=3),
     * @OA\Property(property="primary_bank",             type="string", example="PAYSERA"),
     * @OA\Property(property="is_diversified",           type="boolean", example=true),
     * @OA\Property(property="total_insurance_coverage", type="integer", example=300000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        /** @var User $user */
        $allocations = $user->bankPreferences()->getQuery()->where('is_active', true)->get();

        if ($allocations->isEmpty()) {
            // Setup default allocations if none exist
            $allocations = $this->bankAllocationService->setupDefaultAllocations($user);
        }

        $totalPercentage = $allocations->sum('allocation_percentage');
        $primaryBank = $allocations->where('is_primary', true)->first();

        // Calculate insurance coverage
        $totalInsurance = $allocations->sum(
            function ($allocation) {
                return $allocation->metadata['deposit_insurance'] ?? 100000;
            }
        );

        return response()->json(
            [
                'data' => [
                    'allocations' => $allocations->map(
                        function ($allocation) {
                            return [
                                'bank_code'             => $allocation->bank_code,
                                'bank_name'             => $allocation->bank_name,
                                'allocation_percentage' => $allocation->allocation_percentage,
                                'is_primary'            => $allocation->is_primary,
                                'status'                => $allocation->status,
                                'metadata'              => $allocation->metadata,
                            ];
                        }
                    ),
                    'summary' => [
                        'total_percentage'         => $totalPercentage,
                        'bank_count'               => $allocations->count(),
                        'primary_bank'             => $primaryBank?->bank_code,
                        'is_diversified'           => $allocations->count() >= 3,
                        'total_insurance_coverage' => $totalInsurance,
                    ],
                ],
            ]
        );
    }

    /**
     * @OA\Put(
     *     path="/api/bank-allocations",
     *     tags={"Bank Allocation"},
     *     summary="Update bank allocations",
     *     description="Update user's bank allocation preferences",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"allocations"},
     *
     * @OA\Property(property="allocations",           type="object", example={"PAYSERA": 40, "DEUTSCHE_BANK": 30, "SANTANDER": 30}),
     * @OA\Property(property="primary_bank",          type="string", example="PAYSERA")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Bank allocations updated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",               type="string", example="Bank allocations updated successfully"),
     * @OA\Property(property="data",                  type="object",
     * @OA\Property(property="allocations",           type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="bank_code",             type="string", example="PAYSERA"),
     * @OA\Property(property="allocation_percentage", type="number", example=40.0),
     * @OA\Property(property="is_primary",            type="boolean", example=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error - allocations must sum to 100%"
     *     )
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'allocations'   => 'required|array',
                'allocations.*' => 'numeric|min:0|max:100',
                'primary_bank'  => 'nullable|string|in:' . implode(',', array_keys(UserBankPreference::AVAILABLE_BANKS)),
            ]
        );

        try {
            $user = Auth::user();
            /** @var User $user */
            $allocations = $this->bankAllocationService->updateAllocations($user, $validated['allocations']);

            // Set primary bank if specified
            if (isset($validated['primary_bank'])) {
                $updatedPrimary = $this->bankAllocationService->setPrimaryBank($user, $validated['primary_bank']);
                // Force refresh all allocations from database to get updated is_primary values
                $allocations = UserBankPreference::where('user_uuid', $user->uuid)
                    ->where('status', 'active')
                    ->get();
            }

            return response()->json(
                [
                    'message' => 'Bank allocations updated successfully',
                    'data'    => [
                        'allocations' => $allocations->map(
                            function ($allocation) {
                                return [
                                    'bank_code'             => $allocation->bank_code,
                                    'bank_name'             => $allocation->bank_name,
                                    'allocation_percentage' => $allocation->allocation_percentage,
                                    'is_primary'            => $allocation->is_primary,
                                    'status'                => $allocation->status,
                                ];
                            }
                        ),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Failed to update bank allocations',
                    'error'   => $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bank-allocations/banks",
     *     tags={"Bank Allocation"},
     *     summary="Add bank to allocation",
     *     description="Add a new bank to user's allocation preferences",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"bank_code", "percentage"},
     *
     * @OA\Property(property="bank_code",             type="string", example="REVOLUT"),
     * @OA\Property(property="percentage",            type="number", minimum=0.01, maximum=100, example=15.0)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Bank added successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",               type="string", example="Bank added to allocation successfully"),
     * @OA\Property(property="data",                  type="object",
     * @OA\Property(property="bank_code",             type="string", example="REVOLUT"),
     * @OA\Property(property="bank_name",             type="string", example="Revolut Bank"),
     * @OA\Property(property="allocation_percentage", type="number", example=15.0),
     * @OA\Property(property="is_primary",            type="boolean", example=false),
     * @OA\Property(property="status",                type="string", example="active")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error or bank already exists"
     *     )
     * )
     */
    public function addBank(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'bank_code'  => 'required|string|in:' . implode(',', array_keys(UserBankPreference::AVAILABLE_BANKS)),
                'percentage' => 'required|numeric|min:0.01|max:100',
            ]
        );

        try {
            $user = Auth::user();
            /** @var User $user */
            $preference = $this->bankAllocationService->addBank($user, $validated['bank_code'], $validated['percentage']);

            return response()->json(
                [
                    'message' => 'Bank added to allocation successfully',
                    'data'    => [
                        'bank_code'             => $preference->bank_code,
                        'bank_name'             => $preference->bank_name,
                        'allocation_percentage' => $preference->allocation_percentage,
                        'is_primary'            => $preference->is_primary,
                        'status'                => $preference->status,
                    ],
                ],
                201
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Failed to add bank to allocation',
                    'error'   => $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/bank-allocations/banks/{bankCode}",
     *     tags={"Bank Allocation"},
     *     summary="Remove bank from allocation",
     *     description="Remove a bank from user's allocation preferences",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="bankCode",
     *         in="path",
     *         required=true,
     *         description="Bank code to remove",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Bank removed successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",    type="string", example="Bank removed from allocation successfully"),
     * @OA\Property(property="data",       type="object",
     * @OA\Property(property="bank_code",  type="string", example="REVOLUT"),
     * @OA\Property(property="removed_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Cannot remove primary bank or bank not found"
     *     )
     * )
     */
    public function removeBank(string $bankCode): JsonResponse
    {
        try {
            $user = Auth::user();
            /** @var User $user */
            $this->bankAllocationService->removeBank($user, $bankCode);

            return response()->json(
                [
                    'message' => 'Bank removed from allocation successfully',
                    'data'    => [
                        'bank_code'  => $bankCode,
                        'removed_at' => now()->toISOString(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Failed to remove bank from allocation',
                    'error'   => $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/bank-allocations/primary/{bankCode}",
     *     tags={"Bank Allocation"},
     *     summary="Set primary bank",
     *     description="Set a bank as the primary bank for the user",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="bankCode",
     *         in="path",
     *         required=true,
     *         description="Bank code to set as primary",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Primary bank updated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",    type="string", example="Primary bank updated successfully"),
     * @OA\Property(property="data",       type="object",
     * @OA\Property(property="bank_code",  type="string", example="PAYSERA"),
     * @OA\Property(property="bank_name",  type="string", example="Paysera Bank"),
     * @OA\Property(property="is_primary", type="boolean", example=true),
     * @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Bank not found in user's allocation"
     *     )
     * )
     */
    public function setPrimaryBank(string $bankCode): JsonResponse
    {
        try {
            $user = Auth::user();
            /** @var User $user */
            $preference = $this->bankAllocationService->setPrimaryBank($user, $bankCode);

            return response()->json(
                [
                    'message' => 'Primary bank updated successfully',
                    'data'    => [
                        'bank_code'  => $preference->bank_code,
                        'bank_name'  => $preference->bank_name,
                        'is_primary' => $preference->is_primary,
                        'updated_at' => $preference->updated_at->toISOString(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Failed to set primary bank',
                    'error'   => $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/bank-allocations/available-banks",
     *     tags={"Bank Allocation"},
     *     summary="Get available banks",
     *     description="Get list of all available banks for allocation",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Available banks retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",               type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="bank_code",          type="string", example="PAYSERA"),
     * @OA\Property(property="bank_name",          type="string", example="Paysera Bank"),
     * @OA\Property(property="country",            type="string", example="Lithuania"),
     * @OA\Property(property="currency",           type="string", example="EUR"),
     * @OA\Property(property="insurance_limit",    type="integer", example=100000),
     * @OA\Property(property="supported_features", type="array", @OA\Items(type="string"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAvailableBanks(): JsonResponse
    {
        $banks = collect(UserBankPreference::AVAILABLE_BANKS)->map(
            function (array $bankInfo, string $bankCode) {
                return [
                    'bank_code'          => $bankCode,
                    'bank_name'          => $bankInfo['name'],
                    'country'            => $bankInfo['country'],
                    'currency'           => 'EUR', // All banks currently operate in EUR
                    'insurance_limit'    => $bankInfo['deposit_insurance'],
                    'supported_features' => $bankInfo['features'],
                ];
            }
        )->values();

        return response()->json(
            [
                'data' => $banks,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/bank-allocations/distribution-preview",
     *     tags={"Bank Allocation"},
     *     summary="Preview fund distribution",
     *     description="Preview how funds would be distributed across banks for a given amount",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"amount", "asset_code"},
     *
     * @OA\Property(property="amount",                   type="number", minimum=0.01, example=1000.00),
     * @OA\Property(property="asset_code",               type="string", example="USD")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Distribution preview generated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                     type="object",
     * @OA\Property(property="total_amount",             type="number", example=1000.00),
     * @OA\Property(property="asset_code",               type="string", example="USD"),
     * @OA\Property(property="distribution",             type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="bank_code",                type="string", example="PAYSERA"),
     * @OA\Property(property="bank_name",                type="string", example="Paysera Bank"),
     * @OA\Property(property="allocation_percentage",    type="number", example=40.0),
     * @OA\Property(property="amount",                   type="number", example=400.00),
     * @OA\Property(property="is_primary",               type="boolean", example=true)
     *                     )
     *                 ),
     * @OA\Property(property="summary",                  type="object",
     * @OA\Property(property="bank_count",               type="integer", example=3),
     * @OA\Property(property="is_diversified",           type="boolean", example=true),
     * @OA\Property(property="total_insurance_coverage", type="integer", example=300000)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function previewDistribution(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'amount'     => 'required|numeric|min:0.01',
                'asset_code' => 'required|string|exists:assets,code',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        $amountInCents = (int) ($validated['amount'] * 100);

        $summary = $this->bankAllocationService->getDistributionSummary($user, $amountInCents);

        if (isset($summary['error'])) {
            return response()->json(
                [
                    'message' => 'Failed to generate distribution preview',
                    'error'   => $summary['error'],
                ],
                422
            );
        }

        // Convert distribution from cents back to float for API response
        $distribution = collect($summary['distribution'])->map(
            function ($bankDistribution) {
                return [
                    'bank_code'             => $bankDistribution['bank_code'],
                    'bank_name'             => $bankDistribution['bank_name'],
                    'allocation_percentage' => $bankDistribution['percentage'],
                    'amount'                => $bankDistribution['amount'] / 100,
                    'is_primary'            => $bankDistribution['is_primary'],
                ];
            }
        );

        return response()->json(
            [
                'data' => [
                    'total_amount' => $validated['amount'],
                    'asset_code'   => $validated['asset_code'],
                    'distribution' => $distribution,
                    'summary'      => [
                        'bank_count'               => $summary['bank_count'],
                        'is_diversified'           => $summary['is_diversified'],
                        'total_insurance_coverage' => $summary['total_insurance_coverage'],
                    ],
                ],
            ]
        );
    }
}
