<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="RegulatoryReport",
 *     required={"id", "report_type", "period_start", "period_end", "status", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="report_type", type="string", enum={"ctr", "sar", "currency_exposure", "large_exposure", "liquidity", "capital_adequacy"}, example="ctr"),
 *     @OA\Property(property="period_start", type="string", format="date", example="2025-01-01"),
 *     @OA\Property(property="period_end", type="string", format="date", example="2025-01-31"),
 *     @OA\Property(property="status", type="string", enum={"draft", "pending_review", "approved", "submitted", "rejected"}, example="submitted"),
 *     @OA\Property(property="submission_deadline", type="string", format="date", example="2025-02-15"),
 *     @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="submitted_by", type="string", example="compliance@finaegis.com"),
 *     @OA\Property(property="regulator", type="string", example="Bank of Lithuania"),
 *     @OA\Property(property="reference_number", type="string", example="CTR-2025-01-001"),
 *     @OA\Property(property="file_path", type="string", example="/reports/regulatory/ctr_2025_01.pdf"),
 *     @OA\Property(property="metadata", type="object", description="Report-specific metadata"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="CurrencyTransactionReport",
 *     allOf={@OA\Schema(ref="#/components/schemas/RegulatoryReport")},
 *     @OA\Property(property="report_type", type="string", enum={"ctr"}, example="ctr"),
 *     @OA\Property(property="total_transactions", type="integer", example=150),
 *     @OA\Property(property="total_amount", type="object",
 *         @OA\Property(property="EUR", type="integer", example=15000000),
 *         @OA\Property(property="USD", type="integer", example=10000000)
 *     ),
 *     @OA\Property(property="threshold_exceeded_count", type="integer", example=5),
 *     @OA\Property(property="transactions", type="array", @OA\Items(
 *         @OA\Property(property="transaction_id", type="string"),
 *         @OA\Property(property="account_uuid", type="string"),
 *         @OA\Property(property="amount", type="integer"),
 *         @OA\Property(property="currency", type="string"),
 *         @OA\Property(property="type", type="string", enum={"deposit", "withdrawal", "transfer"}),
 *         @OA\Property(property="date", type="string", format="date-time")
 *     ))
 * )
 */

/**
 * @OA\Schema(
 *     schema="SuspiciousActivityReport",
 *     allOf={@OA\Schema(ref="#/components/schemas/RegulatoryReport")},
 *     @OA\Property(property="report_type", type="string", enum={"sar"}, example="sar"),
 *     @OA\Property(property="case_number", type="string", example="SAR-2025-001"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "critical"}, example="high"),
 *     @OA\Property(property="suspicious_activities", type="array", @OA\Items(
 *         @OA\Property(property="activity_type", type="string", example="rapid_movement"),
 *         @OA\Property(property="description", type="string"),
 *         @OA\Property(property="detected_at", type="string", format="date-time"),
 *         @OA\Property(property="risk_score", type="integer", minimum=0, maximum=100)
 *     )),
 *     @OA\Property(property="involved_accounts", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="total_suspicious_amount", type="integer"),
 *     @OA\Property(property="investigation_notes", type="string"),
 *     @OA\Property(property="law_enforcement_notified", type="boolean", example=false)
 * )
 */

/**
 * @OA\Schema(
 *     schema="ComplianceMetrics",
 *     required={"period", "metrics"},
 *     @OA\Property(property="period", type="string", example="2025-01"),
 *     @OA\Property(property="metrics", type="object",
 *         @OA\Property(property="kyc_completion_rate", type="number", example=0.95),
 *         @OA\Property(property="aml_alerts_generated", type="integer", example=45),
 *         @OA\Property(property="aml_alerts_resolved", type="integer", example=42),
 *         @OA\Property(property="false_positive_rate", type="number", example=0.15),
 *         @OA\Property(property="sar_filed", type="integer", example=3),
 *         @OA\Property(property="ctr_filed", type="integer", example=12),
 *         @OA\Property(property="sanctions_screened", type="integer", example=1500),
 *         @OA\Property(property="sanctions_matches", type="integer", example=2),
 *         @OA\Property(property="training_completion_rate", type="number", example=0.98)
 *     ),
 *     @OA\Property(property="risk_distribution", type="object",
 *         @OA\Property(property="low", type="integer", example=800),
 *         @OA\Property(property="medium", type="integer", example=150),
 *         @OA\Property(property="high", type="integer", example=45),
 *         @OA\Property(property="critical", type="integer", example=5)
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="CreateReportRequest",
 *     required={"report_type", "period_start", "period_end"},
 *     @OA\Property(property="report_type", type="string", enum={"ctr", "sar", "currency_exposure", "large_exposure", "liquidity", "capital_adequacy"}),
 *     @OA\Property(property="period_start", type="string", format="date", example="2025-01-01"),
 *     @OA\Property(property="period_end", type="string", format="date", example="2025-01-31"),
 *     @OA\Property(property="include_draft", type="boolean", example=false, description="Include draft transactions"),
 *     @OA\Property(property="parameters", type="object", description="Report-specific parameters")
 * )
 */

/**
 * @OA\Schema(
 *     schema="ReportSubmission",
 *     required={"report_id", "submission_type"},
 *     @OA\Property(property="report_id", type="string", format="uuid"),
 *     @OA\Property(property="submission_type", type="string", enum={"electronic", "manual", "api"}, example="electronic"),
 *     @OA\Property(property="regulator_system_id", type="string", example="BOL-REPORTING"),
 *     @OA\Property(property="submission_notes", type="string"),
 *     @OA\Property(property="attachments", type="array", @OA\Items(type="string"))
 * )
 */

/**
 * @OA\Schema(
 *     schema="TransactionMonitoringRule",
 *     required={"id", "rule_name", "rule_type", "status", "threshold"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="rule_name", type="string", example="Large Cash Transaction"),
 *     @OA\Property(property="rule_type", type="string", enum={"amount", "velocity", "pattern", "behavioral"}, example="amount"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "testing"}, example="active"),
 *     @OA\Property(property="threshold", type="object",
 *         @OA\Property(property="amount", type="integer", example=1000000),
 *         @OA\Property(property="currency", type="string", example="EUR"),
 *         @OA\Property(property="time_window", type="string", example="24h")
 *     ),
 *     @OA\Property(property="risk_score_impact", type="integer", minimum=0, maximum=100, example=25),
 *     @OA\Property(property="auto_escalate", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="last_triggered", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="ComplianceCase",
 *     required={"id", "case_type", "status", "priority", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="case_number", type="string", example="CASE-2025-001"),
 *     @OA\Property(property="case_type", type="string", enum={"aml", "kyc", "sanctions", "fraud", "other"}, example="aml"),
 *     @OA\Property(property="status", type="string", enum={"open", "under_investigation", "escalated", "closed", "reported"}, example="under_investigation"),
 *     @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "critical"}, example="high"),
 *     @OA\Property(property="subject_type", type="string", enum={"user", "account", "transaction"}, example="account"),
 *     @OA\Property(property="subject_id", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="risk_score", type="integer", minimum=0, maximum=100),
 *     @OA\Property(property="assigned_to", type="string", example="compliance_officer@finaegis.com"),
 *     @OA\Property(property="evidence", type="array", @OA\Items(
 *         @OA\Property(property="type", type="string"),
 *         @OA\Property(property="description", type="string"),
 *         @OA\Property(property="file_path", type="string")
 *     )),
 *     @OA\Property(property="actions_taken", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="resolution", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="resolved_at", type="string", format="date-time", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *     schema="RegulatoryNotification",
 *     required={"id", "type", "title", "severity", "created_at"},
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string", enum={"deadline", "regulation_change", "audit", "inspection", "violation"}, example="deadline"),
 *     @OA\Property(property="title", type="string", example="CTR Submission Deadline Approaching"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="severity", type="string", enum={"info", "warning", "urgent", "critical"}, example="warning"),
 *     @OA\Property(property="regulator", type="string", example="Bank of Lithuania"),
 *     @OA\Property(property="deadline", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="action_required", type="string"),
 *     @OA\Property(property="acknowledged", type="boolean", example=false),
 *     @OA\Property(property="acknowledged_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

class RegulatorySchemas
{
    // This class only contains OpenAPI schema definitions
}