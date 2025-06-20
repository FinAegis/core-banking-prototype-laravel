<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Compliance\Services\KycService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService
    ) {}

    /**
     * Get KYC status for authenticated user
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'status' => $user->kyc_status,
            'level' => $user->kyc_level,
            'submitted_at' => $user->kyc_submitted_at,
            'approved_at' => $user->kyc_approved_at,
            'expires_at' => $user->kyc_expires_at,
            'needs_kyc' => $user->needsKyc(),
            'documents' => $user->kycDocuments->map(fn($doc) => [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'status' => $doc->status,
                'uploaded_at' => $doc->uploaded_at,
            ]),
        ]);
    }

    /**
     * Get KYC requirements for a level
     */
    public function requirements(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|in:basic,enhanced,full',
        ]);

        $requirements = $this->kycService->getRequirements($request->level);

        return response()->json([
            'level' => $request->level,
            'requirements' => $requirements,
        ]);
    }

    /**
     * Submit KYC documents
     */
    public function submit(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->kyc_status === 'approved') {
            return response()->json([
                'error' => 'KYC already approved',
            ], 400);
        }

        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|in:passport,national_id,drivers_license,residence_permit,utility_bill,bank_statement,selfie,proof_of_income,other',
            'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
        ]);

        try {
            $this->kycService->submitKyc($user, $request->documents);

            return response()->json([
                'message' => 'KYC documents submitted successfully',
                'status' => 'pending',
            ]);
        } catch (\Exception $e) {
            AuditLog::log(
                'kyc.submission_failed',
                $user,
                null,
                null,
                ['error' => $e->getMessage()],
                'kyc,error'
            );

            return response()->json([
                'error' => 'Failed to submit KYC documents',
            ], 500);
        }
    }

    /**
     * Download a KYC document (user can only download their own)
     */
    public function downloadDocument(string $documentId): mixed
    {
        $user = Auth::user();
        $document = $user->kycDocuments()->findOrFail($documentId);

        if (!Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Document not found');
        }

        AuditLog::log(
            'kyc.document_downloaded',
            $document,
            null,
            null,
            null,
            'kyc,document'
        );

        return Storage::disk('private')->download(
            $document->file_path,
            $document->metadata['original_name'] ?? 'document'
        );
    }
}