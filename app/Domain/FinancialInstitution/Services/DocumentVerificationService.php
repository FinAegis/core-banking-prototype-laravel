<?php

namespace App\Domain\FinancialInstitution\Services;

use App\Models\FinancialInstitutionApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentVerificationService
{
    /**
     * Upload document for application
     */
    public function uploadDocument(
        FinancialInstitutionApplication $application,
        string $documentType,
        UploadedFile $file
    ): array {
        // Validate document type
        $requiredDocs = $application->getRequiredDocuments();
        if (!isset($requiredDocs[$documentType])) {
            throw new \InvalidArgumentException("Invalid document type: {$documentType}");
        }
        
        // Generate secure filename
        $filename = $this->generateSecureFilename($application, $documentType, $file);
        
        // Store file
        $path = $file->storeAs(
            "fi-documents/{$application->id}",
            $filename,
            'private'
        );
        
        // Update application documents
        $documents = $application->submitted_documents ?? [];
        $documents[$documentType] = [
            'filename' => $filename,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
            'verified' => false,
            'verification_status' => 'pending',
        ];
        
        $application->update([
            'submitted_documents' => $documents,
        ]);
        
        return $documents[$documentType];
    }
    
    /**
     * Verify uploaded document
     */
    public function verifyDocument(
        FinancialInstitutionApplication $application,
        string $documentType,
        bool $isValid,
        ?string $notes = null
    ): void {
        $documents = $application->submitted_documents ?? [];
        
        if (!isset($documents[$documentType])) {
            throw new \InvalidArgumentException("Document not found: {$documentType}");
        }
        
        $documents[$documentType]['verified'] = true;
        $documents[$documentType]['verification_status'] = $isValid ? 'approved' : 'rejected';
        $documents[$documentType]['verified_at'] = now()->toIso8601String();
        
        if ($notes) {
            $documents[$documentType]['verification_notes'] = $notes;
        }
        
        $application->update([
            'submitted_documents' => $documents,
        ]);
        
        // Check if all documents are verified
        $this->checkAllDocumentsVerified($application);
    }
    
    /**
     * Check if all required documents are submitted
     */
    public function areAllDocumentsSubmitted(FinancialInstitutionApplication $application): bool
    {
        $required = array_keys($application->getRequiredDocuments());
        $submitted = array_keys($application->submitted_documents ?? []);
        
        return empty(array_diff($required, $submitted));
    }
    
    /**
     * Check if all documents are verified
     */
    public function areAllDocumentsVerified(FinancialInstitutionApplication $application): bool
    {
        $documents = $application->submitted_documents ?? [];
        
        if (!$this->areAllDocumentsSubmitted($application)) {
            return false;
        }
        
        foreach ($documents as $doc) {
            if (!$doc['verified'] || $doc['verification_status'] !== 'approved') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get document verification status
     */
    public function getVerificationStatus(FinancialInstitutionApplication $application): array
    {
        $required = $application->getRequiredDocuments();
        $submitted = $application->submitted_documents ?? [];
        
        $status = [
            'total_required' => count($required),
            'total_submitted' => count($submitted),
            'total_verified' => 0,
            'total_approved' => 0,
            'documents' => [],
        ];
        
        foreach ($required as $type => $name) {
            $doc = $submitted[$type] ?? null;
            
            $docStatus = [
                'type' => $type,
                'name' => $name,
                'submitted' => $doc !== null,
                'verified' => $doc['verified'] ?? false,
                'status' => $doc['verification_status'] ?? 'not_submitted',
            ];
            
            if ($doc && $doc['verified'] ?? false) {
                $status['total_verified']++;
                if ($doc['verification_status'] === 'approved') {
                    $status['total_approved']++;
                }
            }
            
            $status['documents'][] = $docStatus;
        }
        
        $status['all_submitted'] = $status['total_submitted'] === $status['total_required'];
        $status['all_verified'] = $status['total_verified'] === $status['total_required'];
        $status['all_approved'] = $status['total_approved'] === $status['total_required'];
        
        return $status;
    }
    
    /**
     * Download document
     */
    public function downloadDocument(
        FinancialInstitutionApplication $application,
        string $documentType
    ): ?string {
        $documents = $application->submitted_documents ?? [];
        
        if (!isset($documents[$documentType])) {
            return null;
        }
        
        $path = $documents[$documentType]['path'];
        
        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->path($path);
        }
        
        return null;
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename(
        FinancialInstitutionApplication $application,
        string $documentType,
        UploadedFile $file
    ): string {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        
        return "{$application->application_number}_{$documentType}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Check and update if all documents are verified
     */
    private function checkAllDocumentsVerified(FinancialInstitutionApplication $application): void
    {
        if ($this->areAllDocumentsVerified($application)) {
            $application->update([
                'documents_verified' => true,
            ]);
        }
    }
}