# Operational Runbook

This runbook provides operational procedures for running FinAegis in production. It includes day-to-day operations, incident response, and standard operating procedures.

## Table of Contents

1. [Quick Reference](#quick-reference)
2. [Daily Operations](#daily-operations)
3. [Incident Response](#incident-response)
4. [Common Scenarios](#common-scenarios)
5. [Maintenance Procedures](#maintenance-procedures)
6. [Rollback Procedures](#rollback-procedures)
7. [Disaster Recovery](#disaster-recovery)
8. [Runbook Templates](#runbook-templates)

---

## Quick Reference

### Critical Commands

```bash
# Application Status
php artisan about                    # Application overview
php artisan horizon:status           # Queue worker status
php artisan config:show database     # Database configuration

# Health Checks
curl -s http://localhost/health      # Basic health
curl -s http://localhost/health/db   # Database health
curl -s http://localhost/health/redis # Redis health

# Emergency Commands
php artisan down --secret="emergency-bypass-token"  # Maintenance mode
php artisan up                                       # Exit maintenance
php artisan horizon:terminate                        # Stop all workers
php artisan queue:retry all                          # Retry failed jobs
```

### Key Metrics Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Response Time (p95) | > 500ms | > 2000ms |
| Error Rate | > 1% | > 5% |
| CPU Usage | > 70% | > 90% |
| Memory Usage | > 75% | > 90% |
| Queue Length | > 1000 | > 5000 |
| Failed Jobs | > 10/hour | > 50/hour |
| Database Connections | > 80% | > 95% |

### Escalation Contacts

| Level | Contact | Response Time |
|-------|---------|---------------|
| L1 | On-call Engineer | 15 minutes |
| L2 | Team Lead | 30 minutes |
| L3 | CTO | 1 hour |
| L4 | CEO | 2 hours |

---

## Daily Operations

### Morning Checklist

```markdown
[ ] Review overnight alerts and incidents
[ ] Check dashboard for anomalies
[ ] Verify queue processing is healthy
[ ] Review failed jobs and address critical ones
[ ] Check disk space across all servers
[ ] Verify backup completion
[ ] Review security alerts
```

### Daily Tasks

#### 1. System Health Check

```bash
# Check all services are running
kubectl get pods -n finaegis -o wide

# Check recent errors
kubectl logs deployment/finaegis-app -n finaegis --since=1h | grep -i error

# Check queue health
php artisan horizon:status

# Check database connections
php artisan tinker --execute="echo DB::connection()->getPdo() ? 'OK' : 'FAIL';"
```

#### 2. Financial Reconciliation

```bash
# Run daily reconciliation
php artisan reconcile:daily

# Check for discrepancies
php artisan reconcile:report --date=today

# Verify event sourcing integrity
php artisan event-sourcing:verify --domain=account
```

#### 3. Compliance Checks

```bash
# Process pending compliance alerts
php artisan compliance:process-alerts

# Generate daily compliance report
php artisan compliance:daily-report

# Check for blocked accounts
php artisan accounts:list-blocked --reason=compliance
```

#### 4. Performance Review

```bash
# Check slow queries (last 24 hours)
php artisan db:slow-queries --threshold=1000

# Review API response times
php artisan metrics:api-performance --period=24h

# Check cache hit rates
php artisan cache:stats
```

### Evening Checklist

```markdown
[ ] Verify all scheduled jobs completed
[ ] Review error rates for the day
[ ] Check pending transactions queue
[ ] Verify nightly backup job is scheduled
[ ] Document any notable events
[ ] Hand off to on-call if needed
```

---

## Incident Response

### Incident Severity Levels

| Severity | Definition | Response Time | Examples |
|----------|------------|---------------|----------|
| SEV-1 | Complete outage | 15 min | Platform down, data breach |
| SEV-2 | Major degradation | 30 min | Payment processing failed |
| SEV-3 | Partial degradation | 2 hours | Slow response times |
| SEV-4 | Minor issue | 4 hours | Non-critical bug |

### Incident Response Workflow

#### Step 1: Acknowledge

```bash
# Create incident ticket
# Notify on-call team
# Start incident channel

# Example PagerDuty acknowledgment
curl -X POST https://events.pagerduty.com/v2/enqueue \
  -H "Content-Type: application/json" \
  -d '{"routing_key": "SERVICE_KEY", "event_action": "acknowledge"}'
```

#### Step 2: Assess

```bash
# Check system status
php artisan health:check --all

# Check recent deployments
git log --oneline -10

# Check error spikes
php artisan logs:error-summary --last=1h

# Check infrastructure
kubectl get events -n finaegis --sort-by='.lastTimestamp'
```

#### Step 3: Mitigate

**For Application Issues:**
```bash
# Enable maintenance mode
php artisan down --secret="bypass-token-here"

# Rollback to previous version (if needed)
kubectl rollout undo deployment/finaegis-app -n finaegis

# Scale up resources (if needed)
kubectl scale deployment/finaegis-app -n finaegis --replicas=5
```

**For Database Issues:**
```bash
# Kill long-running queries
mysql -e "KILL QUERY <process_id>;"

# Clear database cache
php artisan cache:clear
php artisan config:clear

# Failover to replica (if needed)
# Update .env DB_HOST to replica
php artisan config:cache
```

**For Queue Issues:**
```bash
# Terminate stuck workers
php artisan horizon:terminate

# Clear failed jobs (after review)
php artisan queue:flush

# Restart horizon
php artisan horizon
```

#### Step 4: Resolve

```bash
# Exit maintenance mode
php artisan up

# Verify all systems operational
php artisan health:check --all

# Run smoke tests
./vendor/bin/pest tests/Feature/SmokeTest.php
```

#### Step 5: Post-Incident

```markdown
## Post-Incident Review Template

**Incident ID**: INC-YYYY-MM-DD-XXX
**Duration**: [start] to [end]
**Impact**: [describe user impact]

### Timeline
- HH:MM - [First detection]
- HH:MM - [Incident acknowledged]
- HH:MM - [Root cause identified]
- HH:MM - [Mitigation applied]
- HH:MM - [Incident resolved]

### Root Cause
[Describe the underlying cause]

### Action Items
- [ ] [Immediate fix]
- [ ] [Preventive measure]
- [ ] [Monitoring improvement]

### Lessons Learned
[What we learned from this incident]
```

---

## Common Scenarios

### Scenario 1: High Queue Backlog

**Symptoms:**
- Queue length > 5000
- Job processing delays
- User complaints about pending operations

**Resolution:**
```bash
# Check queue status
php artisan horizon:status
redis-cli LLEN queues:default

# Identify stuck jobs
php artisan queue:monitor

# Scale up workers
kubectl scale deployment/finaegis-horizon -n finaegis --replicas=5

# If specific queue is stuck, restart it
php artisan horizon:pause-queue default
php artisan horizon:continue-queue default

# Check for long-running jobs
php artisan horizon:failed --last=100
```

### Scenario 2: Database Connection Exhaustion

**Symptoms:**
- "Too many connections" errors
- Slow response times
- Connection timeout errors

**Resolution:**
```bash
# Check current connections
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -e "SHOW PROCESSLIST;"

# Kill idle connections
mysql -e "SELECT CONCAT('KILL ', id, ';') FROM information_schema.processlist WHERE command = 'Sleep' AND time > 300;" | mysql

# Adjust connection pool
# In .env:
# DB_POOL_MIN=10
# DB_POOL_MAX=100

# Restart PHP-FPM to reset connections
sudo systemctl restart php-fpm

# Long-term: optimize connection usage
php artisan db:optimize-connections
```

### Scenario 3: Memory Pressure

**Symptoms:**
- OOM (Out of Memory) kills
- Slow performance
- Worker crashes

**Resolution:**
```bash
# Check memory usage
free -h
docker stats

# Identify memory hogs
ps aux --sort=-%mem | head -20

# Clear caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# For Kubernetes
kubectl top pods -n finaegis

# Scale up memory (if needed)
kubectl set resources deployment/finaegis-app -n finaegis --limits=memory=2Gi
```

### Scenario 4: Payment Processing Failure

**Symptoms:**
- Payment jobs failing
- User payment errors
- Webhook processing delays

**Resolution:**
```bash
# Check payment provider status
curl -s https://status.stripe.com/api/v2/status.json

# Review failed payment jobs
php artisan queue:failed --queue=payments

# Retry specific failed payment
php artisan queue:retry <job-id>

# Check webhook processing
php artisan webhooks:status

# Manually resync payment status
php artisan payments:sync --since="1 hour ago"
```

### Scenario 5: Certificate Expiration

**Symptoms:**
- SSL/TLS errors
- HTTPS failures
- API connection errors

**Resolution:**
```bash
# Check certificate expiration
echo | openssl s_client -connect your-domain.com:443 2>/dev/null | openssl x509 -noout -dates

# Renew with certbot
sudo certbot renew

# For Kubernetes cert-manager
kubectl describe certificate finaegis-tls -n finaegis

# Force certificate renewal
kubectl delete secret finaegis-tls -n finaegis
# cert-manager will auto-create new one

# Restart ingress
kubectl rollout restart deployment/nginx-ingress-controller -n ingress-nginx
```

### Scenario 6: Event Sourcing Projection Lag

**Symptoms:**
- Read model out of sync
- Missing recent data
- Projection queue growing

**Resolution:**
```bash
# Check projection status
php artisan event-sourcing:status

# Check for projection errors
php artisan event-sourcing:errors

# Replay projections (if needed)
php artisan event-sourcing:replay "App\\Domain\\Account\\Projectors\\AccountBalanceProjector"

# Rebuild all projections (caution: resource intensive)
php artisan event-sourcing:replay --all

# Clear and rebuild specific projection
php artisan projections:rebuild account_balances --since="2024-01-01"
```

---

## Maintenance Procedures

### Scheduled Maintenance Window

**Pre-Maintenance:**
```bash
# 1. Notify users (24 hours before)
php artisan notifications:maintenance --scheduled="2024-01-15 02:00:00"

# 2. Create database snapshot
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | gzip > backup_pre_maintenance.sql.gz

# 3. Document current state
php artisan about > system_state_before.txt
php artisan horizon:status >> system_state_before.txt
```

**During Maintenance:**
```bash
# 1. Enable maintenance mode
php artisan down --secret="maintenance-bypass-token" --render="errors::503"

# 2. Stop queue workers
php artisan horizon:terminate

# 3. Perform maintenance tasks
# [Your maintenance commands here]

# 4. Run migrations (if any)
php artisan migrate --force

# 5. Clear caches
php artisan optimize:clear
php artisan optimize

# 6. Restart queue workers
php artisan horizon &
```

**Post-Maintenance:**
```bash
# 1. Verify system health
php artisan health:check --all

# 2. Run smoke tests
./vendor/bin/pest tests/Feature/SmokeTest.php

# 3. Exit maintenance mode
php artisan up

# 4. Monitor for 30 minutes
# Watch dashboards and logs

# 5. Send all-clear notification
php artisan notifications:maintenance-complete
```

### Database Maintenance

#### Index Optimization
```bash
# Analyze tables
mysql -e "ANALYZE TABLE accounts, transactions, ledger_entries, orders;"

# Optimize tables (during low traffic)
mysql -e "OPTIMIZE TABLE accounts, transactions, ledger_entries, orders;"

# Check for fragmentation
mysql -e "SELECT TABLE_NAME, DATA_FREE FROM information_schema.TABLES WHERE TABLE_SCHEMA='finaegis' AND DATA_FREE > 0;"
```

#### Event Store Maintenance
```bash
# Archive old events (older than 1 year)
php artisan event-sourcing:archive --before="1 year ago" --output=archive_2023.json

# Compact event store
php artisan event-sourcing:compact --aggregate=account

# Create snapshots for large aggregates
php artisan event-sourcing:snapshot --min-events=100
```

### Log Rotation

```bash
# Laravel logs
logrotate /etc/logrotate.d/laravel

# Custom log rotation configuration
# /etc/logrotate.d/finaegis
/var/www/html/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 640 www-data www-data
}
```

---

## Rollback Procedures

### Application Rollback

```bash
# 1. Identify current version
kubectl get deployment/finaegis-app -n finaegis -o jsonpath='{.spec.template.spec.containers[0].image}'

# 2. List rollout history
kubectl rollout history deployment/finaegis-app -n finaegis

# 3. Rollback to previous version
kubectl rollout undo deployment/finaegis-app -n finaegis

# 4. Rollback to specific revision
kubectl rollout undo deployment/finaegis-app -n finaegis --to-revision=3

# 5. Verify rollback
kubectl rollout status deployment/finaegis-app -n finaegis
```

### Database Migration Rollback

```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Rollback to specific batch
php artisan migrate:rollback --batch=15

# Full database restore (if needed)
gunzip < backup_pre_migration.sql.gz | mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME
```

### Event Sourcing Rollback

```bash
# Event sourcing is append-only, so we create compensating events

# 1. Identify problematic events
php artisan event-sourcing:list --aggregate-uuid=<uuid> --after="2024-01-15"

# 2. Create compensating events
php artisan event-sourcing:compensate --aggregate-uuid=<uuid> --from-event=<event-id>

# 3. Rebuild projections
php artisan event-sourcing:replay --from=<event-id>
```

---

## Disaster Recovery

### Recovery Time Objectives

| Scenario | RTO | RPO | Priority |
|----------|-----|-----|----------|
| Complete Outage | 4 hours | 1 hour | P1 |
| Database Failure | 2 hours | 15 minutes | P1 |
| Application Failure | 30 minutes | N/A | P2 |
| Region Failure | 8 hours | 1 hour | P1 |

### Full System Recovery

#### Step 1: Infrastructure

```bash
# Deploy infrastructure from IaC
terraform init
terraform apply -auto-approve

# Verify infrastructure
kubectl get nodes
kubectl get services -n finaegis
```

#### Step 2: Database Recovery

```bash
# Restore from most recent backup
aws s3 cp s3://finaegis-backups/latest/database.sql.gz .
gunzip database.sql.gz

# Import to new database
mysql -h $NEW_DB_HOST -u root -p < database.sql

# Verify data integrity
php artisan db:verify-integrity
```

#### Step 3: Application Deployment

```bash
# Deploy application
kubectl apply -f k8s/

# Wait for pods to be ready
kubectl wait --for=condition=ready pod -l app=finaegis -n finaegis --timeout=300s

# Run migrations
kubectl exec -it deployment/finaegis-app -n finaegis -- php artisan migrate --force
```

#### Step 4: Verification

```bash
# Health checks
curl -s https://finaegis.example.com/health

# Run integration tests
./vendor/bin/pest tests/Feature/DisasterRecoveryTest.php

# Verify financial integrity
php artisan reconcile:full --alert-on-discrepancy
```

### Backup Verification

```bash
# Monthly backup restoration test
# 1. Spin up test environment
docker-compose -f docker-compose.test.yml up -d

# 2. Restore backup
gunzip < monthly_backup.sql.gz | mysql -h localhost -P 3307 -u root -p

# 3. Run verification queries
php artisan backup:verify --connection=test

# 4. Tear down test environment
docker-compose -f docker-compose.test.yml down -v
```

---

## Runbook Templates

### Template: New Release Deployment

```markdown
## Release Deployment: v[VERSION]

**Date**: YYYY-MM-DD
**Lead**: [Engineer Name]
**Status**: [ ] Pending | [ ] In Progress | [ ] Complete | [ ] Rolled Back

### Pre-Deployment
- [ ] Changelog reviewed
- [ ] Migration scripts reviewed
- [ ] Rollback plan prepared
- [ ] Stakeholders notified
- [ ] Backup created

### Deployment Steps
- [ ] Enable maintenance mode
- [ ] Stop queue workers
- [ ] Deploy new version: `kubectl set image deployment/finaegis-app app=finaegis:v[VERSION]`
- [ ] Run migrations: `kubectl exec deployment/finaegis-app -- php artisan migrate --force`
- [ ] Clear caches
- [ ] Start queue workers
- [ ] Exit maintenance mode
- [ ] Run smoke tests

### Post-Deployment
- [ ] Monitor dashboards (15 min)
- [ ] Verify key metrics
- [ ] Update deployment log
- [ ] Notify stakeholders

### Rollback Trigger
If any of these occur, immediately rollback:
- [ ] Error rate > 5%
- [ ] P95 latency > 2s
- [ ] Critical features failing
- [ ] Payment processing failures
```

### Template: On-Call Handoff

```markdown
## On-Call Handoff

**From**: [Name] | **To**: [Name]
**Period**: YYYY-MM-DD HH:MM to YYYY-MM-DD HH:MM

### System Status
- [ ] All services healthy
- [ ] No active incidents
- [ ] Queue processing normal
- [ ] No pending deployments

### Outstanding Issues
| Issue | Severity | Status | Notes |
|-------|----------|--------|-------|
| | | | |

### Scheduled Tasks
| Task | Time | Impact |
|------|------|--------|
| | | |

### Recent Changes
- [List any recent deployments or changes]

### Known Issues to Watch
- [List any issues that need monitoring]

### Handoff Confirmed
- [ ] Outgoing engineer briefed incoming
- [ ] Access verified (VPN, kubectl, AWS)
- [ ] Communication channels verified
```

---

## Related Documentation

- [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) - Initial deployment
- [SECURITY_AUDIT_CHECKLIST.md](SECURITY_AUDIT_CHECKLIST.md) - Security review
- [PRODUCTION_READINESS_CHECKLIST.md](PRODUCTION_READINESS_CHECKLIST.md) - Launch checklist
- [MONITORING/monitoring.md](MONITORING/monitoring.md) - Monitoring setup
- [CLAUDE.md](../../CLAUDE.md) - Development commands
