<?php

namespace App\Http\Controllers;

use App\Models\CgoInvestment;
use App\Services\Cgo\InvestmentAgreementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

class CgoAgreementController extends Controller
{
    protected InvestmentAgreementService $agreementService;

    public function __construct(InvestmentAgreementService $agreementService)
    {
        $this->agreementService = $agreementService;
    }

    /**
     * Generate investment agreement
     */
    public function generateAgreement(Request $request, $investmentUuid)
    {
        try {
            $investment = CgoInvestment::where('uuid', $investmentUuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            
            // Check if agreement already exists
            if ($investment->agreement_path && Storage::exists($investment->agreement_path)) {
                // For JSON requests, return download URL
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Agreement already exists',
                        'download_url' => route('cgo.agreement.download', $investment->uuid),
                    ]);
                }
                // For regular requests, redirect to download
                return redirect()->route('cgo.agreement.download', $investment->uuid);
            }
            
            // Generate new agreement
            $path = $this->agreementService->generateAgreement($investment);
            
            return response()->json([
                'success' => true,
                'message' => 'Agreement generated successfully',
                'download_url' => route('cgo.agreement.download', $investment->uuid),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate agreement', [
                'investment_uuid' => $investmentUuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate agreement. Please try again later.',
            ], 500);
        }
    }
    
    /**
     * Download investment agreement
     */
    public function downloadAgreement($investmentUuid)
    {
        $investment = CgoInvestment::where('uuid', $investmentUuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        if (!$investment->agreement_path || !Storage::exists($investment->agreement_path)) {
            abort(404, 'Agreement not found');
        }
        
        $filename = 'Investment_Agreement_' . $investment->uuid . '.pdf';
        
        return Storage::download($investment->agreement_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
    
    /**
     * Generate investment certificate
     */
    public function generateCertificate(Request $request, $investmentUuid)
    {
        try {
            $investment = CgoInvestment::where('uuid', $investmentUuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            
            // Check if investment is confirmed
            if ($investment->status !== 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate can only be generated for confirmed investments',
                ], 400);
            }
            
            // Check if certificate already exists
            if ($investment->certificate_path && Storage::exists($investment->certificate_path)) {
                return $this->downloadCertificate($investmentUuid);
            }
            
            // Generate new certificate
            $path = $this->agreementService->generateCertificate($investment);
            
            return response()->json([
                'success' => true,
                'message' => 'Certificate generated successfully',
                'download_url' => route('cgo.certificate.download', $investment->uuid),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate certificate', [
                'investment_uuid' => $investmentUuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate certificate. Please try again later.',
            ], 500);
        }
    }
    
    /**
     * Download investment certificate
     */
    public function downloadCertificate($investmentUuid)
    {
        $investment = CgoInvestment::where('uuid', $investmentUuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        if (!$investment->certificate_path || !Storage::exists($investment->certificate_path)) {
            abort(404, 'Certificate not found');
        }
        
        $filename = 'Investment_Certificate_' . $investment->certificate_number . '.pdf';
        
        return Storage::download($investment->certificate_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
    
    /**
     * Preview agreement (admin only)
     */
    public function previewAgreement($investmentUuid)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('super_admin')) {
            abort(403);
        }
        
        $investment = CgoInvestment::where('uuid', $investmentUuid)->firstOrFail();
        
        if (!$investment->agreement_path || !Storage::exists($investment->agreement_path)) {
            abort(404, 'Agreement not found');
        }
        
        return Response::make(Storage::get($investment->agreement_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="agreement_preview.pdf"',
        ]);
    }
    
    /**
     * Mark agreement as signed
     */
    public function markAsSigned(Request $request, $investmentUuid)
    {
        $request->validate([
            'signature_data' => 'required|string', // Base64 encoded signature
        ]);
        
        $investment = CgoInvestment::where('uuid', $investmentUuid)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        // Ensure agreement exists
        if (!$investment->agreement_generated_at) {
            return response()->json([
                'success' => false,
                'message' => 'Agreement must be generated first',
            ], 400);
        }
        
        // Update investment
        $investment->update([
            'agreement_signed_at' => now(),
            'metadata' => array_merge($investment->metadata ?? [], [
                'signature_data' => $request->signature_data,
                'signed_ip' => $request->ip(),
                'signed_user_agent' => $request->userAgent(),
            ]),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Agreement marked as signed',
        ]);
    }
}