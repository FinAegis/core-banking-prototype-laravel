<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TrustCert;

use App\Domain\TrustCert\Services\PresentationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(
    name: 'TrustCert Presentation',
    description: 'Generate and verify TrustCert credential presentations'
)]
class PresentationController extends Controller
{
    public function __construct(
        private readonly PresentationService $presentationService,
    ) {
    }

    /**
     * Generate a verifiable presentation for a certificate.
     *
     * Creates a time-limited token that can be shared via QR code or deep link
     * to prove certificate ownership without revealing PII.
     */
    #[OA\Post(
        path: '/api/v1/trustcert/{certificateId}/present',
        summary: 'Generate verifiable presentation',
        tags: ['TrustCert Presentation'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'certificateId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'requested_claims', type: 'array', example: ['certificate_type', 'valid_until'], items: new OA\Items(type: 'string')),
        new OA\Property(property: 'validity_minutes', type: 'integer', example: 15),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Presentation generated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'presentation_token', type: 'string'),
        new OA\Property(property: 'qr_code_data', type: 'string'),
        new OA\Property(property: 'deep_link', type: 'string'),
        new OA\Property(property: 'verification_url', type: 'string'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'claims', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid certificate'
    )]
    #[OA\Response(
        response: 404,
        description: 'Certificate not found or not held by the caller'
    )]
    public function present(Request $request, string $certificateId): JsonResponse
    {
        $validated = $request->validate([
            'requested_claims'   => 'nullable|array',
            'requested_claims.*' => 'string',
            'validity_minutes'   => 'nullable|integer|min:1|max:60',
        ]);

        // Only the holder may present a certificate; unknown ids and other
        // holders' certificates both answer "not found".
        if ($this->presentationService->findCertificateHeldBy($certificateId, $request->user()) === null) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'CERTIFICATE_NOT_FOUND',
                    'message' => 'Certificate not found.',
                ],
            ], 404);
        }

        try {
            $presentation = $this->presentationService->generatePresentation(
                certificateId: $certificateId,
                requestedClaims: $validated['requested_claims'] ?? [],
                validityMinutes: $validated['validity_minutes'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data'    => $presentation,
            ]);
        } catch (Throwable $e) {
            Log::error('Presentation generation failed', [
                'certificate_id' => $certificateId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CERT_506',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Verify a presentation token.
     *
     * This endpoint can be called without authentication to verify a presentation
     * shared via QR code or deep link.
     */
    #[OA\Get(
        path: '/api/v1/trustcert/verify/{token}',
        summary: 'Verify presentation token',
        tags: ['TrustCert Presentation'],
        parameters: [
        new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Verification result',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'valid', type: 'boolean'),
        new OA\Property(property: 'certificate_type', type: 'string', nullable: true),
        new OA\Property(property: 'trust_level', type: 'string', nullable: true),
        new OA\Property(property: 'claims', type: 'object'),
        new OA\Property(property: 'issuer', type: 'string', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        ]),
        ])
    )]
    public function verify(string $token): JsonResponse
    {
        try {
            $result = $this->presentationService->verifyPresentation($token);

            return response()->json([
                'success' => $result['valid'],
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Presentation verification failed', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data'    => [
                    'valid' => false,
                    'error' => 'Verification failed: ' . $e->getMessage(),
                ],
            ]);
        }
    }
}
