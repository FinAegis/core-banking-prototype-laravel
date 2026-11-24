# Security Audit Preparation Guide

## Overview

This guide helps an operator prepare a FinAegis deployment for a third-party security audit. It lists what the code provides, what the operator has to provide and which tools collect evidence.

FinAegis holds no security certification. The compliance modules and the `security:audit`, `pci:audit` and `compliance:*` commands are readiness tooling. Their output helps prepare an audit. It is not an audit result, and it does not show that a deployment meets SOC 2, PCI DSS or any other standard.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Pre-Audit Checklist

Items marked `[x]` are provided by the code. Items marked `[ ]` depend on the operator's infrastructure or processes.

### 1. Access Control & Authentication
- [x] **Two-factor authentication**: TOTP two-factor login for web accounts through Laravel Fortify.
- [x] **Role-based access control**: Roles and permissions through spatie/laravel-permission. The Filament admin panel needs an admin role.
- [x] **API tokens**: Laravel Sanctum tokens with `read`, `write` and `delete` abilities.
- [x] **OAuth 2.1**: Laravel Passport serves the public MCP server, with PKCE and dynamic client registration.
- [x] **Mobile sign-in**: Passkeys and biometric login for the mobile app. Device attestation is off (`MOBILE_ATTESTATION_ENABLED=false`) until the mobile challenge is wired through.
- [ ] **Session timeouts**: Set `SESSION_LIFETIME` to your policy.

### 2. Data Security
- [ ] **Encryption at rest**: Database and disk encryption are set up in the infrastructure, not in the code.
- [ ] **Encryption in transit**: TLS is terminated by the load balancer or web server. The `SecurityHeaders` middleware sends HSTS.
- [x] **Field encryption**: Sensitive model fields use Laravel's `encrypted` cast.
- [x] **Transaction hashes**: Transaction events carry a SHA3-512 hash, and `php artisan app:verify-transaction-hashes` checks the chain.
- [x] **Personal data controls**: GDPR data export and erasure endpoints (`GdprController`), consent records, a record of processing activities (`gdpr:register-export`) and breach-deadline checks (`gdpr:breach-check`).
- [ ] **Data retention**: `php artisan gdpr:retention-enforce` applies the retention rules, but it is not scheduled by default. Schedule it yourself.
- [ ] **Hardware security module**: Key storage in an HSM is the operator's choice. The code has an HSM interface for keys that need one.

### 3. Application Security
- [x] **Input validation**: Request validation on the API endpoints.
- [x] **SQL injection prevention**: Eloquent and the query builder with bound parameters.
- [x] **XSS protection**: Blade escapes output by default.
- [x] **CSRF protection**: Laravel CSRF tokens on web routes.
- [x] **Rate limiting**: `ApiRateLimitMiddleware` and Laravel throttles on the API.
- [x] **Security headers**: `SecurityHeaders` middleware sends CSP, HSTS and X-Frame-Options.
- [x] **Deploy preflight**: `php artisan ops:verify-env` blocks a production deploy with unsafe settings.

### 4. Infrastructure Security
- [ ] **Network segmentation**: Separate networks for database, cache and queue. The Helm chart has a `NetworkPolicy` template.
- [ ] **Firewall rules**: Restrictive ingress and egress rules.
- [ ] **DDoS protection**: A provider such as Cloudflare in front of the app.
- [ ] **Intrusion detection**: Log monitoring and alerting.
- [ ] **Penetration testing**: Commission an external test before go-live.
- [x] **Dependency and secret scanning**: CI runs `composer audit` and Gitleaks (`.github/workflows/02-security-scan.yml`).

### 5. Compliance & Auditing
- [x] **Event sourcing**: An append-only event store keeps the history of financial changes.
- [x] **Report drafts**: `compliance:generate-reports` drafts CTR, SAR, KYC and summary reports for an operator to review. Nothing is filed with an authority.
- [x] **Access review**: `compliance:access-review` reports privileged users, permissions and stale accounts.
- [x] **Evidence collection**: `compliance:collect-evidence` gathers access logs, a configuration snapshot and the change log.
- [x] **Incident records**: `compliance:incident` lists, creates and resolves security incidents.
- [x] **Change management**: Git history and pull request reviews.
- [x] **KYC and AML workflow tooling**: Identity checks run through third-party providers (integration adapters; no partnership or endorsement implied).

## Security Architecture

### 1. Defense in Depth

```
┌─────────────────────────────────────────────┐
│            External Layer                    │
│  - WAF (Web Application Firewall)           │
│  - DDoS Protection                          │
│  - Rate Limiting                            │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│           Application Layer                  │
│  - Input Validation                         │
│  - Authentication & Authorization           │
│  - Encryption                               │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│            Data Layer                        │
│  - Database Encryption                      │
│  - Access Controls                          │
│  - Audit Logging                            │
└─────────────────────────────────────────────┘
```

### 2. Zero Trust Security Model

The code samples in this section and in "Security Controls Implementation" show patterns an auditor may ask about. They are illustrations, not classes in the codebase. Point the auditor to the real middleware in `app/Http/Middleware/` and the services in `app/Domain/Security/` and `app/Domain/Compliance/`.

```php
// Every request is verified
class ZeroTrustMiddleware
{
    public function handle($request, Closure $next)
    {
        // Verify device
        if (!$this->verifyDevice($request)) {
            return response()->json(['error' => 'Unauthorized device'], 401);
        }
        
        // Verify location
        if (!$this->verifyLocation($request)) {
            return response()->json(['error' => 'Unauthorized location'], 401);
        }
        
        // Verify user behavior
        if (!$this->verifyBehavior($request)) {
            return response()->json(['error' => 'Suspicious activity'], 401);
        }
        
        return $next($request);
    }
}
```

## Security Controls Implementation

### 1. Authentication Security

```php
// Enhanced authentication with device fingerprinting
class EnhancedAuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:12',
            'device_fingerprint' => 'required|string',
            'otp' => 'required_if:has_2fa,true',
        ]);
        
        // Verify credentials
        if (!Auth::attempt($request->only('email', 'password'))) {
            $this->recordFailedAttempt($request);
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
        
        // Verify 2FA
        if ($user->has_2fa && !$this->verify2FA($user, $validated['otp'])) {
            return response()->json(['error' => 'Invalid OTP'], 401);
        }
        
        // Verify device
        if (!$this->verifyDevice($user, $validated['device_fingerprint'])) {
            $this->sendDeviceVerification($user);
            return response()->json(['error' => 'Device verification required'], 403);
        }
        
        // Create secure session
        $token = $user->createToken('auth-token', [
            'expires_at' => now()->addMinutes(30),
            'ip' => $request->ip(),
            'device' => $validated['device_fingerprint'],
        ]);
        
        return response()->json(['token' => $token->plainTextToken]);
    }
}
```

### 2. Transaction Security

```php
// Multi-signature transaction verification
class SecureTransactionService
{
    public function initiateTransfer(TransferRequest $request): Transaction
    {
        // Verify transaction limits
        $this->verifyLimits($request);
        
        // Check fraud indicators
        $fraudScore = $this->calculateFraudScore($request);
        if ($fraudScore > 0.7) {
            $this->flagForReview($request);
            throw new SuspiciousActivityException();
        }
        
        // Generate transaction hash
        $hash = $this->generateQuantumResistantHash($request);
        
        // Create pending transaction
        $transaction = Transaction::create([
            'status' => 'pending_approval',
            'hash' => $hash,
            'requires_approval' => $request->amount > 10000,
        ]);
        
        // Send approval notifications
        if ($transaction->requires_approval) {
            $this->sendApprovalRequest($transaction);
        }
        
        return $transaction;
    }
    
    private function generateQuantumResistantHash($data): string
    {
        $serialized = json_encode($data);
        return hash('sha3-512', $serialized . config('app.key'));
    }
}
```

### 3. API Security

```php
// API security middleware stack
Route::middleware([
    'throttle:api',
    'auth:sanctum',
    'verify.signature',
    'log.request',
    'detect.anomaly',
])->group(function () {
    Route::apiResource('accounts', AccountController::class);
});

// Request signature verification
class VerifySignature
{
    public function handle($request, Closure $next)
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        
        // Verify timestamp is recent
        if (abs(time() - $timestamp) > 300) {
            return response()->json(['error' => 'Request expired'], 401);
        }
        
        // Verify signature
        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp . $payload, $apiSecret);
        
        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        return $next($request);
    }
}
```

### 4. Data Protection

```php
// Field-level encryption for sensitive data
class EncryptedModel extends Model
{
    protected $encrypted = ['ssn', 'bank_account', 'tax_id'];
    
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encrypted) && !empty($value)) {
            $value = Crypt::encryptString($value);
        }
        
        return parent::setAttribute($key, $value);
    }
    
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        
        if (in_array($key, $this->encrypted) && !empty($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                // Log decryption failure
                Log::error('Decryption failed', ['key' => $key, 'model' => get_class($this)]);
            }
        }
        
        return $value;
    }
}
```

## Security Testing

### 1. Automated Security Tests

The security tests live in `tests/Security/`, in the folders `API`, `Authentication`, `Cryptography`, `MultiTenancy`, `Penetration` and `Vulnerabilities`. The workflow `.github/workflows/04-security-tests.yml` runs them in CI. The example below shows the kind of test they contain.

```php
// Example in the style of tests/Security/API/
class ApiSecurityTest extends TestCase
{
    public function test_sql_injection_prevention()
    {
        $maliciousInput = "'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/accounts', [
            'name' => $maliciousInput,
        ]);
        
        $response->assertStatus(422); // Validation failure
        $this->assertDatabaseHas('users', ['id' => 1]); // Table still exists
    }
    
    public function test_xss_prevention()
    {
        $xssPayload = '<script>alert("XSS")</script>';
        
        $account = Account::factory()->create(['name' => $xssPayload]);
        
        $response = $this->getJson("/api/accounts/{$account->uuid}");
        
        $response->assertJsonFragment([
            'name' => '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
        ]);
    }
    
    public function test_rate_limiting()
    {
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/accounts');
            
            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
}
```

### 2. Security Scanning Configuration

The CI pipeline (`.github/workflows/ci-pipeline.yml`) calls these workflows on every push and pull request to `main`:

| Workflow | What it runs |
|---|---|
| `02-security-scan.yml` | Gitleaks secret scanning and `composer audit` for production and all dependencies |
| `04-security-tests.yml` | Penetration, authentication, API security and vulnerability test suites from `tests/Security/` |
| `01-code-quality.yml` | PHPStan at level 8, PHPCS and php-cs-fixer |

Run the same checks locally before an audit:

```bash
composer audit
npm audit --omit=dev
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest tests/Security --configuration=phpunit.security.xml
php artisan security:audit --format=table
```

## Security Incident Response

### 1. Incident Response Plan

Incidents are recorded through `App\Domain\Compliance\Services\Certification\IncidentResponseService` and the `compliance:incident` command. The code below is a simplified illustration of a response plan, not that service.

```php
// Illustration
class IncidentResponseService
{
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_LOW = 'low';
    
    public function reportIncident(array $details): void
    {
        $incident = SecurityIncident::create([
            'type' => $details['type'],
            'severity' => $this->calculateSeverity($details),
            'details' => $details,
            'status' => 'open',
        ]);
        
        // Immediate actions based on severity
        match ($incident->severity) {
            self::SEVERITY_CRITICAL => $this->handleCritical($incident),
            self::SEVERITY_HIGH => $this->handleHigh($incident),
            default => $this->handleStandard($incident),
        };
    }
    
    private function handleCritical(SecurityIncident $incident): void
    {
        // Immediate lockdown
        Cache::put('system.lockdown', true, 3600);
        
        // Notify security team
        Notification::route('slack', config('security.slack_webhook'))
            ->notify(new CriticalSecurityIncident($incident));
        
        // Preserve evidence
        $this->preserveEvidence($incident);
        
        // Initiate automated response
        dispatch(new AutomatedIncidentResponse($incident));
    }
}
```

### 2. Security Monitoring

The fraud and anomaly checks live in `app/Domain/Fraud/`, and the scheduler runs `php artisan fraud:scan-anomalies` every hour by default (`FRAUD_BATCH_SCHEDULE`). The code below is an illustration.

```php
// Real-time security monitoring
class SecurityMonitor
{
    public function detectAnomalies(Request $request): void
    {
        $user = $request->user();
        
        // Check for unusual patterns
        $anomalies = [
            'unusual_location' => $this->checkLocation($user, $request->ip()),
            'unusual_time' => $this->checkAccessTime($user),
            'unusual_behavior' => $this->checkBehavior($user, $request),
            'multiple_failures' => $this->checkFailedAttempts($user),
        ];
        
        $score = $this->calculateAnomalyScore($anomalies);
        
        if ($score > 0.5) {
            event(new AnomalyDetected($user, $anomalies, $score));
        }
    }
}
```

## Security Documentation Requirements

### For Auditors
1. **Security Policy Documentation**
   - Information Security Policy
   - Access Control Policy
   - Incident Response Policy
   - Data Protection Policy

2. **Technical Documentation**
   - Network Diagrams
   - Data Flow Diagrams
   - API Documentation
   - Database Schema

3. **Compliance Documentation**
   - Regulatory Compliance Matrix
   - Audit Reports
   - Penetration Test Results
   - Vulnerability Assessments

### For Developers
1. **Secure Coding Guidelines**
   - OWASP Top 10 Prevention
   - Secure API Development
   - Cryptography Standards
   - Authentication Best Practices

2. **Security Testing Guide**
   - Unit Test Security Cases
   - Integration Security Tests
   - Penetration Testing Playbook
   - Security Regression Tests

## Security Metrics and KPIs

These are suggested targets. Set them to match your own service levels.

### Technical Metrics
- **Mean Time to Detect (MTTD)**: < 5 minutes
- **Mean Time to Respond (MTTR)**: < 30 minutes
- **Vulnerability Resolution Time**: < 24 hours for critical
- **Failed Login Attempts**: < 0.1% of total
- **API Error Rate**: < 0.01%

### Compliance Metrics
- **Audit Finding Resolution**: 100% within SLA
- **Security Training Completion**: 100% annually
- **Incident Reporting**: 100% within 1 hour
- **Access Review Completion**: 100% quarterly
- **Patch Management**: 100% within patch window

## Third-Party Audit Preparation

### 1. Documentation Package
- [ ] Executive Summary
- [ ] Architecture Overview
- [ ] Security Controls Matrix
- [ ] Risk Assessment
- [ ] Incident History
- [ ] Remediation Plans

### 2. Technical Access
- [ ] Read-only database access
- [ ] Application source code access
- [ ] Infrastructure configuration
- [ ] Log file access
- [ ] Monitoring dashboard access

### 3. Interview Preparation
- [ ] Security team briefing
- [ ] Developer security training
- [ ] Incident response drills
- [ ] Documentation review
- [ ] Mock audit exercises

## Post-Audit Actions

1. **Immediate Actions** (24 hours)
   - Address critical findings
   - Implement quick fixes
   - Update security patches

2. **Short-term Actions** (1 week)
   - Fix high-priority issues
   - Update documentation
   - Enhance monitoring

3. **Long-term Actions** (1 month)
   - Implement strategic improvements
   - Update security architecture
   - Enhance security training

## Security Contacts

Report vulnerabilities in FinAegis through a private GitHub security advisory, as described in [SECURITY.md](../../SECURITY.md). An incident in a running deployment goes to the contacts in that operator's own incident response plan.
