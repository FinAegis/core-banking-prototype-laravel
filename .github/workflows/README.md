# GitHub Actions Workflows

This directory contains the CI/CD workflows for the Core Banking Prototype. The workflows follow professional DevOps best practices with comprehensive testing, security scanning, and deployment automation.

## 🎯 Workflow Overview

### Core Workflows

#### 1. **Continuous Integration** (`ci.yml`)
The main CI workflow that orchestrates all testing and validation:
- **Triggers**: Push/PR to main/develop branches
- **Jobs**:
  - Code Quality Checks (linting, static analysis)
  - Security Scanning
  - Test Suite (Unit, Feature, Integration)
  - Security Test Suite
  - Performance Testing
  - Frontend Asset Building
  - Integration Report Generation

#### 2. **Security Scanning** (`security-scanning.yml`)
Comprehensive security analysis:
- **Triggers**: Push/PR, daily schedule, manual
- **Scans**:
  - Secret Detection (Gitleaks)
  - Dependency Vulnerabilities (Composer, NPM)
  - SAST (CodeQL, Psalm, PHPStan, Semgrep)
  - Container Security (Trivy)

#### 3. **Test Coverage Analysis** (`test-coverage.yml`)
Tracks and reports code coverage:
- **Triggers**: Push/PR with PHP changes, weekly schedule
- **Features**:
  - Coverage percentage tracking
  - Codecov integration
  - PR comments with coverage reports
  - Coverage trend analysis

#### 4. **Performance Testing** (`performance-testing.yml`)
Ensures application performance:
- **Triggers**: Push/PR with code changes, daily schedule
- **Tests**:
  - Application benchmarks with Apache Bench
  - Load testing with k6
  - Performance regression checks
  - Memory usage analysis

#### 5. **Deploy to Production** (`deploy.yml`)
Automated deployment pipeline:
- **Triggers**: Push to main, version tags, manual
- **Stages**:
  - Pre-deployment validation
  - Build artifacts
  - Deploy to staging
  - Deploy to production
  - Post-deployment tasks

#### 6. **Database Operations** (`database-operations.yml`)
Database management tasks:
- **Triggers**: Manual only
- **Operations**:
  - Backup/Restore
  - Migrations
  - Rollback
  - Seeding
  - Database refresh

### Reusable Workflows

Located in `.github/workflows/reusable/`:

1. **setup-php.yml**: Standardized PHP environment setup
2. **setup-laravel.yml**: Laravel application preparation
3. **setup-services.yml**: External services configuration


## 🚀 Usage

### Running Tests Locally

```bash
# Run all tests
./vendor/bin/pest --parallel

# Run specific test suite
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Feature
./vendor/bin/pest tests/Security

# Run with coverage
./vendor/bin/pest --coverage --min=80
```

### Manual Workflow Triggers

#### Deploy to Production
```bash
gh workflow run deploy.yml \
  -f environment=production \
  -f skip-tests=false
```

#### Database Operations
```bash
# Create backup
gh workflow run database-operations.yml \
  -f operation=backup \
  -f environment=staging

# Run migrations
gh workflow run database-operations.yml \
  -f operation=migrate \
  -f environment=production \
  -f confirm-production=CONFIRM
```

#### Performance Testing
```bash
gh workflow run performance-testing.yml \
  -f test-duration=600 \
  -f concurrent-users=200 \
  -f benchmark-iterations=2000
```

## 🔧 Configuration

### Environment Variables

Key environment variables used across workflows:

```yaml
PHP_VERSION: '8.3'
NODE_VERSION: '20'
COMPOSER_PROCESS_TIMEOUT: 0
PERFORMANCE_THRESHOLD_API: 200  # ms
MEMORY_THRESHOLD: 128  # MB
```

### Secrets Required

- `CODECOV_TOKEN`: For coverage reporting
- `GITHUB_TOKEN`: Automatically provided
- Environment-specific deployment credentials

### Cache Strategy

All workflows implement intelligent caching:
- Composer dependencies
- NPM packages
- Built assets
- Test results

## 📊 Workflow Features

### Concurrency Control
Prevents multiple runs of the same workflow:
```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```

### Matrix Testing
Parallel execution for faster results:
```yaml
strategy:
  matrix:
    test-type: [unit, feature, integration]
    php: [8.2, 8.3]
```

### Conditional Execution
Smart job dependencies and conditions:
```yaml
if: |
  github.event_name == 'push' || 
  github.event.inputs.full-test == 'true'
```

### Artifact Management
Test results and reports are preserved:
```yaml
uses: actions/upload-artifact@v4
with:
  retention-days: 30
```

## 🛡️ Security Best Practices

1. **Minimal Permissions**: Each workflow requests only necessary permissions
2. **Secret Scanning**: Automatic detection of exposed credentials
3. **Dependency Auditing**: Regular vulnerability checks
4. **SAST Integration**: Multiple static analysis tools
5. **Production Guards**: Confirmation required for production operations

## 📈 Performance Optimization

1. **Parallel Jobs**: Test suites run concurrently
2. **Intelligent Caching**: Dependencies cached across runs
3. **Conditional Steps**: Skip unnecessary operations
4. **Optimized Builds**: Production builds exclude dev dependencies
5. **JIT Compilation**: PHP JIT enabled for performance tests

## 🔍 Monitoring & Reporting

### PR Comments
Automated comments provide:
- Test coverage reports
- Security scan summaries
- Performance impact analysis
- Build status updates

### Artifacts
Download test results, coverage reports, and logs from the Actions tab.

### Notifications
Failed production deployments create GitHub issues automatically.

## 🚨 Troubleshooting

### Common Issues

1. **Cache Problems**
   ```bash
   # Clear workflow caches
   gh cache delete --all
   ```

2. **Flaky Tests**
   - Check for race conditions
   - Ensure proper test isolation
   - Review service health checks

3. **Deployment Failures**
   - Verify environment secrets
   - Check service connectivity
   - Review deployment logs

### Debug Mode

Enable debug logging:
```bash
gh workflow run ci.yml -f debug_enabled=true
```

## 📝 Contributing

When adding new workflows:

1. Follow naming conventions (kebab-case)
2. Include comprehensive documentation
3. Implement proper error handling
4. Add concurrency controls
5. Use reusable workflows where possible
6. Include security scanning
7. Add performance benchmarks

## 📚 References

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Laravel CI/CD Best Practices](https://laravel.com/docs/deployment)
- [Security Hardening for Actions](https://docs.github.com/en/actions/security-guides)