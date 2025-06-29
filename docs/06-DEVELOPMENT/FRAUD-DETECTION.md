# Transaction Monitoring and Fraud Detection System

## Overview

The FinAegis platform includes a comprehensive, multi-layered fraud detection system that combines rule-based analysis, behavioral profiling, device fingerprinting, and machine learning to protect against fraudulent activities.

## Architecture

### Core Components

1. **Fraud Detection Service** (`FraudDetectionService`)
   - Orchestrates all fraud detection components
   - Analyzes transactions and users in real-time
   - Makes decisions (allow, challenge, review, block)

2. **Rule Engine** (`RuleEngineService`)
   - Evaluates configurable fraud rules
   - Supports multiple rule categories
   - Dynamic scoring based on conditions

3. **Behavioral Analysis** (`BehavioralAnalysisService`)
   - Builds user behavioral profiles
   - Detects anomalies in user patterns
   - Tracks transaction timing, amounts, locations

4. **Device Fingerprinting** (`DeviceFingerprintService`)
   - Tracks and analyzes devices
   - Detects device spoofing
   - Manages device trust networks

5. **Machine Learning** (`MachineLearningService`)
   - ML-based fraud prediction
   - Feature extraction and engineering
   - Model training with feedback loop

6. **Case Management** (`FraudCaseService`)
   - Creates and manages fraud investigation cases
   - Tracks evidence and investigation notes
   - Handles case resolution and outcomes

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
- trigger_count
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
- id (UUID)
- case_number (unique)
- fraud_score_id
- entity_id (polymorphic)
- entity_type
- status (open|investigating|closed)
- priority (low|medium|high|critical)
- risk_level
- assigned_to (investigator)
- loss_amount
- recovery_amount
- investigation_notes (JSON)
- evidence (JSON)
- resolution
- outcome
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
- blocked_at
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
GET  /api/fraud/cases/{caseId}
PUT  /api/fraud/cases/{caseId}
POST /api/fraud/cases/{caseId}/resolve
POST /api/fraud/cases/{caseId}/escalate
POST /api/fraud/cases/{caseId}/evidence
GET  /api/fraud/cases/{caseId}/timeline
GET  /api/fraud/cases/statistics
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

The system uses a weighted scoring approach:

```
Total Score = (Rule Score × 0.35) + 
              (Behavioral Score × 0.25) + 
              (Device Score × 0.20) + 
              (ML Score × 0.20)
```

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

The ML service extracts 40+ features including:

- Transaction features (amount, type, currency)
- Temporal features (time of day, day of week)
- Velocity features (transaction counts, volumes)
- User features (account age, KYC level)
- Behavioral features (deviation scores, profile confidence)
- Device features (risk score, trust level)
- Network features (IP risk, location)

## Behavioral Analysis

The system tracks:

1. **Transaction Patterns**
   - Typical amounts and deviations
   - Preferred transaction times
   - Common transaction types

2. **Location Patterns**
   - Common countries/cities
   - Travel patterns
   - Location consistency

3. **Device Patterns**
   - Trusted devices
   - Device switching behavior
   - Device count

4. **Velocity Patterns**
   - Normal transaction frequency
   - Volume patterns
   - Peak activity times

## Events

The system emits events for monitoring and integration:

- `FraudDetected` - When fraud is detected
- `TransactionBlocked` - When a transaction is blocked
- `ChallengeRequired` - When additional verification is needed
- `FraudCaseCreated` - When a new case is created
- `FraudCaseResolved` - When a case is resolved

## Configuration

### Environment Variables

```env
# ML Service Configuration
FRAUD_ML_ENABLED=true
FRAUD_ML_API_ENDPOINT=http://ml-service:8000
FRAUD_ML_MODEL_VERSION=1.0.0

# Rule Engine Configuration
FRAUD_RULES_CACHE_TTL=300
FRAUD_RULES_MAX_ACTIVE=100

# Behavioral Analysis
FRAUD_BEHAVIOR_MIN_TRANSACTIONS=10
FRAUD_BEHAVIOR_LOOKBACK_DAYS=90

# Device Fingerprinting
FRAUD_DEVICE_TRUST_THRESHOLD=70
FRAUD_DEVICE_MAX_USERS=5
```

### Creating Default Rules

```bash
php artisan fraud:create-default-rules
```

## Monitoring and Metrics

### Key Metrics

1. **Detection Metrics**
   - Total transactions analyzed
   - Fraud detection rate
   - False positive rate
   - Average processing time

2. **Rule Performance**
   - Rule trigger frequency
   - Rule effectiveness
   - False positive by rule

3. **Case Metrics**
   - Cases created/resolved
   - Average resolution time
   - Recovery rates

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

1. **Rule Management**
   - Regularly review rule performance
   - Adjust thresholds based on false positive rates
   - Test rules before activation

2. **Behavioral Profiles**
   - Allow sufficient history before strict enforcement
   - Consider seasonal patterns
   - Account for legitimate behavior changes

3. **Device Management**
   - Implement device registration flows
   - Allow users to manage trusted devices
   - Consider multi-device users

4. **Case Investigation**
   - Document all investigation steps
   - Collect evidence systematically
   - Use outcomes for ML training

5. **Performance**
   - Cache active rules
   - Use async processing for non-critical analysis
   - Monitor API response times

## Testing

```bash
# Run fraud detection tests
php artisan test --filter=FraudDetection

# Test specific rule
curl -X POST /api/fraud/rules/{ruleId}/test \
  -H "Authorization: Bearer {token}" \
  -d '{"context": {...}}'
```

## Security Considerations

1. **Data Protection**
   - Encrypt sensitive behavioral data
   - Limit PII in fraud scores
   - Implement audit logging

2. **Access Control**
   - Role-based access to cases
   - Separate read/write permissions
   - Investigator assignment rules

3. **API Security**
   - Rate limiting on analysis endpoints
   - Authentication for all endpoints
   - Input validation on rule creation