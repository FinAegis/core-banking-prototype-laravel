# Security Policy

## Supported Versions

FinAegis is currently in prototype/demonstration phase. Security updates are provided for the latest release only.

| Version | Supported          |
| ------- | ------------------ |
| main    | :white_check_mark: |
| < main  | :x:                |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security issue, please report it responsibly.

### How to Report

**DO NOT** create a public GitHub issue for security vulnerabilities.

Instead, please report security issues through one of these channels:

1. **GitHub Security Advisories** (Preferred):
   - Go to the [Security tab](https://github.com/FinAegis/core-banking-prototype-laravel/security)
   - Click "Report a vulnerability"
   - Fill out the private security advisory form

2. **Email**:
   - Send details to the repository maintainers
   - Use subject line: `[SECURITY] Brief description`

### What to Include

Please provide:

1. **Description** - Clear explanation of the vulnerability
2. **Impact** - What could an attacker achieve?
3. **Steps to Reproduce** - Detailed reproduction steps
4. **Affected Components** - Which files, endpoints, or domains
5. **Suggested Fix** - If you have one (optional)
6. **Your Contact** - For follow-up questions

### Example Report

```
## Vulnerability: SQL Injection in Account Search

### Description
The account search endpoint is vulnerable to SQL injection through
the `search` query parameter.

### Impact
An attacker could extract sensitive data from the database or
potentially modify/delete records.

### Steps to Reproduce
1. Navigate to /api/accounts
2. Add parameter: ?search=' OR '1'='1
3. Observe that all accounts are returned

### Affected Component
- File: app/Http/Controllers/Api/AccountController.php
- Method: index()
- Line: 45

### Suggested Fix
Use parameterized queries or Eloquent's built-in escaping.
```

## Response Process

### Timeline

| Phase | Timeframe |
|-------|-----------|
| Acknowledgment | Within 48 hours |
| Initial Assessment | Within 1 week |
| Fix Development | Depends on severity |
| Public Disclosure | After fix is released |

### Severity Levels

| Level | Description | Response Time |
|-------|-------------|---------------|
| Critical | Remote code execution, data breach | 24-48 hours |
| High | Authentication bypass, privilege escalation | 1 week |
| Medium | Information disclosure, DoS | 2 weeks |
| Low | Minor issues, hardening | Next release |

### Process

1. **Receive** - We acknowledge your report
2. **Assess** - We evaluate severity and impact
3. **Fix** - We develop and test a patch
4. **Release** - We deploy the fix
5. **Disclose** - We publish a security advisory
6. **Credit** - We acknowledge your contribution (if desired)

## Security Best Practices

### For Contributors

When contributing code, please:

- **Input Validation**: Validate all user inputs
- **SQL Injection**: Use parameterized queries (Eloquent handles this)
- **XSS Prevention**: Escape output in Blade templates (`{{ }}` not `{!! !!}`)
- **CSRF Protection**: Include CSRF tokens in forms
- **Authentication**: Use Laravel's built-in auth mechanisms
- **Authorization**: Use policies for access control
- **Secrets**: Never commit credentials or API keys
- **Dependencies**: Keep packages updated

### For Deployment

If deploying FinAegis (even as a prototype):

- Use HTTPS only
- Set secure cookie flags
- Configure CORS appropriately
- Enable rate limiting
- Use environment variables for secrets
- Regularly update dependencies
- Monitor for suspicious activity

## Security Features

FinAegis includes these security features:

### Authentication
- OAuth2 / Laravel Passport
- API key authentication
- Session management
- Multi-factor authentication (configurable)

### Authorization
- Role-based access control (RBAC)
- Policy-based authorization
- API scope management

### Data Protection
- Encrypted sensitive fields
- Audit logging (event sourcing)
- PII handling compliance

### Financial Security
- Transaction signing
- Double-entry validation
- Fraud detection (configurable)
- KYC/AML compliance features

### Infrastructure
- Rate limiting
- IP blocking (configurable)
- CORS configuration
- Security headers

## Known Limitations

As a prototype, FinAegis has these security considerations:

1. **Not Production-Ready**: This is a demonstration platform
2. **Demo Mode**: Demo accounts have known credentials
3. **External Services**: Mock implementations in demo mode
4. **Audit**: No formal security audit has been conducted

**DO NOT** use FinAegis for real financial transactions without:
- Comprehensive security audit
- Regulatory compliance review
- Penetration testing
- Professional security hardening

## Acknowledgments

We appreciate responsible disclosure. Security researchers who report valid vulnerabilities will be:

- Credited in the security advisory (unless anonymity preferred)
- Listed in our security hall of fame
- Thanked in release notes

## Contact

For security matters:
- GitHub Security Advisories (preferred)
- Repository maintainers via GitHub

For general questions:
- [GitHub Discussions](https://github.com/FinAegis/core-banking-prototype-laravel/discussions)

---

Thank you for helping keep FinAegis secure!
