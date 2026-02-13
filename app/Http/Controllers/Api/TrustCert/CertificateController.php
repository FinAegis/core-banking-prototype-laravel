<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TrustCert;

use App\Domain\TrustCert\Services\CertificateExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="TrustCert Certificates",
 *     description="TrustCert certificate viewing and export endpoints"
 * )
 */
class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateExportService $exportService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/trustcert/{certId}/certificate",
     *     operationId="trustCertCertificatesShow",
     *     tags={"TrustCert Certificates"},
     *     summary="Get certificate details",
     *     description="Returns detailed information about a TrustCert certificate",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="certId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(string $certId): JsonResponse
    {
        $details = $this->exportService->getCertificateDetails($certId);

        if (! $details) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'CERTIFICATE_NOT_FOUND',
                    'message' => 'Certificate not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $details,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/trustcert/{certId}/export-pdf",
     *     operationId="trustCertCertificatesExportPdf",
     *     tags={"TrustCert Certificates"},
     *     summary="Export certificate as PDF",
     *     description="Exports a TrustCert certificate as a PDF document",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="certId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function exportPdf(string $certId): JsonResponse
    {
        $result = $this->exportService->exportToPdf($certId);

        if (! $result) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'EXPORT_FAILED',
                    'message' => 'Certificate not found or PDF generation failed.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }
}
