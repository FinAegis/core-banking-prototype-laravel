# Deployment Guide

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

This guide explains how to deploy FinAegis, from a local Docker setup to a Kubernetes cluster. It describes the files that ship in this repository: `docker-compose.yml`, `docker/Dockerfile`, the Helm chart in `helm/finaegis` and the environment templates. The release described here is v7.18.1.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Configuration](#environment-configuration)
3. [Docker Deployment](#docker-deployment)
4. [Kubernetes Deployment](#kubernetes-deployment)
5. [Database Setup](#database-setup)
6. [Queue Workers and Scheduler](#queue-workers-and-scheduler)
7. [Deploying a Release](#deploying-a-release)
8. [Scaling Considerations](#scaling-considerations)
9. [Health Checks](#health-checks)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 2 cores | 4+ cores |
| Memory | 4 GB | 8+ GB |
| Storage | 20 GB | 100+ GB SSD |
| PHP | 8.4 | 8.4 |
| Database | MySQL 8.0 or MariaDB | MariaDB 11.4 |
| Redis | 6.0 | 7.0 |
| Node.js | 20.19 | 22 |

CI runs the test suite on MySQL 8.0 and on MariaDB 11.4. The production templates use MariaDB (`DB_CONNECTION=mariadb`). Node.js is needed only to build the frontend assets, and Vite 8 requires Node.js 20.19 or newer.

### Services

- **Database.** MySQL 8.0 or MariaDB.
- **Cache, queues and sessions.** Redis.
- **Search (optional).** Laravel Scout uses Meilisearch by default (`SCOUT_DRIVER`).
- **Object storage (optional).** Any S3-compatible store.

---

## Environment Configuration

### Choosing a template

The repository has several `.env` templates. Pick the one that matches the deployment.

| Template | Use it for |
|----------|------------|
| `.env.production.example` | A general production deployment of FinAegis. |
| `.env.zelta.example` | The hosted Zelta mobile wallet. It switches off the standards and demo modules and lists every key that Zelta needs, including the card and wallet send settings. |
| `.env.demo` | A demo deployment. `APP_ENV=demo` turns on demo mode. |
| `.env.example` | Local development. |

```bash
# For a production deployment
cp .env.production.example .env

# For demo/development
cp .env.demo .env
```

### Critical configuration

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mariadb
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=finaegis
DB_USERNAME=finaegis_user
DB_PASSWORD=secure-password

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=redis-password
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Cache
CACHE_STORE=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=.your-domain.com
```

### Environments

`APP_ENV` decides how the platform behaves.

| `APP_ENV` | Use case |
|-----------|----------|
| `local` | Development on your own machine. |
| `demo` | Demonstrations and learning. Demo mode uses simulated deposits, banks, blockchains and exchange rates, which the `DEMO_*` keys in `.env.demo` control. |
| `staging` | Integration testing before production. |
| `production` | A live deployment. Production guards are on, for example `ops:verify-env` blocks a deploy on any FAIL. |

### Switches that change what a deployment offers

Several settings decide which features are live. Check them before every production deploy.

| Setting | What it does |
|---------|--------------|
| `MODULES_DISABLED` | Switches off whole domain modules. `.env.zelta.example` switches off Banking, Basket, Cgo, CrossChain, DeFi, FinancialInstitution, Governance, ISO20022, ISO8583, Interledger, Ledger, Lending, Microfinance, OpenBanking, PaymentRails, Stablecoin and Treasury. |
| `TRADING_ENABLED` | Turns the demo trading engine on or off, including order books and liquidity pools. It defaults to on only when `APP_ENV` is `local`, `testing` or `demo`. |
| `SHOW_PROMO_PAGES` | Shows the feature and promotional pages. Production templates set it to `false`. |
| `ADMIN_MODULES` | Limits which admin panel groups are visible. |
| `CARD_PROVIDERS_ENABLED` | Lists the card providers that may be offered. Empty means cards are off. With `lepo`, only the users in `CARD_PROVIDER_LEPO_PILOT_USERS` get cards while `CARD_PROVIDER_LEPO_PILOT_ONLY=true`. |
| `CARD_TOPUPS_ENABLED` | Turns on topping up a card from the wallet. It stays `false` until the card partner confirms how the money moves. |
| `HYPERSWITCH_ENABLED` | Turns on the experimental HyperSwitch rail for card deposits. It is off by default, and Stripe stays the deposit rail. |
| `WALLET_SEND_EVM_NETWORKS` | Lists the EVM networks for sponsored wallet sends. The default is `polygon,base,arbitrum`. |

The [runbooks](../operations/) explain the settings for the ramp, deposits, subscriptions, wallet sponsorship and RAILGUN.

---

## Docker Deployment

### Docker Compose for development

The repository's `docker-compose.yml` starts four services. `app` is built from `docker/Dockerfile` and listens on port 8000. `mysql` runs MySQL 8.0, `redis` runs Redis 7 and `mailpit` catches outgoing mail on ports 8025 and 1025. The app waits until MySQL and Redis report healthy. `docker-compose.websocket.yml` adds a Soketi WebSocket server.

```bash
# Build images
docker-compose build

# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate --seed

# View logs
docker-compose logs -f app
```

### The production image

`docker/Dockerfile` is a multi-stage build. It installs the PHP dependencies with Composer 2.8 and builds the frontend assets with Node.js 22. The final stage runs on `php:8.4-fpm-alpine`, exposes port 80 and starts nginx and PHP-FPM under supervisord. Its health check calls `/api/monitoring/alive`.

The same file has two more targets. The `horizon` target runs `php artisan horizon`, and the `scheduler` target runs the Laravel scheduler.

```bash
docker build -f docker/Dockerfile --target production -t finaegis/core-banking:latest .
docker build -f docker/Dockerfile --target horizon -t finaegis/core-banking-horizon:latest .
docker build -f docker/Dockerfile --target scheduler -t finaegis/core-banking-scheduler:latest .
```

---

## Kubernetes Deployment

The Helm chart in `helm/finaegis` deploys the platform to Kubernetes. It has separate deployments for the web pods, Horizon and the scheduler. It also has a config map, autoscalers for the web pods and for Horizon, a pod disruption budget, an ingress, network policies, a Prometheus `ServiceMonitor` and Istio resources. Secrets can come from a secret store through External Secrets. `values-production.yaml` turns this on and reads from a Vault store.

```bash
# Add Bitnami repo for dependencies
helm repo add bitnami https://charts.bitnami.com/bitnami

# Install with staging values
helm upgrade --install finaegis ./helm/finaegis \
  --values ./helm/finaegis/values-staging.yaml \
  --namespace finaegis-staging \
  --create-namespace

# Install with production values
helm upgrade --install finaegis ./helm/finaegis \
  --values ./helm/finaegis/values-production.yaml \
  --namespace finaegis

# Run migrations (one-time per release)
kubectl exec -it deployment/finaegis-web -n finaegis -- php artisan migrate --force

# Check status
kubectl get pods -n finaegis
```

The image comes from `image.repository` and `image.tag` in `values.yaml`, which default to `finaegis/core-banking:latest`. Pin a release tag in production. The liveness probe calls `/api/monitoring/alive`, and the readiness probe calls `/api/monitoring/ready`. Check the deployment names in your release with `kubectl get deployments -n finaegis`, because they depend on the Helm release name.

---

## Database Setup

### Initial setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE finaegis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create user
mysql -u root -p -e "CREATE USER 'finaegis'@'%' IDENTIFIED BY 'secure-password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON finaegis.* TO 'finaegis'@'%';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

### Run migrations

```bash
# Run all migrations
php artisan migrate --force

# Seed the base data
php artisan db:seed --force
```

`db:seed` runs `DatabaseSeeder`. It creates the roles, assets, stablecoins, the GCU demo basket, the settings and the rewards catalogue. It creates no users. For a demo deployment, `php artisan demo:populate` also creates the demo users.

On a deployment that uses separate tenant databases, also run `php artisan tenants:migrate` after `migrate`.

### Backups

In production the scheduler runs `backup:run --only-db` every day at 01:30 UTC and `backup:clean` at 02:30 UTC. `BACKUP_DISK` sets where the dumps go, and both production templates still ship with `BACKUP_DISK=local`. In production, `ops:verify-env` fails and blocks the deploy when the local disk is the only destination, because the dumps would never leave the server. Set `BACKUP_DISK=s3` with the bucket credentials and test a restore at least once.

A plain `mysqldump` also works as an extra copy:

```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | gzip > backup_$DATE.sql.gz

# Upload to S3
aws s3 cp backup_$DATE.sql.gz s3://your-bucket/backups/
```

---

## Queue Workers and Scheduler

### Horizon

Run the queue workers through Horizon. `config/horizon.php` defines four supervisors for production.

| Supervisor | Queues |
|------------|--------|
| `supervisor-1` | `default`, `webhooks`, `fraud-batch`, `exchange`, `proofs` |
| `event-sourcing-supervisor-1` | `events`, `ledger`, `transactions`, `transfers`, `liquidity_pools` |
| `broadcasts-supervisor` | `broadcasts` |
| `mobile-supervisor` | `mobile` |

```bash
# Start Horizon (recommended)
php artisan horizon

# Or run plain workers for the same queues
php artisan queue:work --queue=default,webhooks,fraud-batch,exchange,proofs,events,ledger,transactions,transfers,liquidity_pools,broadcasts,mobile
```

### Supervisor configuration on a plain server

```ini
# /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/horizon.log
stopwaitsecs=3600
```

### Scheduler

Many jobs run on the Laravel scheduler, which `routes/console.php` defines. Examples are the wallet send confirmation polls every minute, the card top-up pipeline every minute, the revenue outbox sweep every five minutes, the Solana sponsor balance check every hour and the reviewer account expiry sweep at 00:10 UTC. Run the scheduler every minute from cron, or use the `scheduler` image target.

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## Deploying a Release

### Preflight check

`php artisan ops:verify-env` checks the environment before a deploy. It covers peppers, bypass flags, webhook credentials, the Apple root certificate pin, the card provider keys and more. It exits with 1 when a check FAILs. FAIL results block only in production, unless you pass `--strict`. Add `--json` for machine-readable output.

The GitHub deploy workflow (`.github/workflows/deploy.yml`) runs `ops:verify-env` while the site is still in maintenance mode, before it runs the migrations.

### Steps for a release

These are the upgrade steps from the v7.18.0 release notes. v7.18.1 adds no migrations and no new settings, so the same steps apply. The [changelog](../../CHANGELOG.md) lists the steps for each release in its "Upgrade" section.

```bash
php artisan down
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan ops:verify-env
php artisan migrate --force
php artisan tenants:migrate          # only with separate tenant databases
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan horizon:terminate        # Horizon restarts the workers with the new code
# With plain queue:work workers under Supervisor (etc/supervisor.conf), run instead:
# php artisan queue:restart
php artisan up
```

v7.18.0 adds eight migrations, plus two tenant migrations on a multi-database tenancy setup. Cards stay off until `CARD_PROVIDERS_ENABLED=lepo` is set together with the Lepo keys, and top-ups stay off until `CARD_TOPUPS_ENABLED=true`. `.env.zelta.example` lists every new key.

---

## Scaling Considerations

### Horizontal scaling

The Helm chart includes horizontal pod autoscalers. The web pods scale on CPU and memory. Horizon also scales on queue depth through the external metric `redis_queue_jobs_pending`. `values-production.yaml` sets the replica counts and the limits.

### Database read replicas

Laravel can send reads to a replica. Configure the `read` and `write` hosts of the connection in `config/database.php`.

### Redis cluster

`REDIS_CLUSTER` selects the Redis cluster mode (default `redis`).

---

## Health Checks

### Endpoints

| Endpoint | Purpose |
|----------|---------|
| `/api/monitoring/alive` | Liveness. The Docker health check and the Helm liveness probe use it. |
| `/api/monitoring/ready` | Readiness. The Helm readiness probe uses it. |
| `/api/monitoring/health` | A detailed health report. |
| `/api/health` | A quick health check. |
| `/up` | Laravel's built-in health route. |

---

## Troubleshooting

### Common issues

| Issue | Solution |
|-------|----------|
| 500 errors | Check `storage/logs/laravel.log`. |
| Queue not processing | Run `php artisan horizon:status`, restart Horizon and check the Redis connection. |
| Deploy blocked by `ops:verify-env` | Read the FAIL lines, fix the named settings and run the command again. |
| Slow queries | Turn on query logging and add indexes. |
| Memory issues | Raise the PHP memory limit. |

### Debug commands

```bash
# Clear all caches
php artisan optimize:clear

# Check queue status
php artisan horizon:status

# Check the environment
php artisan ops:verify-env

# Tail logs
tail -f storage/logs/laravel.log

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo()"
```

---

## Related Documentation

- [SECURITY_AUDIT_CHECKLIST.md](SECURITY_AUDIT_CHECKLIST.md)
- [OPERATIONAL_RUNBOOK.md](OPERATIONAL_RUNBOOK.md)
- [Runbooks for the hosted Zelta deployment](../operations/)
- [Infrastructure guide](../06-DEVELOPMENT/INFRASTRUCTURE.md)
