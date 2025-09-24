<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Illuminate\Support\Facades\Storage;
use Workflow\Activity;

class ValidateKycDocumentsActivity extends Activity
{
    /**
     * Validate KYC documents.
     */
    public function execute(array $documents, array $requiredDocuments): array
    {
        $failedChecks = [];
        $valid = true;

        // Check for required documents
        foreach ($requiredDocuments as $documentType) {
            if (! isset($documents[$documentType])) {
                $failedChecks[] = "missing_{$documentType}";
                $valid = false;
                continue;
            }

            // Validate document path exists
            $documentPath = $documents[$documentType];
            if (! Storage::exists($documentPath)) {
                $failedChecks[] = "invalid_{$documentType}_path";
                $valid = false;
                continue;
            }

            // Validate file size (max 10MB)
            $fileSize = Storage::size($documentPath);
            if ($fileSize > 10 * 1024 * 1024) {
                $failedChecks[] = "{$documentType}_too_large";
                $valid = false;
                continue;
            }

            // Validate file type (PDF, JPG, PNG)
            $mimeType = Storage::mimeType($documentPath);
            $allowedTypes = [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
            ];

            if (! in_array($mimeType, $allowedTypes, true)) {
                $failedChecks[] = "{$documentType}_invalid_format";
                $valid = false;
            }

            // Check document expiry (simplified check)
            if ($this->isDocumentExpired($documentType, $documentPath)) {
                $failedChecks[] = "{$documentType}_expired";
                $valid = false;
            }
        }

        return [
            'valid'         => $valid,
            'failedChecks'  => $failedChecks,
            'reason'        => $valid ? 'All documents valid' : 'Document validation failed',
            'documentCount' => count($documents),
            'requiredCount' => count($requiredDocuments),
        ];
    }

    /**
     * Check if document is expired (simplified implementation).
     */
    private function isDocumentExpired(string $documentType, string $documentPath): bool
    {
        // In a real implementation, this would use OCR or document parsing
        // to extract and validate expiry dates

        // For demo purposes, check if file is older than 6 months
        $fileTime = Storage::lastModified($documentPath);
        $sixMonthsAgo = now()->subMonths(6)->timestamp;

        // Only check expiry for certain document types
        $expiryCheckTypes = ['government_id', 'passport', 'driving_license'];

        if (in_array($documentType, $expiryCheckTypes, true)) {
            return $fileTime < $sixMonthsAgo;
        }

        return false;
    }
}
