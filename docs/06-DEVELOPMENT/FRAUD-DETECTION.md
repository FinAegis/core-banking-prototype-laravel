# Transaction Monitoring and Fraud Detection System

## Overview

The Fraud domain (`app/Domain/Fraud/`) scores transactions and users for fraud risk. It combines configurable rules, behavioural profiles, device fingerprints and a machine learning score. The machine learning part is a rule-based simulation unless an external model is configured with `FRAUD_ML_ENABLED` and `FRAUD_ML_API_ENDPOINT`. Since v7.18.0, fraud cases are stored in the `fraud_cases` table.

## Architecture

### Core Components

The services live in `app/Domain/Fraud/Services/`.

1. `FraudDetectionService` runs all the other components. It analyses transactions and users and decides to allow, challenge, review or block.
2. `RuleEngineService` evaluates the configurable fraud rules in several categories and scores them by their conditions.
3. `BehavioralAnalysisService` builds behavioural profiles of users. It finds unusual timing, amounts and locations.
4. `DeviceFingerprintService` tracks devices, detects device spoofing and keeps device trust scores.
5. `MachineLearningService` extracts features and returns a fraud probability. Without an external model it uses a rule-based simulation.
6. `FraudCaseService` creates and manages investigation cases with their evidence, notes and outcomes.
7. `AnomalyDetectionOrchestrator` runs the statistical, behavioural, velocity and geographic anomaly detectors. The `fraud:scan-anomalies` command runs it in batches. It is off unless `FRAUD_ANOMALY_DETECTION_ENABLED=true`.

## Database Schema

### Fraud Rules Table
```sql
- id (UUID)
- code (unique identifier)
- name
- description
- category (velocity|pattern|amount|geography|device|behavior)
- severity (low|medium|high|critical)
- conditions (JSON)
- thresholds (JSON)
- actions (JSON array)
- base_score (0-100)
- is_active
- is_blocking
- triggers_count, true_positives, false_positives
- last_triggered_at
```

### Fraud Scores Table
```sql
- id (UUID)
- entity_id (polymorphic)
- entity_type (Transaction|User)
- score_type (real_time|batch|manual)
- total_score (0-100)
- risk_level (very_low|low|medium|high|very_high)
- decision (allow|challenge|review|block)
- outcome (fraud|legitimate|unknown)
- triggered_rules (JSON array)
- score_breakdown (JSON)
- behavioral_factors (JSON)
- device_factors (JSON)
- ml_score
- decision_at
```

### Fraud Cases Table
```sql
- id
- uuid (unique)
- team_id
- case_number (unique)
- type (transaction, account_takeover, money_mule and so on)
- status (pending|investigating|confirmed|false_positive|resolved)
- severity (low|medium|high|critical)
- risk_score
- fraud_score_id (the fraud score that opened the case, added in v7.18.0)
- subject_account_uuid
- amount and currency
- related_transactions and related_accounts (JSON)
- detection_rules (JSON)
- description
- evidence (JSON)
- assigned_to (investigator)
- actions_taken (JSON)
- resolution_notes
- detected_at
- resolved_at
```

### Device Fingerprints Table
```sql
- id (UUID)
- fingerprint_hash (unique)
- user_id
- device_type (desktop|mobile|tablet)
- trust_score (0-100)
- is_vpn/is_proxy/is_tor
- behavioral biometrics (typing patterns, mouse patterns)
- first_seen_at
- last_seen_at
- is_trusted, is_blocked and block_reason
```

### Behavioral Profiles Table
```sql
- id (UUID)
- user_id
- transaction statistics (avg, median, max amounts)
- typical transaction times/days
- common locations
- trusted devices
- profile established flag
- ML feature vector
```

## API Endpoints

### Fraud Detection

```http
POST /api/fraud/detection/analyze/transaction/{transactionId}
POST /api/fraud/detection/analyze/user/{userId}
GET  /api/fraud/detection/score/{fraudScoreId}
PUT  /api/fraud/detection/score/{fraudScoreId}/outcome
GET  /api/fraud/detection/statistics
GET  /api/fraud/detection/model/metrics
```

### Fraud Cases

```http
GET  /api/fraud/cases
GET  /api/fraud/cases/{id}
PUT  /api/fraud/cases/{id}
```

Resolving and escalating a case (`resolveCase()`, `escalateCase()`) and case statistics (`getCaseStatistics()`) are methods of `FraudCaseService` without HTTP routes. There is no evidence or timeline endpoint.

### Alerts, Patterns and Dashboard

```http
GET  /api/fraud/alerts
GET  /api/fraud/alerts/{id}
POST /api/fraud/alerts/{id}/acknowledge
POST /api/fraud/alerts/{id}/investigate
GET  /api/fraud/patterns
GET  /api/fraud/dashboard
GET  /api/fraud/statistics
```

### Fraud Rules

```http
GET  /api/fraud/rules
POST /api/fraud/rules
GET  /api/fraud/rules/{ruleId}
PUT  /api/fraud/rules/{ruleId}
DELETE /api/fraud/rules/{ruleId}
POST /api/fraud/rules/{ruleId}/toggle
POST /api/fraud/rules/{ruleId}/test
POST /api/fraud/rules/create-defaults
GET  /api/fraud/rules/export/all
POST /api/fraud/rules/import
GET  /api/fraud/rules/statistics
```

## Rule Categories

### 1. Velocity Rules
- Transaction count limits (daily, hourly)
- Transaction volume limits
- Time-based restrictions

### 2. Pattern Rules
- Rapid succession transactions
- Round amount patterns
- Transaction splitting
- Unusual sequences

### 3. Amount Rules
- Absolute amount limits
- Percentage of balance
- Multiple of average

### 4. Geography Rules
- High-risk countries
- Country mismatches
- Impossible travel detection

### 5. Device Rules
- VPN/Proxy/Tor detection
- New device flagging
- Device trust requirements

### 6. Behavior Rules
- Abnormal behavior detection
- Pattern changes
- Profile deviations

## Risk Scoring

The total score is a weighted sum of the component scores:

```
Total Score = (Rule Score × 0.35) + 
              (Behavioral Score × 0.25) + 
              (Device Score × 0.20) + 
              (ML Score × 0.20)
```

When machine learning is turned off, the ML part is left out and the rest of the score is scaled up to the full range.

Risk Levels:
- Very Low: 0-19
- Low: 20-39
- Medium: 40-59
- High: 60-79
- Very High: 80-100

## Decision Matrix

| Score Range | Decision | Action |
|------------|----------|--------|
| 0-39 | Allow | Transaction proceeds |
| 40-59 | Challenge | Additional verification required |
| 60-79 | Review | Manual review needed |
| 80-100 | Block | Transaction blocked |

## Integration Example

### Analyzing a Transaction

```php
// In your transaction processing code
$fraudScore = app(FraudDetectionService::class)->analyzeTransaction(
    $transaction,
    [
        'device_data' => $request->get('device_fingerprint'),
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]
);

if ($fraudScore->decision === FraudScore::DECISION_BLOCK) {
    throw new FraudDetectedException('Transaction blocked due to fraud risk');
}

if ($fraudScore->decision === FraudScore::DECISION_CHALLENGE) {
    return $this->requestAdditionalVerification($transaction);
}
```

### Creating Custom Rules

```php
$rule = FraudRule::create([
    'name' => 'High Value Night Transaction',
    'code' => 'FR-CUSTOM-001',
    'category' => FraudRule::CATEGORY_PATTERN,
    'severity' => FraudRule::SEVERITY_HIGH,
    'conditions' => [
        ['field' => 'hour', 'operator' => 'between', 'value' => [22, 6]],
        ['field' => 'amount', 'operator' => '>', 'value' => 10000],
    ],
    'actions' => [FraudRule::ACTION_FLAG, FraudRule::ACTION_NOTIFY],
    'base_score' => 70,
    'is_active' => true,
]);
```

## Machine Learning Features

The ML service extracts more than 40 features. They cover the transaction (amount, type, currency), timing (time of day, day of week), velocity (transaction counts and volumes) and the user (account age, KYC level). They also cover behaviour (deviation scores, profile confidence), the device (risk score, trust level) and the network (IP risk, location).

## Behavioral Analysis

The behavioural profile records four kinds of patterns:

1. **Transaction patterns.** Typical amounts and deviations, preferred times and common transaction types.
2. **Location patterns.** Common countries and cities, travel patterns and how consistent the location is.
3. **Device patterns.** Trusted devices, how often the user switches devices and the number of devices.
4. **Velocity patterns.** Normal transaction frequency, volume and peak activity times.

## Events

The domain emits these events from `app/Domain/Fraud/Events/`:

- `FraudDetected` fires when fraud is detected.
- `TransactionBlocked` fires when a transaction is blocked.
- `ChallengeRequired` fires when more verification is needed.
- `FraudCaseCreated`, `FraudCaseUpdated` and `FraudCaseResolved` follow the life of a case.
- `AnomalyDetected` fires when an anomaly detector finds something.

## Configuration

### Environment Variables

The settings live in `config/fraud.php`. These are the main variables:

```env
# ML Service Configuration (off by default)
FRAUD_ML_ENABLED=false
FRAUD_ML_API_ENDPOINT=http://ml-service:8000
FRAUD_ML_MODEL_VERSION=v1.0

# Anomaly detection (off by default)
FRAUD_ANOMALY_DETECTION_ENABLED=false
FRAUD_Z_SCORE_THRESHOLD=3.0
FRAUD_BATCH_CHUNK_SIZE=100
FRAUD_BATCH_LOOKBACK_HOURS=24

# Geography
FRAUD_HIGH_RISK_COUNTRIES=
FRAUD_MAX_TRAVEL_SPEED=900
```

See `config/fraud.php` for the full list, including the velocity, structuring, time and weight settings.

### Creating Default Rules

There is no artisan command for the default rules. Create them through the API:

```bash
curl -X POST /api/fraud/rules/create-defaults \
  -H "Authorization: Bearer {token}"
```

### Scanning for Anomalies

```bash
php artisan fraud:scan-anomalies
```

## Monitoring and Metrics

### Key Metrics

1. **Detection metrics.** Track the number of transactions analysed, the detection rate, the false positive rate and the average processing time.
2. **Rule performance.** Track how often each rule triggers, how effective it is and its false positives.
3. **Case metrics.** Track cases created and resolved, the average resolution time and the recovery rate.

### Dashboard Queries

```php
// Get fraud statistics
$stats = FraudScore::where('created_at', '>=', now()->subDays(7))
    ->selectRaw('
        COUNT(*) as total,
        AVG(total_score) as avg_score,
        SUM(CASE WHEN decision = "block" THEN 1 ELSE 0 END) as blocked,
        SUM(CASE WHEN outcome = "fraud" THEN 1 ELSE 0 END) as confirmed_fraud
    ')
    ->first();
```

## Best Practices

1. **Rule management.** Review rule performance regularly. Adjust thresholds when false positives are high. Test a rule before you activate it.
2. **Behavioural profiles.** Wait for enough history before strict enforcement. Allow for seasonal patterns and legitimate changes in behaviour.
3. **Device management.** Offer a device registration flow and let users manage their trusted devices. Remember that many users have several devices.
4. **Case investigation.** Document every step and collect evidence systematically. Feed the outcomes back into model training.
5. **Performance.** Cache the active rules, run non-critical analysis asynchronously and watch the API response times.

## Testing

```bash
# Run fraud detection tests
./vendor/bin/pest --filter=FraudDetection

# Test specific rule
curl -X POST /api/fraud/rules/{ruleId}/test \
  -H "Authorization: Bearer {token}" \
  -d '{"context": {...}}'
```

## Security Considerations

1. **Data protection.** Encrypt sensitive behavioural data, keep personal data out of fraud scores and log access for audit.
2. **Access control.** Give access to cases by role, separate read and write permissions and define rules for assigning investigators.
3. **API security.** Rate-limit the analysis endpoints, require authentication on every endpoint and validate input when rules are created.
