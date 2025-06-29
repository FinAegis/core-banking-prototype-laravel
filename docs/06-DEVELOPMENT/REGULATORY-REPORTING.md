# Regulatory Reporting Framework

## Overview

The FinAegis platform includes a comprehensive regulatory reporting framework that automates the generation, submission, and tracking of regulatory reports across multiple jurisdictions. The framework integrates with the fraud detection system to provide enhanced reporting capabilities.

## Architecture

### Core Components

1. **Enhanced Regulatory Reporting Service** (`EnhancedRegulatoryReportingService`)
   - Extends the base reporting service
   - Integrates fraud detection data
   - Supports multiple report types and jurisdictions

2. **Threshold Monitoring Service** (`ThresholdMonitoringService`)
   - Monitors transactions against regulatory thresholds
   - Triggers automatic reporting when thresholds are breached
   - Supports aggregation rules

3. **Report Generator Service** (`ReportGeneratorService`)
   - Generates reports in multiple formats (JSON, XML, CSV, PDF, Excel)
   - Handles format-specific requirements
   - Maintains report integrity with checksums

4. **Regulatory Filing Service** (`RegulatoryFilingService`)
   - Submits reports to regulatory authorities
   - Supports multiple filing methods (API, Portal, Email)
   - Handles retries and status tracking

## Database Schema

### Regulatory Reports Table
```sql
- id (UUID)
- report_id (unique identifier, e.g., CTR-2024-0001)
- report_type (CTR|SAR|OFAC|BSA|AML|KYC|FATCA|CRS|GDPR|PSD2|MIFID)
- jurisdiction (US|EU|UK|CA|AU|SG|HK)
- reporting_period_start/end
- status (draft|pending_review|submitted|accepted|rejected)
- priority (1-5)
- file details (path, format, size, hash)
- submission details (date, by, reference, response)
- review details (date, by, notes, corrections)
- compliance details (regulation reference, mandatory, due date)
- report metadata (data, record count, amount, entities, risk indicators)
```

### Regulatory Thresholds Table
```sql
- id (UUID)
- threshold_code (unique, e.g., THR-CTR-US-001)
- name and description
- category (transaction|customer|account|aggregate)
- report_type and jurisdiction
- conditions (JSON - complex threshold logic)
- thresholds (amount, count, time period)
- aggregation rules
- actions (report|flag|notify|block|review)
- status and validity dates
- performance metrics (triggers, false positives)
```

### Regulatory Filing Records Table
```sql
- id (UUID)
- regulatory_report_id (foreign key)
- filing_id (unique, e.g., FIL-202401-000001)
- filing details (type, method, status, attempt)
- submission details (date, by, credentials, request/response)
- acknowledgment details
- validation and retry information
- audit trail
```

## Report Types

### 1. Currency Transaction Report (CTR)
- Triggered by transactions exceeding $10,000
- Enhanced with fraud detection data
- Daily generation
- 15-day filing deadline

### 2. Suspicious Activity Report (SAR)
- Integrates fraud cases and high-risk scores
- Identifies patterns and anomalies
- 30-day filing deadline
- Immediate filing for critical cases

### 3. Anti-Money Laundering (AML)
- Monthly comprehensive report
- Includes transaction monitoring results
- Customer risk ratings
- Sanctions screening results

### 4. OFAC Screening Report
- Daily screening against sanctions lists
- Immediate action for matches
- Blocked transaction reporting
- False positive tracking

### 5. Bank Secrecy Act (BSA)
- Quarterly compliance report
- CTR/SAR filing summary
- Customer identification program
- Risk assessment results

### 6. Know Your Customer (KYC)
- Customer verification status
- Risk rating distribution
- PEP identification
- Expiring/expired KYC tracking

## API Endpoints

### Report Management
```http
GET  /api/regulatory/reports                    # List reports
GET  /api/regulatory/reports/{id}               # Get report details
GET  /api/regulatory/reports/{id}/download      # Download report
POST /api/regulatory/reports/generate/ctr       # Generate CTR
POST /api/regulatory/reports/generate/sar       # Generate SAR
POST /api/regulatory/reports/generate/aml       # Generate AML report
POST /api/regulatory/reports/generate/ofac      # Generate OFAC report
POST /api/regulatory/reports/generate/bsa       # Generate BSA report
```

### Filing Management
```http
POST /api/regulatory/filings/reports/{id}/submit    # Submit report
GET  /api/regulatory/filings/{id}/status           # Check filing status
POST /api/regulatory/filings/{id}/retry            # Retry failed filing
```

### Threshold Management
```http
GET  /api/regulatory/thresholds                    # List thresholds
PUT  /api/regulatory/thresholds/{id}              # Update threshold
```

### Dashboard
```http
GET  /api/regulatory/dashboard                     # Regulatory dashboard
```

## Threshold Configuration

### Transaction Thresholds
```json
{
  "threshold_code": "THR-CTR-US-TRA-001",
  "name": "Large Cash Transaction",
  "category": "transaction",
  "conditions": [
    {"field": "amount", "operator": ">=", "value": 1000000}
  ],
  "actions": ["report", "flag"],
  "auto_report": true
}
```

### Aggregate Thresholds
```json
{
  "threshold_code": "THR-SAR-US-AGG-001",
  "name": "Multiple Transactions Below CTR",
  "category": "aggregate",
  "aggregation_key": "customer",
  "time_period": "daily",
  "conditions": [
    {"field": "transaction_count", "operator": ">", "value": 3},
    {"field": "total_amount", "operator": ">", "value": 900000}
  ],
  "actions": ["report", "review"]
}
```

## Integration with Fraud Detection

The regulatory reporting framework automatically incorporates fraud detection data:

1. **Enhanced CTR Reports**
   - Include fraud risk scores
   - Flag high-risk transactions
   - Add behavioral anomalies

2. **SAR Generation**
   - Automatically include fraud cases
   - Compile suspicious patterns
   - Risk-based prioritization

3. **Threshold Monitoring**
   - Real-time transaction monitoring
   - Pattern detection integration
   - Automated alert generation

## Filing Methods

### 1. API Submission
- Direct integration with regulatory systems
- Real-time submission and acknowledgment
- Automatic status tracking

### 2. Portal Submission
- Automated portal interaction (where available)
- Queue-based processing
- Manual fallback option

### 3. Email Submission
- Secure email with encrypted attachments
- Audit trail maintenance
- Acknowledgment tracking

## Usage Examples

### Generate Enhanced CTR
```php
$date = Carbon::parse('2024-01-15');
$report = $reportingService->generateEnhancedCTR($date);

// Report includes:
// - Standard CTR data
// - Fraud risk analysis
// - Behavioral anomalies
// - Device risk factors
```

### Monitor Thresholds
```php
$transaction = Transaction::find($id);
$triggeredThresholds = $thresholdService->monitorTransaction($transaction);

foreach ($triggeredThresholds as $trigger) {
    $threshold = $trigger['threshold'];
    if ($threshold->shouldAutoReport()) {
        // Automatic report generation
    }
}
```

### Submit Report
```php
$report = RegulatoryReport::find($id);
$filing = $filingService->submitReport($report, [
    'filing_type' => 'initial',
    'filing_method' => 'api'
]);

// Check status later
$status = $filingService->checkFilingStatus($filing);
```

## Command Line Management

```bash
# Generate reports for a specific date
php artisan regulatory:manage generate-reports --date=2024-01-15

# Generate specific report type
php artisan regulatory:manage generate-reports --type=CTR

# Check regulatory thresholds
php artisan regulatory:manage check-thresholds

# Process pending filings
php artisan regulatory:manage process-filings

# Check overdue reports
php artisan regulatory:manage check-overdue

# Dry run mode (no changes)
php artisan regulatory:manage generate-reports --dry-run
```

## Configuration

### Environment Variables
```env
# Institution identification
REGULATORY_INSTITUTION_ID=FIN001

# API Endpoints
REGULATORY_API_US_CTR=https://api.fincen.gov/v1/ctr
REGULATORY_API_US_SAR=https://api.fincen.gov/v1/sar

# Credentials (encrypted in database)
REGULATORY_US_API_KEY=your-api-key
REGULATORY_US_INSTITUTION_ID=your-institution-id

# Email submission
REGULATORY_EMAIL_CTR_TO=ctr@fincen.gov
REGULATORY_EMAIL_SAR_TO=sar@fincen.gov

# Thresholds
REGULATORY_CTR_THRESHOLD=1000000
REGULATORY_SAR_MONITORING_DAYS=30
```

### Jurisdiction Configuration
```php
// config/regulatory.php
return [
    'jurisdictions' => [
        'US' => [
            'name' => 'United States',
            'reports' => ['CTR', 'SAR', 'OFAC', 'BSA'],
            'thresholds' => [
                'CTR' => 1000000, // $10,000 in cents
            ],
        ],
        'EU' => [
            'name' => 'European Union',
            'reports' => ['AML', 'GDPR', 'PSD2'],
        ],
    ],
];
```

## Monitoring and Alerts

### Key Metrics
1. **Report Generation**
   - Reports generated by type
   - Average generation time
   - Error rates

2. **Filing Performance**
   - Submission success rate
   - Average processing time
   - Rejection reasons

3. **Threshold Effectiveness**
   - Trigger frequency
   - False positive rate
   - Auto-report rate

4. **Compliance Status**
   - Overdue reports
   - Upcoming deadlines
   - Filing backlog

### Dashboard Features
- Real-time compliance status
- Upcoming report deadlines
- Recent filing activities
- Threshold trigger summary
- Jurisdiction breakdown

## Best Practices

1. **Report Generation**
   - Schedule daily generation jobs
   - Implement retry logic
   - Maintain audit trails
   - Validate data completeness

2. **Threshold Management**
   - Regular review of thresholds
   - Monitor false positive rates
   - Adjust based on regulatory updates
   - Test before activation

3. **Filing Process**
   - Verify report accuracy before submission
   - Maintain filing credentials securely
   - Monitor submission status
   - Handle rejections promptly

4. **Data Security**
   - Encrypt sensitive report data
   - Secure API credentials
   - Audit access logs
   - Implement data retention policies

## Testing

```bash
# Run regulatory tests
php artisan test --filter=Regulatory

# Test report generation
php artisan regulatory:manage generate-reports --dry-run

# Test threshold evaluation
php artisan test tests/Feature/Regulatory/ThresholdMonitoringTest.php
```

## Compliance Considerations

1. **Data Privacy**
   - GDPR compliance for EU reports
   - PII handling procedures
   - Data minimization principles

2. **Audit Requirements**
   - Complete audit trail
   - Report versioning
   - Access logging
   - Change tracking

3. **Retention Policies**
   - 5-year minimum retention
   - Secure archival
   - Retrieval procedures
   - Destruction protocols

4. **Regulatory Updates**
   - Monitor regulatory changes
   - Update thresholds promptly
   - Test new requirements
   - Document changes