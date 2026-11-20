<?php

declare(strict_types=1);

namespace App\Domain\TrustCert\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service for exporting TrustCert certificates to PDF.
 *
 * Generates formatted PDF documents with certificate details,
 * trust chain info, and verification QR data.
 */
class CertificateExportService
{
    /**
     * Exported PDFs live on the private local disk (not web-served) and are
     * downloaded through a signed, holder-only route until they expire.
     */
    public const EXPORT_DISK = 'local';

    public const EXPORT_DIRECTORY = 'trustcert-exports';

    public const EXPORT_TTL_MINUTES = 60;

    /** Where releases before the fix wrote exports (public disk). */
    private const LEGACY_PUBLIC_DIRECTORY = 'certs';

    public function __construct(
        private readonly CertificateAuthorityService $caService,
        private readonly PresentationService $presentationService,
    ) {
    }

    /**
     * Get certificate details for API response.
     *
     * @return array<string, mixed>|null
     */
    public function getCertificateDetails(string $certificateId): ?array
    {
        $certificate = $this->caService->getCertificate($certificateId);

        if (! $certificate) {
            return null;
        }

        $subjectName = $certificate->subject['name'] ?? 'Unknown';
        $subjectType = $certificate->subject['type'] ?? 'individual';

        return [
            'certId'             => $certificate->certificateId,
            'status'             => $certificate->isValid() ? 'verified' : $certificate->status->value,
            'verificationStatus' => $certificate->isValid() ? 'Fully Verified' : ucfirst($certificate->status->value),
            'identityId'         => strtoupper(substr(hash('sha256', $certificate->subjectId), 0, 4)) . '-'
                                  . strtoupper(substr(hash('sha256', $certificate->certificateId), 0, 4)) . '-'
                                  . strtoupper(substr($certificate->subjectId, -1)),
            'scope'      => $subjectType === 'individual' ? 'Individual Global Account' : 'Organization Account',
            'validUntil' => $certificate->validUntil->format('Y-m-d'),
            'issuedAt'   => $certificate->validFrom->format('Y-m-d'),
            'disclaimer' => 'This certificate confirms identity trust within the FinAegis ecosystem. Authenticity is cryptographically signed and can be verified via the official verification portal.',
            'qrPayload'  => 'https://trust.finaegis.com/verify/' . $certificate->certificateId,
        ];
    }

    /**
     * Export a certificate to PDF. Stores the file and returns metadata.
     *
     * @return array{pdfUrl: string, expiresAt: string}|null
     */
    public function exportToPdf(string $certificateId): ?array
    {
        $certificate = $this->caService->getCertificate($certificateId);

        if (! $certificate) {
            return null;
        }

        try {
            // Generate a presentation token for the QR code
            $presentation = $this->presentationService->generatePresentation(
                certificateId: $certificateId,
                requestedClaims: ['certificate_type', 'status', 'valid_from', 'valid_until', 'subject_id'],
                validityMinutes: 60,
            );

            $data = [
                'certificate'     => $certificate,
                'verificationUrl' => $presentation['verification_url'] ?? null,
                'qrData'          => $presentation['qr_code_data'] ?? null,
                'deepLink'        => $presentation['deep_link'] ?? null,
                'generatedAt'     => now()->toIso8601String(),
                'disclaimer'      => 'This certificate is issued within the FinAegis ecosystem. It is intended for identity and compliance verification within FinAegis services only.',
                'branding'        => [
                    'name'   => 'FinAegis',
                    'shield' => 'Aegis Shield',
                ],
            ];

            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = Pdf::loadView('trustcert.certificate-pdf', $data);
            $pdf->setPaper('a4', 'portrait');

            $pdfContent = $pdf->output();

            $this->pruneExpiredExports();

            // Store the PDF privately; the URL is a signed link to the
            // holder-only download route, valid until expiresAt.
            $file = Str::random(40);
            Storage::disk(self::EXPORT_DISK)->put(self::exportPath($file), $pdfContent);

            $expiresAt = now()->addMinutes(self::EXPORT_TTL_MINUTES);

            return [
                'pdfUrl' => URL::temporarySignedRoute(
                    'api.trustcert.certificate.download',
                    $expiresAt,
                    ['certId' => $certificateId, 'file' => $file],
                ),
                'expiresAt' => $expiresAt->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('Certificate PDF export failed', [
                'certificate_id' => $certificateId,
                'error'          => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Path of an exported PDF on the export disk.
     */
    public static function exportPath(string $file): string
    {
        return self::EXPORT_DIRECTORY . '/' . $file . '.pdf';
    }

    /**
     * Delete exports older than their link, including PDFs that releases
     * before the fix left on the public disk. Never fails an export.
     */
    private function pruneExpiredExports(): void
    {
        $cutoff = now()->subMinutes(self::EXPORT_TTL_MINUTES)->getTimestamp();

        foreach ([[self::EXPORT_DISK, self::EXPORT_DIRECTORY], ['public', self::LEGACY_PUBLIC_DIRECTORY]] as [$diskName, $directory]) {
            try {
                $disk = Storage::disk($diskName);

                foreach ($disk->files($directory) as $path) {
                    if (str_ends_with($path, '.pdf') && $disk->lastModified($path) < $cutoff) {
                        $disk->delete($path);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('TrustCert export cleanup failed', [
                    'disk'  => $diskName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
