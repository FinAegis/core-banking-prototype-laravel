# Operations Documentation

This directory contains operational documentation for running the FinAegis platform in production.

## Contents

### Current Operational Features
While formal operations documentation is being developed, the platform includes several operational features:

- **Health Monitoring**: Bank connector health monitoring with circuit breakers
- **Scheduled Tasks**: Automated balance synchronization, reconciliation, and compliance reporting
- **Queue Management**: Event-driven processing with multiple queues
- **Caching Strategy**: Redis-based caching for performance
- **Webhook System**: Real-time event notifications
- **Admin Dashboard**: Comprehensive monitoring via Filament

### Planned Documentation
- **DEPLOYMENT.md** - Deployment strategies and procedures
- **MONITORING.md** - System monitoring and alerting setup
- **BACKUP_RECOVERY.md** - Backup and disaster recovery procedures
- **PERFORMANCE_TUNING.md** - Performance optimization guidelines
- **SECURITY_OPERATIONS.md** - Security best practices and procedures

## Current Operational Status (January 2025)

### Monitoring & Health Checks
- ✅ **Bank Health Monitoring**: Real-time status tracking
  - Circuit breaker pattern for resilience
  - Automated health checks every 5 minutes
  - Alert system for failures
  
- ✅ **System Health Widget**: Admin dashboard monitoring
  - Service status indicators
  - Cache performance metrics
  - Queue status monitoring

### Scheduled Operations
```php
// Configured in app/Console/Kernel.php
$schedule->command('custodian:sync-balances')->everyFiveMinutes();
$schedule->command('custodian:reconcile')->daily();
$schedule->command('compliance:generate-ctr')->daily();
$schedule->command('compliance:generate-sar')->monthly();
$schedule->command('cgo:verify-payments')->everyTenMinutes();
```

### Queue Configuration
```bash
# Queue workers should be configured for these queues:
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks,cgo
```

### Performance Considerations
- **Caching**: 5-minute TTL for exchange rates, 1-hour for accounts
- **Database Indexes**: Optimized for high-frequency queries
- **Event Store**: Partitioned for performance at scale
- **API Rate Limiting**: Configured per endpoint

### Security Operations
- **2FA**: Mandatory for admin accounts
- **API Authentication**: Sanctum-based token auth
- **Webhook Verification**: Signature validation
- **Audit Logging**: Complete event trail
- **GDPR Compliance**: Data export and anonymization tools

### CGO Operations
- **Payment Verification**: Automated every 10 minutes
- **KYC Processing**: Manual review queue for high-risk profiles
- **Refund Processing**: Event-sourced with admin oversight
- **Investment Monitoring**: Real-time dashboard

## Purpose

These documents provide guidance on:
- Production deployment strategies
- System monitoring and health checks
- Backup and recovery procedures
- Performance tuning and optimization
- Security operations and incident response
- Scaling strategies
- Maintenance procedures
- Compliance operations