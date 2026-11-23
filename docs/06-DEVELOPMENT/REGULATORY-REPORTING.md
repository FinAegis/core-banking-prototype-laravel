# Regulatory Reporting Framework

## Overview

The regulatory reporting module helps operators generate and track report drafts. The drafts are modelled on common formats such as the Currency Transaction Report (CTR), the Suspicious Activity Report (SAR) and AML, OFAC, BSA and KYC reports. The module does not by itself make a deployment compliant. Filing obligations remain the responsibility of the operator of each deployment. The module uses data from the fraud detection system in its reports.

## Architecture

### Core Components

The services live in `app/Domain/Regulatory/Services/`.

1. `EnhancedRegulatoryReportingService` extends the base reporting service. It adds fraud detection data and supports several report types and jurisdictions.
2. `ThresholdMonitoringService` checks transactions against regulatory thresholds. It can start a report when a threshold is breached, and it supports aggregation rules. Since v7.18.0 it takes the customer's country into account.
3. `ReportGeneratorService` writes reports as JSON, XML, CSV, PDF or Excel. It stores a checksum to protect report integrity.
4. `RegulatoryFilingService` records filing attempts with retries and status tracking. Filing is simulated, and no report reaches a regulator. See [Filing Methods](#filing-methods).

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
The CTR draft covers transactions above $10,000. It adds fraud detection data. It is generated daily and models a 15-day filing deadline.

### 2. Suspicious Activity Report (SAR)
The SAR draft collects fraud cases, high-risk scores, patterns and anomalies. It models a 30-day filing deadline, with immediate filing for critical cases.

### 3. Anti-Money Laundering (AML)
The AML report is monthly. It contains the transaction monitoring results, customer risk ratings and the results of the sanctions country checks.

### 4. OFAC Screening Report
The OFAC report is daily. It has sections for screening matches, blocked transactions and false positives. The screening step is a placeholder that returns zero results, so the report is an empty template. No sanctions list feed is connected.

### 5. Bank Secrecy Act (BSA)
The BSA report is quarterly. It summarises CTR and SAR drafts, the customer identification program and the risk assessment results.

### 6. Know Your Customer (KYC)
The KYC report shows customer verification status, the risk rating distribution, politically exposed persons (PEP) and expiring or expired KYC checks.

## API Endpoints

All endpoints need `auth:sanctum` and the compliance staff role.

### Report Generation and Files
These routes are defined in `app/Domain/Banking/Routes/api.php`. They load only when the Banking module is enabled, and Banking is disabled on the production site.
```http
POST   /api/regulatory/reports/ctr                   # Generate CTR
POST   /api/regulatory/reports/sar-candidates        # Generate SAR candidates
POST   /api/regulatory/reports/compliance-summary    # Generate compliance summary
POST   /api/regulatory/reports/kyc                   # Generate KYC report
GET    /api/regulatory/reports                       # List report files
GET    /api/regulatory/reports/{filename}            # Get report
GET    /api/regulatory/reports/{filename}/download   # Download report
DELETE /api/regulatory/reports/{filename}            # Delete report
GET    /api/regulatory/metrics                       # Reporting metrics
```

### Report Workflow Routes
`routes/api/regulatory.php` also registers these routes. Their controller methods do not exist in `RegulatoryReportingController` yet, so they cannot work. Several of them also share a URI with the file routes above.
```http
GET  /api/regulatory/reports/{id}
POST /api/regulatory/reports/generate
POST /api/regulatory/reports/{id}/submit
GET  /api/regulatory/reports/{id}/status
GET  /api/regulatory/reports/{id}/download
POST /api/regulatory/reports/{id}/amend
GET  /api/regulatory/templates
GET  /api/regulatory/jurisdictions
GET  /api/regulatory/requirements
GET  /api/regulatory/deadlines
GET  /api/regulatory/filings
GET  /api/regulatory/filings/{id}
```

There are no HTTP endpoints for thresholds or for filing retries. Use the services or `regulatory:manage` instead.

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

The reporting services read data from the fraud detection system:

1. **Enhanced CTR reports** include fraud risk scores, flag high-risk transactions and add behavioural anomalies.
2. **SAR generation** includes fraud cases, collects suspicious patterns and orders the cases by risk.
3. **Threshold monitoring** checks transactions as they happen, uses the pattern detection and creates alerts.

## Filing Methods

Filing is simulated in this codebase. None of the three methods sends a report to a regulator. The service picks the method from the report type and jurisdiction. US CTR and SAR reports use the API method, the US OFAC report uses the portal method, and all other reports use email.

### 1. API Submission
The API method posts the report to the endpoint that `regulatory.api_endpoints` sets for the report's jurisdiction and type. No config file in the repository defines that key. The call therefore fails with "No API endpoint configured", and the filing is marked as failed.

### 2. Portal Submission
Portal submission is simulated. It logs the attempt and succeeds at random about 80% of the time with a `PORTAL-` reference. A later status check returns a random status of pending, accepted or rejected.

### 3. Email Submission
Email submission is simulated. It logs the attempt and returns an `EMAIL-` reference, but it sends no email.

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
// Filing is simulated. Without regulatory.api_endpoints this API filing fails.

// Check status later
$status = $filingService->checkFilingStatus($filing);
```

## Command Line Management

```bash
# Generate reports for a specific date
php artisan regulatory:manage generate-reports --date=2024-01-15

# Generate specific report type (CTR, SAR, AML, OFAC, BSA)
php artisan regulatory:manage generate-reports --type=CTR

# Limit to one jurisdiction (US, EU, UK)
php artisan regulatory:manage generate-reports --jurisdiction=US

# Check regulatory thresholds
php artisan regulatory:manage check-thresholds

# Process pending filings
php artisan regulatory:manage process-filings

# Check overdue reports
php artisan regulatory:manage check-overdue

# Dry run mode (no changes)
php artisan regulatory:manage generate-reports --dry-run
```

The scheduler also runs `compliance:generate-reports` every day for CTR (01:00) and KYC (02:00), every Monday for SAR candidates (03:00) and on the first day of each month for the compliance summary (04:00). That command accepts `--type` (ctr, sar, summary, kyc, all), `--date` and `--month`.

## Configuration

### Configuration File

The services read `config('regulatory.institution_id')`, `config('regulatory.credentials.{jurisdiction}')`, `config('regulatory.api_endpoints')` and `config('regulatory.email_submission')`. The repository has no `config/regulatory.php` and no `REGULATORY_*` environment variables yet. An operator who wants to file must create that file first. Until then, filing has no endpoints, credentials or institution ID. Portal and email filing stay simulated even with that file.

The example below shows the intended jurisdiction section of such a file:

```php
// config/regulatory.php (not included in the repository)
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
1. **Report generation.** Track the reports generated by type, the average generation time and the error rate.
2. **Filing performance.** Track the submission success rate, the average processing time and the rejection reasons.
3. **Threshold effectiveness.** Track how often thresholds trigger, the false positive rate and how many triggers produce a report.
4. **Compliance status.** Track overdue reports, upcoming deadlines and the filing backlog.

`GET /api/regulatory/metrics` returns the reporting metrics. A dashboard should show the compliance status, upcoming deadlines, recent filings, threshold triggers and a breakdown by jurisdiction.

## Best Practices

1. **Report generation.** Schedule the daily generation jobs, retry failures, keep audit trails and check that the data is complete.
2. **Threshold management.** Review thresholds and their false positive rates regularly. Adjust them when regulations change, and test them before activation.
3. **Filing.** Check every report before submission. Keep filing credentials secure, watch the submission status and handle rejections quickly.
4. **Data security.** Encrypt sensitive report data, protect API credentials, audit access logs and apply a data retention policy.

## Testing

```bash
# Run regulatory tests
./vendor/bin/pest --filter=Regulatory

# Test report generation
php artisan regulatory:manage generate-reports --dry-run

# Test threshold evaluation by customer country
./vendor/bin/pest tests/Feature/Regulatory/ThresholdCustomerCountryTest.php
```

## Compliance Considerations

1. **Data privacy.** EU reports fall under the GDPR. Define how personal data is handled and keep only the data you need.
2. **Audit.** Keep a complete audit trail, version the reports, log access and track changes.
3. **Retention.** Keep reports for at least five years. Archive them securely and define how they are retrieved and destroyed.
4. **Regulatory updates.** Watch for regulatory changes, update thresholds promptly, test new requirements and document the changes.
