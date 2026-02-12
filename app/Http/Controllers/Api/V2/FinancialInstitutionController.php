<?php

namespace App\Http\Controllers\Api\V2;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Domain\FinancialInstitution\Services\DocumentVerificationService;
use App\Domain\FinancialInstitution\Services\OnboardingService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Get application form structure.
     *
     * @OA\Get(
     *     path="/api/v2/financial-institutions/application-form",
     *     operationId="fiGetApplicationForm",
     *     summary="Get application form structure",
     *     description="Returns the application form structure including institution types, required fields, and document requirements for financial institution onboarding.",
     *     tags={"BaaS Onboarding"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="institution_types", type="object"),
     *                 @OA\Property(property="required_fields", type="object",
     *                     @OA\Property(property="institution_details", type="object"),
     *                     @OA\Property(property="contact_information", type="object"),
     *                     @OA\Property(property="address_information", type="object"),
     *                     @OA\Property(property="business_information", type="object"),
     *                     @OA\Property(property="technical_requirements", type="object"),
     *                     @OA\Property(property="compliance_information", type="object")
     *                 ),
     *                 @OA\Property(property="document_requirements", type="object",
     *                     @OA\Property(property="certificate_of_incorporation", type="string", example="Certificate of Incorporation"),
     *                     @OA\Property(property="regulatory_license", type="string", example="Regulatory License"),
     *                     @OA\Property(property="audited_financials", type="string", example="Audited Financial Statements (Last 3 Years)"),
     *                     @OA\Property(property="aml_policy", type="string", example="AML/KYC Policy Document"),
     *                     @OA\Property(property="data_protection_policy", type="string", example="Data Protection Policy")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getApplicationForm(): JsonResponse
    {
        return response()->json(
            [
                'data' => [
                    'institution_types' => FinancialInstitutionApplication::INSTITUTION_TYPES,
                    'required_fields'   => [
                        'institution_details' => [
                            'institution_name'          => 'string|required',
                            'legal_name'                => 'string|required',
                            'registration_number'       => 'string|required',
                            'tax_id'                    => 'string|required',
                            'country'                   => 'string|required|size:2',
                            'institution_type'          => 'string|required|in:' . implode(',', array_keys(FinancialInstitutionApplication::INSTITUTION_TYPES)),
                            'assets_under_management'   => 'numeric|nullable|min:0',
                            'years_in_operation'        => 'integer|required|min:0',
                            'primary_regulator'         => 'string|nullable',
                            'regulatory_license_number' => 'string|nullable',
                        ],
                        'contact_information' => [
                            'contact_name'       => 'string|required',
                            'contact_email'      => 'email|required',
                            'contact_phone'      => 'string|required',
                            'contact_position'   => 'string|required',
                            'contact_department' => 'string|nullable',
                        ],
                        'address_information' => [
                            'headquarters_address'     => 'string|required',
                            'headquarters_city'        => 'string|required',
                            'headquarters_state'       => 'string|nullable',
                            'headquarters_postal_code' => 'string|required',
                            'headquarters_country'     => 'string|required|size:2',
                        ],
                        'business_information' => [
                            'business_description'          => 'string|required|min:100',
                            'target_markets'                => 'array|required',
                            'product_offerings'             => 'array|required',
                            'expected_monthly_transactions' => 'integer|nullable|min:0',
                            'expected_monthly_volume'       => 'numeric|nullable|min:0',
                            'required_currencies'           => 'array|required',
                        ],
                        'technical_requirements' => [
                            'integration_requirements' => 'array|required',
                            'requires_api_access'      => 'boolean',
                            'requires_webhooks'        => 'boolean',
                            'requires_reporting'       => 'boolean',
                            'security_certifications'  => 'array|nullable',
                        ],
                        'compliance_information' => [
                            'has_aml_program'            => 'boolean|required',
                            'has_kyc_procedures'         => 'boolean|required',
                            'has_data_protection_policy' => 'boolean|required',
                            'is_pci_compliant'           => 'boolean|required',
                            'is_gdpr_compliant'          => 'boolean|required',
                            'compliance_certifications'  => 'array|nullable',
                        ],
                    ],
                    'document_requirements' => [
                        'certificate_of_incorporation' => 'Certificate of Incorporation',
                        'regulatory_license'           => 'Regulatory License',
                        'audited_financials'           => 'Audited Financial Statements (Last 3 Years)',
                        'aml_policy'                   => 'AML/KYC Policy Document',
                        'data_protection_policy'       => 'Data Protection Policy',
                    ],
                ],
            ]
        );
    }

    /**
     * Submit new application.
     *
     * @OA\Post(
     *     path="/api/v2/financial-institutions/apply",
     *     operationId="fiSubmitApplication",
     *     summary="Submit financial institution application",
     *     description="Submits a new financial institution onboarding application with institution details, contact information, business information, technical requirements, and compliance information.",
     *     tags={"BaaS Onboarding"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"institution_name", "legal_name", "registration_number", "tax_id", "country", "institution_type", "years_in_operation", "contact_name", "contact_email", "contact_phone", "contact_position", "headquarters_address", "headquarters_city", "headquarters_postal_code", "headquarters_country", "business_description", "target_markets", "product_offerings", "required_currencies", "integration_requirements", "has_aml_program", "has_kyc_procedures", "has_data_protection_policy", "is_pci_compliant", "is_gdpr_compliant"},
     *             @OA\Property(property="institution_name", type="string", example="Acme Finance Ltd"),
     *             @OA\Property(property="legal_name", type="string", example="Acme Finance Limited"),
     *             @OA\Property(property="registration_number", type="string", example="REG-12345678"),
     *             @OA\Property(property="tax_id", type="string", example="TAX-87654321"),
     *             @OA\Property(property="country", type="string", minLength=2, maxLength=2, example="GB"),
     *             @OA\Property(property="institution_type", type="string", example="bank"),
     *             @OA\Property(property="assets_under_management", type="number", nullable=true, example=50000000),
     *             @OA\Property(property="years_in_operation", type="integer", example=10),
     *             @OA\Property(property="primary_regulator", type="string", nullable=true, example="FCA"),
     *             @OA\Property(property="regulatory_license_number", type="string", nullable=true, example="FCA-123456"),
     *             @OA\Property(property="contact_name", type="string", example="John Doe"),
     *             @OA\Property(property="contact_email", type="string", format="email", example="john@acmefinance.com"),
     *             @OA\Property(property="contact_phone", type="string", example="+44 20 7946 0958"),
     *             @OA\Property(property="contact_position", type="string", example="CTO"),
     *             @OA\Property(property="contact_department", type="string", nullable=true, example="Technology"),
     *             @OA\Property(property="headquarters_address", type="string", example="123 Finance Street"),
     *             @OA\Property(property="headquarters_city", type="string", example="London"),
     *             @OA\Property(property="headquarters_state", type="string", nullable=true, example="Greater London"),
     *             @OA\Property(property="headquarters_postal_code", type="string", example="EC1A 1BB"),
     *             @OA\Property(property="headquarters_country", type="string", minLength=2, maxLength=2, example="GB"),
     *             @OA\Property(property="business_description", type="string", example="A comprehensive digital banking platform providing retail and corporate banking services across Europe with focus on innovative payment solutions."),
     *             @OA\Property(property="target_markets", type="array", @OA\Items(type="string", example="GB")),
     *             @OA\Property(property="product_offerings", type="array", @OA\Items(type="string", example="payments")),
     *             @OA\Property(property="expected_monthly_transactions", type="integer", nullable=true, example=50000),
     *             @OA\Property(property="expected_monthly_volume", type="number", nullable=true, example=10000000),
     *             @OA\Property(property="required_currencies", type="array", @OA\Items(type="string", example="GBP")),
     *             @OA\Property(property="integration_requirements", type="array", @OA\Items(type="string", example="rest_api")),
     *             @OA\Property(property="requires_api_access", type="boolean", example=true),
     *             @OA\Property(property="requires_webhooks", type="boolean", example=true),
     *             @OA\Property(property="requires_reporting", type="boolean", example=true),
     *             @OA\Property(property="security_certifications", type="array", nullable=true, @OA\Items(type="string", example="ISO27001")),
     *             @OA\Property(property="has_aml_program", type="boolean", example=true),
     *             @OA\Property(property="has_kyc_procedures", type="boolean", example=true),
     *             @OA\Property(property="has_data_protection_policy", type="boolean", example=true),
     *             @OA\Property(property="is_pci_compliant", type="boolean", example=true),
     *             @OA\Property(property="is_gdpr_compliant", type="boolean", example=true),
     *             @OA\Property(property="compliance_certifications", type="array", nullable=true, @OA\Items(type="string", example="PCI-DSS")),
     *             @OA\Property(property="source", type="string", nullable=true, example="website"),
     *             @OA\Property(property="referral_code", type="string", nullable=true, example="REF-2024-001")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Application submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="application_id", type="string", example="app_abc123"),
     *                 @OA\Property(property="application_number", type="string", example="FI-2024-00001"),
     *                 @OA\Property(property="status", type="string", example="pending_review"),
     *                 @OA\Property(property="required_documents", type="array", @OA\Items(type="string", example="certificate_of_incorporation")),
     *                 @OA\Property(property="message", type="string", example="Application submitted successfully")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or submission failure")
     * )
     */
    public function submitApplication(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                // Institution Details
                'institution_name'          => 'required|string|max:255',
                'legal_name'                => 'required|string|max:255',
                'registration_number'       => 'required|string|max:255',
                'tax_id'                    => 'required|string|max:255',
                'country'                   => 'required|string|size:2',
                'institution_type'          => 'required|string|in:' . implode(',', array_keys(FinancialInstitutionApplication::INSTITUTION_TYPES)),
                'assets_under_management'   => 'nullable|numeric|min:0',
                'years_in_operation'        => 'required|integer|min:0',
                'primary_regulator'         => 'nullable|string|max:255',
                'regulatory_license_number' => 'nullable|string|max:255',

                // Contact Information
                'contact_name'       => 'required|string|max:255',
                'contact_email'      => 'required|email|max:255',
                'contact_phone'      => 'required|string|max:50',
                'contact_position'   => 'required|string|max:255',
                'contact_department' => 'nullable|string|max:255',

                // Address Information
                'headquarters_address'     => 'required|string|max:500',
                'headquarters_city'        => 'required|string|max:255',
                'headquarters_state'       => 'nullable|string|max:255',
                'headquarters_postal_code' => 'required|string|max:50',
                'headquarters_country'     => 'required|string|size:2',

                // Business Information
                'business_description'          => 'required|string|min:100',
                'target_markets'                => 'required|array',
                'target_markets.*'              => 'string|size:2',
                'product_offerings'             => 'required|array',
                'product_offerings.*'           => 'string',
                'expected_monthly_transactions' => 'nullable|integer|min:0',
                'expected_monthly_volume'       => 'nullable|numeric|min:0',
                'required_currencies'           => 'required|array',
                'required_currencies.*'         => 'string|size:3',

                // Technical Requirements
                'integration_requirements'   => 'required|array',
                'integration_requirements.*' => 'string',
                'requires_api_access'        => 'boolean',
                'requires_webhooks'          => 'boolean',
                'requires_reporting'         => 'boolean',
                'security_certifications'    => 'nullable|array',
                'security_certifications.*'  => 'string',

                // Compliance Information
                'has_aml_program'             => 'required|boolean',
                'has_kyc_procedures'          => 'required|boolean',
                'has_data_protection_policy'  => 'required|boolean',
                'is_pci_compliant'            => 'required|boolean',
                'is_gdpr_compliant'           => 'required|boolean',
                'compliance_certifications'   => 'nullable|array',
                'compliance_certifications.*' => 'string',

                // Optional
                'source'        => 'nullable|string',
                'referral_code' => 'nullable|string',
            ]
        );

        try {
            $application = $this->onboardingService->submitApplication($validated);

            return response()->json(
                [
                    'data' => [
                        'application_id'     => $application->id,
                        'application_number' => $application->application_number,
                        'status'             => $application->status,
                        'required_documents' => $application->required_documents,
                        'message'            => 'Application submitted successfully',
                    ],
                ],
                201
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to submit FI application',
                [
                    'error' => $e->getMessage(),
                    'data'  => $validated,
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to submit application',
                ],
                422
            );
        }
    }

    /**
     * Get application status.
     *
     * @OA\Get(
     *     path="/api/v2/financial-institutions/application/{applicationNumber}/status",
     *     operationId="fiGetApplicationStatus",
     *     summary="Get application status",
     *     description="Returns the current status, review stage, risk rating, and document verification status for a specific financial institution application.",
     *     tags={"BaaS Onboarding"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="applicationNumber",
     *         in="path",
     *         required=true,
     *         description="The application number",
     *         @OA\Schema(type="string", example="FI-2024-00001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="application_number", type="string", example="FI-2024-00001"),
     *                 @OA\Property(property="institution_name", type="string", example="Acme Finance Ltd"),
     *                 @OA\Property(property="status", type="string", example="pending_review"),
     *                 @OA\Property(property="review_stage", type="string", example="initial_review"),
     *                 @OA\Property(property="risk_rating", type="string", nullable=true, example="low"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time"),
     *                 @OA\Property(property="documents", type="object"),
     *                 @OA\Property(property="is_editable", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function getApplicationStatus(string $applicationNumber): JsonResponse
    {
        $application = FinancialInstitutionApplication::where('application_number', $applicationNumber)
            ->first();

        if (! $application) {
            return response()->json(
                [
                    'error' => 'Application not found',
                ],
                404
            );
        }

        $documentStatus = $this->documentService->getVerificationStatus($application);

        return response()->json(
            [
                'data' => [
                    'application_number' => $application->application_number,
                    'institution_name'   => $application->institution_name,
                    'status'             => $application->status,
                    'review_stage'       => $application->review_stage,
                    'risk_rating'        => $application->risk_rating,
                    'submitted_at'       => $application->created_at->toIso8601String(),
                    'documents'          => $documentStatus,
                    'is_editable'        => $application->isEditable(),
                ],
            ]
        );
    }

    /**
     * Upload document for application.
     *
     * @OA\Post(
     *     path="/api/v2/financial-institutions/application/{applicationNumber}/documents",
     *     operationId="fiUploadDocument",
     *     summary="Upload application document",
     *     description="Uploads a supporting document for a financial institution application. Accepts PDF, JPG, JPEG, and PNG files up to 10MB. Application must be in an editable status.",
     *     tags={"BaaS Onboarding"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="applicationNumber",
     *         in="path",
     *         required=true,
     *         description="The application number",
     *         @OA\Schema(type="string", example="FI-2024-00001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"document_type", "document"},
     *                 @OA\Property(property="document_type", type="string", example="certificate_of_incorporation"),
     *                 @OA\Property(property="document", type="string", format="binary", description="Document file (PDF, JPG, JPEG, PNG; max 10MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="document_type", type="string", example="certificate_of_incorporation"),
     *                 @OA\Property(property="uploaded", type="boolean", example=true),
     *                 @OA\Property(property="filename", type="string", example="certificate.pdf"),
     *                 @OA\Property(property="size", type="integer", example=204800),
     *                 @OA\Property(property="message", type="string", example="Document uploaded successfully")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Application not found"),
     *     @OA\Response(response=422, description="Application not editable or validation error")
     * )
     */
    public function uploadDocument(Request $request, string $applicationNumber): JsonResponse
    {
        $application = FinancialInstitutionApplication::where('application_number', $applicationNumber)
            ->first();

        if (! $application) {
            return response()->json(
                [
                    'error' => 'Application not found',
                ],
                404
            );
        }

        if (! $application->isEditable()) {
            return response()->json(
                [
                    'error' => 'Application is not editable in current status',
                ],
                422
            );
        }

        $validated = $request->validate(
            [
                'document_type' => 'required|string',
                'document'      => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
            ]
        );

        try {
            $document = $this->documentService->uploadDocument(
                $application,
                $validated['document_type'],
                $request->file('document')
            );

            return response()->json(
                [
                    'data' => [
                        'document_type' => $validated['document_type'],
                        'uploaded'      => true,
                        'filename'      => $document['original_name'],
                        'size'          => $document['size'],
                        'message'       => 'Document uploaded successfully',
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * Get partner API documentation.
     *
     * @OA\Get(
     *     path="/api/v2/financial-institutions/api-documentation",
     *     operationId="fiGetApiDocumentation",
     *     summary="Get partner API documentation",
     *     description="Returns the partner API documentation including base URL, authentication details, rate limits, available endpoints, webhook events, and error codes.",
     *     tags={"BaaS Onboarding"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="base_url", type="string", example="https://app.example.com/api/partner/v1"),
     *                 @OA\Property(property="authentication", type="object",
     *                     @OA\Property(property="type", type="string", example="Bearer Token"),
     *                     @OA\Property(property="header", type="string", example="Authorization: Bearer {api_client_id}:{api_client_secret}")
     *                 ),
     *                 @OA\Property(property="rate_limits", type="object",
     *                     @OA\Property(property="sandbox", type="object",
     *                         @OA\Property(property="per_minute", type="integer", example=60),
     *                         @OA\Property(property="per_day", type="integer", example=10000)
     *                     ),
     *                     @OA\Property(property="production", type="object",
     *                         @OA\Property(property="per_minute", type="integer", example=300),
     *                         @OA\Property(property="per_day", type="integer", example=100000)
     *                     )
     *                 ),
     *                 @OA\Property(property="endpoints", type="object"),
     *                 @OA\Property(property="webhook_events", type="array", @OA\Items(type="string", example="account.created")),
     *                 @OA\Property(property="error_codes", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getApiDocumentation(): JsonResponse
    {
        return response()->json(
            [
                'data' => [
                    'base_url'       => config('app.url') . '/api/partner/v1',
                    'authentication' => [
                        'type'   => 'Bearer Token',
                        'header' => 'Authorization: Bearer {api_client_id}:{api_client_secret}',
                    ],
                    'rate_limits' => [
                        'sandbox' => [
                            'per_minute' => 60,
                            'per_day'    => 10000,
                        ],
                        'production' => [
                            'per_minute' => 300,
                            'per_day'    => 100000,
                        ],
                    ],
                    'endpoints' => [
                        'accounts' => [
                            'list'   => 'GET /accounts',
                            'create' => 'POST /accounts',
                            'get'    => 'GET /accounts/{account_id}',
                            'update' => 'PUT /accounts/{account_id}',
                            'close'  => 'POST /accounts/{account_id}/close',
                        ],
                        'transactions' => [
                            'list'   => 'GET /transactions',
                            'get'    => 'GET /transactions/{transaction_id}',
                            'create' => 'POST /transactions',
                        ],
                        'webhooks' => [
                            'list'   => 'GET /webhooks',
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
                ],
            ]
        );
    }
}
