<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TrustCert;

use App\Domain\TrustCert\Enums\TrustLevel;
use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileTrustCertController extends Controller
{
    public function __construct(
        private readonly CertificateAuthorityService $certificateAuthority,
    ) {
    }

    /**
     * Get user's current trust certificate and trust level.
     *
     * @OA\Get(
     *     path="/api/v1/trustcert/current",
     *     operationId="trustCertCurrent",
     *     summary="Get current trust certificate",
     *     description="Returns the authenticated user's current trust certificate status and trust level information.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="trust_level", type="string", example="verified"),
     *                 @OA\Property(property="label", type="string", example="Verified"),
     *                 @OA\Property(property="numeric_value", type="integer", example=2),
     *                 @OA\Property(property="certificate", type="object", nullable=true),
     *                 @OA\Property(property="is_valid", type="boolean", example=true),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $subjectId = 'user:' . $user->id;

        $certificate = $this->certificateAuthority->getCertificateBySubject($subjectId);

        if (! $certificate) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'trust_level' => TrustLevel::UNKNOWN->value,
                    'label'       => TrustLevel::UNKNOWN->label(),
                    'certificate' => null,
                ],
            ]);
        }

        $trustLevel = TrustLevel::tryFrom($certificate->extensions['trust_level'] ?? 'unknown')
            ?? TrustLevel::UNKNOWN;

        return response()->json([
            'success' => true,
            'data'    => [
                'trust_level'   => $trustLevel->value,
                'label'         => $trustLevel->label(),
                'numeric_value' => $trustLevel->numericValue(),
                'certificate'   => $certificate->toArray(),
                'is_valid'      => $certificate->isValid(),
                'expires_at'    => $certificate->validUntil->format('c'),
            ],
        ]);
    }

    /**
     * Get all trust levels and their requirements.
     *
     * @OA\Get(
     *     path="/api/v1/trustcert/requirements",
     *     operationId="trustCertRequirements",
     *     summary="Get all trust level requirements",
     *     description="Returns all trust levels with their requirements and associated transaction limits.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="level", type="string", example="verified"),
     *                     @OA\Property(property="label", type="string", example="Verified"),
     *                     @OA\Property(property="numeric_value", type="integer", example=2),
     *                     @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="limits", type="object",
     *                         @OA\Property(property="daily", type="number", example=5000),
     *                         @OA\Property(property="monthly", type="number", example=50000),
     *                         @OA\Property(property="single", type="number", example=2000)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function requirements(): JsonResponse
    {
        $levels = [];
        foreach (TrustLevel::cases() as $level) {
            $levels[] = [
                'level'         => $level->value,
                'label'         => $level->label(),
                'numeric_value' => $level->numericValue(),
                'requirements'  => $level->requirements(),
                'limits'        => $this->getLimitsForLevel($level),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $levels,
        ]);
    }

    /**
     * Get requirements for a specific trust level.
     *
     * @OA\Get(
     *     path="/api/v1/trustcert/requirements/{level}",
     *     operationId="trustCertRequirementsByLevel",
     *     summary="Get requirements for a specific trust level",
     *     description="Returns the requirements and transaction limits for a specific trust level.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="level",
     *         in="path",
     *         required=true,
     *         description="The trust level to retrieve requirements for",
     *         @OA\Schema(type="string", enum={"unknown", "basic", "verified", "high", "ultimate"}, example="verified")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="level", type="string", example="verified"),
     *                 @OA\Property(property="label", type="string", example="Verified"),
     *                 @OA\Property(property="numeric_value", type="integer", example=2),
     *                 @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="limits", type="object",
     *                     @OA\Property(property="daily", type="number", example=5000),
     *                     @OA\Property(property="monthly", type="number", example=50000),
     *                     @OA\Property(property="single", type="number", example=2000)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid trust level",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INVALID_TRUST_LEVEL"),
     *                 @OA\Property(property="message", type="string", example="Trust level not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function requirementsByLevel(string $level): JsonResponse
    {
        $trustLevel = TrustLevel::tryFrom($level);

        if (! $trustLevel) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_TRUST_LEVEL',
                    'message' => 'Trust level not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'level'         => $trustLevel->value,
                'label'         => $trustLevel->label(),
                'numeric_value' => $trustLevel->numericValue(),
                'requirements'  => $trustLevel->requirements(),
                'limits'        => $this->getLimitsForLevel($trustLevel),
            ],
        ]);
    }

    /**
     * Get transaction limits for all trust levels.
     *
     * @OA\Get(
     *     path="/api/v1/trustcert/limits",
     *     operationId="trustCertLimits",
     *     summary="Get transaction limits for all trust levels",
     *     description="Returns the transaction limits (daily, monthly, single) for each trust level.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="level", type="string", example="verified"),
     *                     @OA\Property(property="label", type="string", example="Verified"),
     *                     @OA\Property(property="limits", type="object",
     *                         @OA\Property(property="daily", type="number", example=5000),
     *                         @OA\Property(property="monthly", type="number", example=50000),
     *                         @OA\Property(property="single", type="number", example=2000)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function limits(): JsonResponse
    {
        $limits = [];
        foreach (TrustLevel::cases() as $level) {
            $limits[] = [
                'level'  => $level->value,
                'label'  => $level->label(),
                'limits' => $this->getLimitsForLevel($level),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $limits,
        ]);
    }

    /**
     * Check if a transaction amount is within the user's trust level limits.
     *
     * @OA\Post(
     *     path="/api/v1/trustcert/check-limit",
     *     operationId="trustCertCheckLimit",
     *     summary="Check transaction limit against trust level",
     *     description="Checks whether a given transaction amount is within the authenticated user's trust level limits.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "transaction_type"},
     *             @OA\Property(property="amount", type="number", example=1500.00, description="The transaction amount to check"),
     *             @OA\Property(property="transaction_type", type="string", enum={"daily", "monthly", "single"}, example="single", description="The type of transaction limit to check against")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="allowed", type="boolean", example=true),
     *                 @OA\Property(property="trust_level", type="string", example="verified"),
     *                 @OA\Property(property="limit", type="number", example=2000),
     *                 @OA\Property(property="amount", type="number", example=1500),
     *                 @OA\Property(property="type", type="string", example="single"),
     *                 @OA\Property(property="remaining", type="number", example=500)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function checkLimit(Request $request): JsonResponse
    {
        $request->validate([
            'amount'           => ['required', 'numeric', 'min:0'],
            'transaction_type' => ['required', 'string', 'in:daily,monthly,single'],
        ]);

        $user = $request->user();
        $subjectId = 'user:' . $user->id;

        $certificate = $this->certificateAuthority->getCertificateBySubject($subjectId);
        $trustLevel = TrustLevel::UNKNOWN;

        if ($certificate && $certificate->isValid()) {
            $trustLevel = TrustLevel::tryFrom($certificate->extensions['trust_level'] ?? 'unknown')
                ?? TrustLevel::UNKNOWN;
        }

        $limits = $this->getLimitsForLevel($trustLevel);
        $type = $request->input('transaction_type');
        $amount = (float) $request->input('amount');
        $limit = $limits[$type] ?? 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'allowed'     => $amount <= $limit,
                'trust_level' => $trustLevel->value,
                'limit'       => $limit,
                'amount'      => $amount,
                'type'        => $type,
                'remaining'   => max(0, $limit - $amount),
            ],
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function getLimitsForLevel(TrustLevel $level): array
    {
        return match ($level) {
            TrustLevel::UNKNOWN  => ['daily' => 0, 'monthly' => 0, 'single' => 0],
            TrustLevel::BASIC    => ['daily' => 500, 'monthly' => 5000, 'single' => 200],
            TrustLevel::VERIFIED => ['daily' => 5000, 'monthly' => 50000, 'single' => 2000],
            TrustLevel::HIGH     => ['daily' => 50000, 'monthly' => 500000, 'single' => 25000],
            TrustLevel::ULTIMATE => ['daily' => 500000, 'monthly' => 5000000, 'single' => 250000],
        };
    }
}
