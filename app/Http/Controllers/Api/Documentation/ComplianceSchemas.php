<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="KycDocument",
 *     required={"id", "user_uuid", "document_type", "status", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="user_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="document_type", type="string", enum={"passport", "national_id", "driving_license", "proof_of_address", "bank_statement"}, example="passport"),
 *     @OA\Property(property="document_number", type="string", example="AB123456", description="Document identification number"),
 *     @OA\Property(property="file_path", type="string", example="/documents/kyc/123e4567.pdf"),
 *     @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected", "expired"}, example="approved"),
 *     @OA\Property(property="verification_notes", type="string", example="Document verified successfully"),
 *     @OA\Property(property="expires_at", type="string", format="date", example="2030-12-31"),
 *     @OA\Property(property="verified_at", type="string", format="date-time", example="2025-01-15T10:00:00Z"),
 *     @OA\Property(property="verified_by", type="string", example="admin@finaegis.com"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T09:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T10:00:00Z")
 * )
 */

/**
 * @OA\Schema(
 *     schema="KycStatus",
 *     required={"user_uuid", "verification_level", "status", "documents", "next_review_date"},
 *     @OA\Property(property="user_uuid", type="string", format="uuid"),
 *     @OA\Property(property="verification_level", type="string", enum={"basic", "enhanced", "premium"}, example="enhanced", description="KYC verification tier"),
 *     @OA\Property(property="status", type="string", enum={"unverified", "pending", "verified", "rejected", "expired"}, example="verified"),
 *     @OA\Property(property="documents", type="array", @OA\Items(ref="#/components/schemas/KycDocument")),
 *     @OA\Property(property="limits", type="object",
 *         @OA\Property(property="daily_limit", type="integer", example=10000000, description="Daily transaction limit in cents"),
 *         @OA\Property(property="monthly_limit", type="integer", example=100000000, description="Monthly transaction limit in cents"),
 *         @OA\Property(property="single_transaction_limit", type="integer", example=5000000, description="Single transaction limit in cents")
 *     ),
 *     @OA\Property(property="risk_score", type="integer", minimum=0, maximum=100, example=25, description="Risk assessment score"),
 *     @OA\Property(property="next_review_date", type="string", format="date", example="2026-01-15"),
 *     @OA\Property(property="last_verified_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="UploadKycDocumentRequest",
 *     required={"document_type", "document_file"},
 *     @OA\Property(property="document_type", type="string", enum={"passport", "national_id", "driving_license", "proof_of_address", "bank_statement"}),
 *     @OA\Property(property="document_number", type="string", example="AB123456", description="Document identification number"),
 *     @OA\Property(property="document_file", type="string", format="binary", description="Document file upload"),
 *     @OA\Property(property="expires_at", type="string", format="date", example="2030-12-31", description="Document expiration date"),
 *     @OA\Property(property="metadata", type="object", description="Additional document metadata")
 * )
 */

/**
 * @OA\Schema(
 *     schema="VerifyKycDocumentRequest",
 *     required={"status"},
 *     @OA\Property(property="status", type="string", enum={"approved", "rejected"}, example="approved"),
 *     @OA\Property(property="verification_notes", type="string", example="Document verified against government database"),
 *     @OA\Property(property="risk_factors", type="array", @OA\Items(type="string"), example={"pep", "high_risk_country"})
 * )
 */

/**
 * @OA\Schema(
 *     schema="GdprDataRequest",
 *     required={"id", "user_uuid", "request_type", "status", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_uuid", type="string", format="uuid"),
 *     @OA\Property(property="request_type", type="string", enum={"export", "deletion", "rectification", "portability"}, example="export"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "rejected"}, example="completed"),
 *     @OA\Property(property="requested_data", type="array", @OA\Items(type="string"), example={"personal_info", "transactions", "documents"}),
 *     @OA\Property(property="completion_file", type="string", example="/gdpr/exports/user_data_123.zip", description="Path to completed export file"),
 *     @OA\Property(property="completed_at", type="string", format="date-time"),
 *     @OA\Property(property="notes", type="string", example="Data export completed successfully"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CreateGdprRequestRequest",
 *     required={"request_type"},
 *     @OA\Property(property="request_type", type="string", enum={"export", "deletion", "rectification", "portability"}),
 *     @OA\Property(property="requested_data", type="array", @OA\Items(type="string"), example={"personal_info", "transactions"}, description="Specific data categories requested"),
 *     @OA\Property(property="reason", type="string", example="Personal backup", description="Reason for the request"),
 *     @OA\Property(property="target_system", type="string", example="competitor_bank", description="For portability requests, where to send data")
 * )
 */

/**
 * @OA\Schema(
 *     schema="ConsentRecord",
 *     required={"id", "user_uuid", "consent_type", "status", "version", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_uuid", type="string", format="uuid"),
 *     @OA\Property(property="consent_type", type="string", enum={"marketing", "data_processing", "third_party_sharing", "cookies"}, example="marketing"),
 *     @OA\Property(property="status", type="string", enum={"granted", "revoked", "expired"}, example="granted"),
 *     @OA\Property(property="version", type="string", example="1.0", description="Version of consent terms"),
 *     @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
 *     @OA\Property(property="user_agent", type="string", example="Mozilla/5.0..."),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="revoked_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="UpdateConsentRequest",
 *     required={"consent_type", "status"},
 *     @OA\Property(property="consent_type", type="string", enum={"marketing", "data_processing", "third_party_sharing", "cookies"}),
 *     @OA\Property(property="status", type="string", enum={"granted", "revoked"}, example="granted"),
 *     @OA\Property(property="duration_days", type="integer", example=365, description="Consent duration in days")
 * )
 */

/**
 * @OA\Schema(
 *     schema="AmlAlert",
 *     required={"id", "user_uuid", "alert_type", "severity", "status", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="user_uuid", type="string", format="uuid"),
 *     @OA\Property(property="alert_type", type="string", enum={"high_value_transaction", "rapid_movement", "suspicious_pattern", "sanctions_match", "pep_match"}, example="high_value_transaction"),
 *     @OA\Property(property="severity", type="string", enum={"low", "medium", "high", "critical"}, example="high"),
 *     @OA\Property(property="status", type="string", enum={"new", "under_review", "escalated", "closed", "reported"}, example="under_review"),
 *     @OA\Property(property="transaction_ids", type="array", @OA\Items(type="string"), description="Related transaction IDs"),
 *     @OA\Property(property="amount", type="integer", example=10000000, description="Amount involved in cents"),
 *     @OA\Property(property="description", type="string", example="Multiple high-value transactions within 24 hours"),
 *     @OA\Property(property="investigator", type="string", example="compliance@finaegis.com"),
 *     @OA\Property(property="resolution", type="string", example="False positive - legitimate business activity"),
 *     @OA\Property(property="reported_to_authorities", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="resolved_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="SanctionsCheckResult",
 *     required={"checked_at", "status", "matches"},
 *     @OA\Property(property="checked_at", type="string", format="date-time"),
 *     @OA\Property(property="status", type="string", enum={"clear", "potential_match", "confirmed_match"}, example="clear"),
 *     @OA\Property(property="matches", type="array", @OA\Items(
 *         @OA\Property(property="list_name", type="string", example="OFAC SDN"),
 *         @OA\Property(property="match_score", type="number", example=0.95),
 *         @OA\Property(property="entity_name", type="string", example="John Doe"),
 *         @OA\Property(property="reason", type="string", example="Name and DOB match")
 *     )),
 *     @OA\Property(property="next_check_date", type="string", format="date")
 * )
 */

class ComplianceSchemas
{
    // This class only contains OpenAPI schema definitions
}