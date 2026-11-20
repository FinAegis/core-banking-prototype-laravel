<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TrustCert;

use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Domain\TrustCert\Services\CertificateExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(
    name: 'TrustCert Certificates',
    description: 'TrustCert certificate viewing and export endpoints'
)]
class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateExportService $exportService,
        private readonly CertificateAuthorityService $certificateAuthority,
    ) {
    }

        #[OA\Get(
            path: '/api/v1/trustcert/{certId}/certificate',
            operationId: 'trustCertCertificatesShow',
            tags: ['TrustCert Certificates'],
            summary: 'Get certificate details',
            description: 'Returns detailed information about a TrustCert certificate',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'certId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 404,
        description: 'Certificate not found or not held by the caller'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function show(Request $request, string $certId): JsonResponse
    {
        // Holder only: another holder's certificate answers like an unknown id.
        $details = $this->isHeldByCaller($request, $certId)
            ? $this->exportService->getCertificateDetails($certId)
            : null;

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

        #[OA\Post(
            path: '/api/v1/trustcert/{certId}/export-pdf',
            operationId: 'trustCertCertificatesExportPdf',
            tags: ['TrustCert Certificates'],
            summary: 'Export certificate as PDF',
            description: 'Exports a TrustCert certificate as a PDF document',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'certId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 404,
        description: 'Certificate not found, not held by the caller, or PDF generation failed'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function exportPdf(Request $request, string $certId): JsonResponse
    {
        // Holder only: another holder's certificate answers like an unknown id
        // (and no PDF is written).
        $result = $this->isHeldByCaller($request, $certId)
            ? $this->exportService->exportToPdf($certId)
            : null;

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

        #[OA\Get(
            path: '/api/v1/trustcert/{certId}/export-pdf/{file}',
            operationId: 'trustCertCertificatesDownloadPdf',
            tags: ['TrustCert Certificates'],
            summary: 'Download an exported certificate PDF',
            description: 'Downloads a PDF made by the export endpoint. Use the pdfUrl it returned: the link is signed, expires at expiresAt, and works only for the certificate holder.',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'certId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'The PDF file',
        content: new OA\MediaType(mediaType: 'application/pdf')
    )]
    #[OA\Response(
        response: 403,
        description: 'Invalid or expired download link'
    )]
    #[OA\Response(
        response: 404,
        description: 'Export not found, expired, or the certificate is not held by the caller'
    )]
    public function downloadPdf(Request $request, string $certId, string $file): Response
    {
        if (! $request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_DOWNLOAD_LINK',
                    'message' => 'Invalid or expired download link.',
                ],
            ], 403);
        }

        $disk = Storage::disk(CertificateExportService::EXPORT_DISK);
        $path = CertificateExportService::exportPath($file);

        // Holder only: another holder's certificate answers like a missing file.
        if (! $this->isHeldByCaller($request, $certId) || ! $disk->exists($path)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'EXPORT_NOT_FOUND',
                    'message' => 'Export not found or expired.',
                ],
            ], 404);
        }

        return $disk->download($path, 'trustcert-certificate.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function isHeldByCaller(Request $request, string $certId): bool
    {
        return $this->certificateAuthority->getCertificate($certId)?->isHeldBy($request->user()) === true;
    }
}
