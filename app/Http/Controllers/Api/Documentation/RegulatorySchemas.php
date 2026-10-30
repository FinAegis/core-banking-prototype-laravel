<?php

namespace App\Http\Controllers\Api\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegulatoryReport',
    required: ['id', 'report_type', 'period_start', 'period_end', 'status', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'report_type', type: 'string', enum: ['ctr', 'sar', 'currency_exposure', 'large_exposure', 'liquidity', 'capital_adequacy'], example: 'ctr'),
        new OA\Property(property: 'period_start', type: 'string', format: 'date', example: '2025-01-01'),
        new OA\Property(property: 'period_end', type: 'string', format: 'date', example: '2025-01-31'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'pending_review', 'approved', 'submitted', 'rejected'], example: 'submitted'),
        new OA\Property(property: 'submission_deadline', type: 'string', format: 'date', example: '2025-02-15'),
        new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'submitted_by', type: 'string', example: 'compliance@finaegis.org'),
        new OA\Property(property: 'regulator', type: 'string', example: 'National competent authority (example)'),
        new OA\Property(property: 'reference_number', type: 'string', example: 'CTR-2025-01-001'),
        new OA\Property(property: 'file_path', type: 'string', example: '/reports/regulatory/ctr_2025_01.pdf'),
        new OA\Property(property: 'metadata', type: 'object', description: 'Report-specific metadata'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'CurrencyTransactionReport',
    allOf: [new OA\Schema(ref: '#/components/schemas/RegulatoryReport')],
    properties: [
        new OA\Property(property: 'report_type', type: 'string', enum: ['ctr'], example: 'ctr'),
        new OA\Property(property: 'total_transactions', type: 'integer', example: 150),
        new OA\Property(
            property: 'total_amount',
            type: 'object',
            properties: [
                new OA\Property(property: 'EUR', type: 'integer', example: 15000000),
                new OA\Property(property: 'USD', type: 'integer', example: 10000000),
            ],
        ),
        new OA\Property(property: 'threshold_exceeded_count', type: 'integer', example: 5),
        new OA\Property(
            property: 'transactions',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'transaction_id', type: 'string'),
                    new OA\Property(property: 'account_uuid', type: 'string'),
                    new OA\Property(property: 'amount', type: 'integer'),
                    new OA\Property(property: 'currency', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['deposit', 'withdrawal', 'transfer']),
                    new OA\Property(property: 'date', type: 'string', format: 'date-time'),
                ],
            ),
        ),
    ],
)]
#[OA\Schema(
    schema: 'SuspiciousActivityReport',
    allOf: [new OA\Schema(ref: '#/components/schemas/RegulatoryReport')],
    properties: [
        new OA\Property(property: 'report_type', type: 'string', enum: ['sar'], example: 'sar'),
        new OA\Property(property: 'case_number', type: 'string', example: 'SAR-2025-001'),
        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high'),
        new OA\Property(
            property: 'suspicious_activities',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'activity_type', type: 'string', example: 'rapid_movement'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'detected_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'risk_score', type: 'integer', minimum: 0, maximum: 100),
                ],
            ),
        ),
        new OA\Property(property: 'involved_accounts', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'total_suspicious_amount', type: 'integer'),
        new OA\Property(property: 'investigation_notes', type: 'string'),
        new OA\Property(property: 'law_enforcement_notified', type: 'boolean', example: false),
    ],
)]
#[OA\Schema(
    schema: 'ComplianceMetrics',
    required: ['period', 'metrics'],
    properties: [
        new OA\Property(property: 'period', type: 'string', example: '2025-01'),
        new OA\Property(
            property: 'metrics',
            type: 'object',
            properties: [
                new OA\Property(property: 'kyc_completion_rate', type: 'number', example: 0.95),
                new OA\Property(property: 'aml_alerts_generated', type: 'integer', example: 45),
                new OA\Property(property: 'aml_alerts_resolved', type: 'integer', example: 42),
                new OA\Property(property: 'false_positive_rate', type: 'number', example: 0.15),
                new OA\Property(property: 'sar_filed', type: 'integer', example: 3),
                new OA\Property(property: 'ctr_filed', type: 'integer', example: 12),
                new OA\Property(property: 'sanctions_screened', type: 'integer', example: 1500),
                new OA\Property(property: 'sanctions_matches', type: 'integer', example: 2),
                new OA\Property(property: 'training_completion_rate', type: 'number', example: 0.98),
            ],
        ),
        new OA\Property(
            property: 'risk_distribution',
            type: 'object',
            properties: [
                new OA\Property(property: 'low', type: 'integer', example: 800),
                new OA\Property(property: 'medium', type: 'integer', example: 150),
                new OA\Property(property: 'high', type: 'integer', example: 45),
                new OA\Property(property: 'critical', type: 'integer', example: 5),
            ],
        ),
    ],
)]
#[OA\Schema(
    schema: 'RegulatoryCreateReportRequest',
    required: ['report_type', 'period_start', 'period_end'],
    properties: [
        new OA\Property(property: 'report_type', type: 'string', enum: ['ctr', 'sar', 'currency_exposure', 'large_exposure', 'liquidity', 'capital_adequacy']),
        new OA\Property(property: 'period_start', type: 'string', format: 'date', example: '2025-01-01'),
        new OA\Property(property: 'period_end', type: 'string', format: 'date', example: '2025-01-31'),
        new OA\Property(property: 'include_draft', type: 'boolean', description: 'Include draft transactions', example: false),
        new OA\Property(property: 'parameters', type: 'object', description: 'Report-specific parameters'),
    ],
)]
#[OA\Schema(
    schema: 'ReportSubmission',
    required: ['report_id', 'submission_type'],
    properties: [
        new OA\Property(property: 'report_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'submission_type', type: 'string', enum: ['electronic', 'manual', 'api'], example: 'electronic'),
        new OA\Property(property: 'regulator_system_id', type: 'string', example: 'BOL-REPORTING'),
        new OA\Property(property: 'submission_notes', type: 'string'),
        new OA\Property(property: 'attachments', type: 'array', items: new OA\Items(type: 'string')),
    ],
)]
#[OA\Schema(
    schema: 'TransactionMonitoringRule',
    required: ['id', 'rule_name', 'rule_type', 'status', 'threshold'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'rule_name', type: 'string', example: 'Large Cash Transaction'),
        new OA\Property(property: 'rule_type', type: 'string', enum: ['amount', 'velocity', 'pattern', 'behavioral'], example: 'amount'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'testing'], example: 'active'),
        new OA\Property(
            property: 'threshold',
            type: 'object',
            properties: [
                new OA\Property(property: 'amount', type: 'integer', example: 1000000),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'time_window', type: 'string', example: '24h'),
            ],
        ),
        new OA\Property(property: 'risk_score_impact', type: 'integer', minimum: 0, maximum: 100, example: 25),
        new OA\Property(property: 'auto_escalate', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'last_triggered', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'ComplianceCase',
    required: ['id', 'case_type', 'status', 'priority', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'case_number', type: 'string', example: 'CASE-2025-001'),
        new OA\Property(property: 'case_type', type: 'string', enum: ['aml', 'kyc', 'sanctions', 'fraud', 'other'], example: 'aml'),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'under_investigation', 'escalated', 'closed', 'reported'], example: 'under_investigation'),
        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high'),
        new OA\Property(property: 'subject_type', type: 'string', enum: ['user', 'account', 'transaction'], example: 'account'),
        new OA\Property(property: 'subject_id', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'risk_score', type: 'integer', minimum: 0, maximum: 100),
        new OA\Property(property: 'assigned_to', type: 'string', example: 'compliance_officer@finaegis.org'),
        new OA\Property(
            property: 'evidence',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'file_path', type: 'string'),
                ],
            ),
        ),
        new OA\Property(property: 'actions_taken', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'resolution', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'resolved_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'RegulatoryNotification',
    required: ['id', 'type', 'title', 'severity', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', enum: ['deadline', 'regulation_change', 'audit', 'inspection', 'violation'], example: 'deadline'),
        new OA\Property(property: 'title', type: 'string', example: 'CTR Submission Deadline Approaching'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'severity', type: 'string', enum: ['info', 'warning', 'urgent', 'critical'], example: 'warning'),
        new OA\Property(property: 'regulator', type: 'string', example: 'National competent authority (example)'),
        new OA\Property(property: 'deadline', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'action_required', type: 'string'),
        new OA\Property(property: 'acknowledged', type: 'boolean', example: false),
        new OA\Property(property: 'acknowledged_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class RegulatorySchemas
{
    // This class only contains OpenAPI schema definitions
}
