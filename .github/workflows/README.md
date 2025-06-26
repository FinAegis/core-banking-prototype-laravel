# GitHub Actions Workflows

This directory contains the CI/CD pipelines for the Core Banking Prototype. The workflows follow modern DevOps best practices with comprehensive testing, security scanning, and modular pipeline architecture.

## 🏗️ Pipeline Architecture

### Core Pipeline Structure

The CI/CD system is organized into modular, reusable pipelines:

#### 1. **Main CI Pipeline** (`ci-pipeline.yml`)
Orchestrates all testing and validation phases:
- **Triggers**: Push/PR to main/develop branches, manual dispatch
- **Phases**:
  - Phase 1: Code Quality & Security (Parallel)
  - Phase 2: Test Suite (After Code Quality)
  - Phase 3: Security Tests (After Security Scan)
  - Phase 4: Performance Tests (After Test Suite)
  - Final: Status Check (No automatic commenting)

#### 2. **Modular Pipeline Components**

**Code Quality Pipeline** (`01-code-quality.yml`):
- Code standards checking (PSR12, PHP-CS-Fixer)
- Static analysis (PHPStan)
- Dependency security audit

**Security Scan Pipeline** (`02-security-scan.yml`):
- Secret scanning (Gitleaks)
- Vulnerability analysis
- Security audit reports

**Test Suite Pipeline** (`03-test-suite.yml`):
- Unit tests (parallel execution)
- Feature tests (parallel execution)
- Integration tests
- Coverage reporting to Codecov

**Security Test Pipeline** (`04-security-tests.yml`):
- Penetration testing
- Authentication security tests
- API security validation

**Performance Pipeline** (`05-performance.yml`):
- Performance tests with JIT compilation
- Load testing with k6
- Performance analysis

**Build Pipeline** (`06-build.yml`):
- Frontend asset compilation
- Production build optimization
- Asset artifact management

### Legacy Workflows

The following workflows are maintained for specific purposes:
- `claude.yml` - Claude Code integration
- `performance.yml` - Scheduled performance monitoring
- `security.yml` - Scheduled security scans

## 🚀 Usage

### Running Tests Locally

```bash
# Run all tests in parallel
./vendor/bin/pest --parallel

# Run specific test suites
./vendor/bin/pest tests/Unit --parallel
./vendor/bin/pest tests/Feature --parallel
./vendor/bin/pest tests/Security --stop-on-failure

# Run with coverage
./vendor/bin/pest --coverage --min=80
```

### Manual Pipeline Triggers

#### Full CI Pipeline
```bash
gh workflow run ci-pipeline.yml -f debug_enabled=true
```

#### Individual Pipeline Components
```bash
# Run only security tests
gh workflow run 04-security-tests.yml

# Run performance tests
gh workflow run 05-performance.yml
```

## 🔧 Configuration

### Environment Variables

```yaml
# Global settings
PHP_VERSION: '8.3'
NODE_VERSION: '20'
COMPOSER_PROCESS_TIMEOUT: 0
COMPOSER_NO_INTERACTION: 1
COMPOSER_NO_AUDIT: 1
```

### Required Secrets

- `CODECOV_TOKEN`: Coverage reporting (optional)
- `GITLEAKS_LICENSE`: Secret scanning (optional)
- `GITHUB_TOKEN`: Automatically provided

### Pipeline Features

#### Parallel Execution
Each pipeline component runs jobs in parallel where possible:
```yaml
strategy:
  matrix:
    test-type: [unit, feature, integration]
```

#### Intelligent Caching
All pipelines implement comprehensive caching:
- Composer dependencies
- NPM packages
- Built assets
- Test databases

#### Conditional Execution
Pipelines include smart dependency management:
```yaml
needs: [code-quality]  # Only run after code quality passes
if: always()           # Run regardless of previous job status
```

## 🛡️ Security Features

### No Automatic Commenting
The new pipeline architecture removes automatic PR commenting to reduce noise while maintaining comprehensive testing.

### Security-First Design
1. **Minimal Permissions**: Each workflow requests only necessary permissions
2. **Secret Scanning**: Gitleaks integration for credential detection
3. **Dependency Auditing**: Composer and NPM vulnerability checks
4. **Isolated Environments**: Separate databases for different test types

## 📊 Pipeline Benefits

### Improved Performance
- Modular design allows for faster feedback
- Parallel execution reduces total runtime
- Smart caching minimizes redundant operations

### Enhanced Reliability
- Isolated pipeline components reduce failure propagation
- Better error isolation and debugging
- Consistent environments across all stages

### Better Maintainability
- Reusable pipeline components
- Clear separation of concerns
- Easier to update individual stages

## 🔍 Monitoring & Debugging

### Pipeline Status
Check pipeline status in the Actions tab:
- Individual pipeline results
- Detailed job logs
- Artifact downloads

### Debug Mode
Enable verbose logging:
```bash
gh workflow run ci-pipeline.yml -f debug_enabled=true
```

### Troubleshooting Common Issues

1. **Cache Issues**
   ```bash
   gh cache delete --all
   ```

2. **Test Failures**
   - Check individual pipeline logs
   - Review database setup
   - Validate service health checks

3. **Performance Issues**
   - Monitor JIT compilation logs
   - Check memory usage patterns
   - Review load test results

## 📈 Performance Optimization

### JIT Compilation
Performance tests use PHP JIT for maximum speed:
```yaml
ini-values: |
  opcache.enable=1
  opcache.enable_cli=1
  opcache.jit=tracing
  opcache.jit_buffer_size=256M
```

### Resource Management
- Optimized container resources
- Efficient database seeding
- Minimal dependency installation for specific jobs

## 📝 Contributing

When adding new pipeline components:

1. Follow the modular pipeline pattern
2. Use the `workflow_call` trigger for reusability
3. Implement proper error handling
4. Add comprehensive caching
5. Include security considerations
6. Document inputs and secrets

### Pipeline Naming Convention
- `01-code-quality.yml` - Phase 1 pipelines
- `02-security-scan.yml` - Phase 2 pipelines  
- `03-test-suite.yml` - Phase 3 pipelines
- `ci-pipeline.yml` - Main orchestrator

## 📚 References

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Reusable Workflows](https://docs.github.com/en/actions/using-workflows/reusing-workflows)
- [Laravel Testing Best Practices](https://laravel.com/docs/testing)
- [Security Hardening Guide](https://docs.github.com/en/actions/security-guides)