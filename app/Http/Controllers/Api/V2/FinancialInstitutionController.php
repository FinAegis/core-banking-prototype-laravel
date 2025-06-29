<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\FinancialInstitutionApplication;
use App\Domain\FinancialInstitution\Services\OnboardingService;
use App\Domain\FinancialInstitution\Services\DocumentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FinancialInstitutionController extends Controller
{
    private OnboardingService $onboardingService;
    private DocumentVerificationService $documentService;
    
    public function __construct(
        OnboardingService $onboardingService,
        DocumentVerificationService $documentService
    ) {
        $this->onboardingService = $onboardingService;
        $this->documentService = $documentService;
    }
    
    /**
     * Get application form structure
     */
    public function getApplicationForm(): JsonResponse
    {
        return response()->json([
            'data' => [
                'institution_types' => FinancialInstitutionApplication::INSTITUTION_TYPES,
                'required_fields' => [
                    'institution_details' => [
                        'institution_name' => 'string|required',
                        'legal_name' => 'string|required',
                        'registration_number' => 'string|required',
                        'tax_id' => 'string|required',
                        'country' => 'string|required|size:2',
                        'institution_type' => 'string|required|in:' . implode(',', array_keys(FinancialInstitutionApplication::INSTITUTION_TYPES)),
                        'assets_under_management' => 'numeric|nullable|min:0',
                        'years_in_operation' => 'integer|required|min:0',
                        'primary_regulator' => 'string|nullable',
                        'regulatory_license_number' => 'string|nullable',
                    ],
                    'contact_information' => [
                        'contact_name' => 'string|required',
                        'contact_email' => 'email|required',
                        'contact_phone' => 'string|required',
                        'contact_position' => 'string|required',
                        'contact_department' => 'string|nullable',
                    ],
                    'address_information' => [
                        'headquarters_address' => 'string|required',
                        'headquarters_city' => 'string|required',
                        'headquarters_state' => 'string|nullable',
                        'headquarters_postal_code' => 'string|required',
                        'headquarters_country' => 'string|required|size:2',
                    ],
                    'business_information' => [
                        'business_description' => 'string|required|min:100',
                        'target_markets' => 'array|required',
                        'product_offerings' => 'array|required',
                        'expected_monthly_transactions' => 'integer|nullable|min:0',
                        'expected_monthly_volume' => 'numeric|nullable|min:0',
                        'required_currencies' => 'array|required',
                    ],
                    'technical_requirements' => [
                        'integration_requirements' => 'array|required',
                        'requires_api_access' => 'boolean',
                        'requires_webhooks' => 'boolean',
                        'requires_reporting' => 'boolean',
                        'security_certifications' => 'array|nullable',
                    ],
                    'compliance_information' => [
                        'has_aml_program' => 'boolean|required',
                        'has_kyc_procedures' => 'boolean|required',
                        'has_data_protection_policy' => 'boolean|required',
                        'is_pci_compliant' => 'boolean|required',
                        'is_gdpr_compliant' => 'boolean|required',
                        'compliance_certifications' => 'array|nullable',
                    ],
                ],
                'document_requirements' => [
                    'certificate_of_incorporation' => 'Certificate of Incorporation',
                    'regulatory_license' => 'Regulatory License',
                    'audited_financials' => 'Audited Financial Statements (Last 3 Years)',
                    'aml_policy' => 'AML/KYC Policy Document',
                    'data_protection_policy' => 'Data Protection Policy',
                ],
            ]
        ]);
    }
    
    /**
     * Submit new application
     */
    public function submitApplication(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Institution Details
            'institution_name' => 'required|string|max:255',
            'legal_name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:255',
            'tax_id' => 'required|string|max:255',
            'country' => 'required|string|size:2',
            'institution_type' => 'required|string|in:' . implode(',', array_keys(FinancialInstitutionApplication::INSTITUTION_TYPES)),
            'assets_under_management' => 'nullable|numeric|min:0',
            'years_in_operation' => 'required|integer|min:0',
            'primary_regulator' => 'nullable|string|max:255',
            'regulatory_license_number' => 'nullable|string|max:255',
            
            // Contact Information
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:50',
            'contact_position' => 'required|string|max:255',
            'contact_department' => 'nullable|string|max:255',
            
            // Address Information
            'headquarters_address' => 'required|string|max:500',
            'headquarters_city' => 'required|string|max:255',
            'headquarters_state' => 'nullable|string|max:255',
            'headquarters_postal_code' => 'required|string|max:50',
            'headquarters_country' => 'required|string|size:2',
            
            // Business Information
            'business_description' => 'required|string|min:100',
            'target_markets' => 'required|array',
            'target_markets.*' => 'string|size:2',
            'product_offerings' => 'required|array',
            'product_offerings.*' => 'string',
            'expected_monthly_transactions' => 'nullable|integer|min:0',
            'expected_monthly_volume' => 'nullable|numeric|min:0',
            'required_currencies' => 'required|array',
            'required_currencies.*' => 'string|size:3',
            
            // Technical Requirements
            'integration_requirements' => 'required|array',
            'integration_requirements.*' => 'string',
            'requires_api_access' => 'boolean',
            'requires_webhooks' => 'boolean',
            'requires_reporting' => 'boolean',
            'security_certifications' => 'nullable|array',
            'security_certifications.*' => 'string',
            
            // Compliance Information
            'has_aml_program' => 'required|boolean',
            'has_kyc_procedures' => 'required|boolean',
            'has_data_protection_policy' => 'required|boolean',
            'is_pci_compliant' => 'required|boolean',
            'is_gdpr_compliant' => 'required|boolean',
            'compliance_certifications' => 'nullable|array',
            'compliance_certifications.*' => 'string',
            
            // Optional
            'source' => 'nullable|string',
            'referral_code' => 'nullable|string',
        ]);
        
        try {
            $application = $this->onboardingService->submitApplication($validated);
            
            return response()->json([
                'data' => [
                    'application_id' => $application->id,
                    'application_number' => $application->application_number,
                    'status' => $application->status,
                    'required_documents' => $application->required_documents,
                    'message' => 'Application submitted successfully',
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to submit FI application', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);
            
            return response()->json([
                'error' => 'Failed to submit application'
            ], 422);
        }
    }
    
    /**
     * Get application status
     */
    public function getApplicationStatus(string $applicationNumber): JsonResponse
    {
        $application = FinancialInstitutionApplication::where('application_number', $applicationNumber)
            ->first();
        
        if (!$application) {
            return response()->json([
                'error' => 'Application not found'
            ], 404);
        }
        
        $documentStatus = $this->documentService->getVerificationStatus($application);
        
        return response()->json([
            'data' => [
                'application_number' => $application->application_number,
                'institution_name' => $application->institution_name,
                'status' => $application->status,
                'review_stage' => $application->review_stage,
                'risk_rating' => $application->risk_rating,
                'submitted_at' => $application->created_at->toIso8601String(),
                'documents' => $documentStatus,
                'is_editable' => $application->isEditable(),
            ]
        ]);
    }
    
    /**
     * Upload document for application
     */
    public function uploadDocument(Request $request, string $applicationNumber): JsonResponse
    {
        $application = FinancialInstitutionApplication::where('application_number', $applicationNumber)
            ->first();
        
        if (!$application) {
            return response()->json([
                'error' => 'Application not found'
            ], 404);
        }
        
        if (!$application->isEditable()) {
            return response()->json([
                'error' => 'Application is not editable in current status'
            ], 422);
        }
        
        $validated = $request->validate([
            'document_type' => 'required|string',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
        ]);
        
        try {
            $document = $this->documentService->uploadDocument(
                $application,
                $validated['document_type'],
                $request->file('document')
            );
            
            return response()->json([
                'data' => [
                    'document_type' => $validated['document_type'],
                    'uploaded' => true,
                    'filename' => $document['original_name'],
                    'size' => $document['size'],
                    'message' => 'Document uploaded successfully',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
    
    /**
     * Get partner API documentation
     */
    public function getApiDocumentation(): JsonResponse
    {
        return response()->json([
            'data' => [
                'base_url' => config('app.url') . '/api/partner/v1',
                'authentication' => [
                    'type' => 'Bearer Token',
                    'header' => 'Authorization: Bearer {api_client_id}:{api_client_secret}',
                ],
                'rate_limits' => [
                    'sandbox' => [
                        'per_minute' => 60,
                        'per_day' => 10000,
                    ],
                    'production' => [
                        'per_minute' => 300,
                        'per_day' => 100000,
                    ],
                ],
                'endpoints' => [
                    'accounts' => [
                        'list' => 'GET /accounts',
                        'create' => 'POST /accounts',
                        'get' => 'GET /accounts/{account_id}',
                        'update' => 'PUT /accounts/{account_id}',
                        'close' => 'POST /accounts/{account_id}/close',
                    ],
                    'transactions' => [
                        'list' => 'GET /transactions',
                        'get' => 'GET /transactions/{transaction_id}',
                        'create' => 'POST /transactions',
                    ],
                    'webhooks' => [
                        'list' => 'GET /webhooks',
                        'create' => 'POST /webhooks',
                        'update' => 'PUT /webhooks/{webhook_id}',
                        'delete' => 'DELETE /webhooks/{webhook_id}',
                    ],
                ],
                'webhook_events' => [
                    'account.created',
                    'account.updated',
                    'account.closed',
                    'transaction.created',
                    'transaction.completed',
                    'transaction.failed',
                ],
                'error_codes' => [
                    '400' => 'Bad Request',
                    '401' => 'Unauthorized',
                    '403' => 'Forbidden',
                    '404' => 'Not Found',
                    '422' => 'Validation Error',
                    '429' => 'Rate Limit Exceeded',
                    '500' => 'Internal Server Error',
                ],
            ]
        ]);
    }
}