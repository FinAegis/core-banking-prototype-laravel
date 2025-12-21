# Deployment Guide

This guide covers deploying FinAegis in various environments, from local development to production Kubernetes clusters.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Configuration](#environment-configuration)
3. [Docker Deployment](#docker-deployment)
4. [Kubernetes Deployment](#kubernetes-deployment)
5. [Database Setup](#database-setup)
6. [Queue Workers](#queue-workers)
7. [Scaling Considerations](#scaling-considerations)
8. [Health Checks](#health-checks)
9. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 2 cores | 4+ cores |
| Memory | 4 GB | 8+ GB |
| Storage | 20 GB | 100+ GB SSD |
| PHP | 8.4+ | 8.4+ |
| MySQL | 8.0+ | 8.0+ |
| Redis | 6.0+ | 7.0+ |
| Node.js | 18+ | 20+ |

### Required Services

- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Cache/Queue**: Redis 6.0+
- **Search** (optional): Meilisearch
- **Object Storage** (optional): S3-compatible

---

## Environment Configuration

### Environment Variables

Create `.env` from the appropriate template:

```bash
# For production
cp .env.example .env

# For demo/development
cp .env.demo .env
```

### Critical Configuration

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
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
CACHE_DRIVER=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=.your-domain.com
```

### Environment Modes

| Mode | `APP_ENV_MODE` | Use Case |
|------|----------------|----------|
| Demo | `demo` | Demonstrations, learning |
| Sandbox | `sandbox` | Integration testing |
| Production | `production` | Real transactions |

---

## Docker Deployment

### Docker Compose (Development)

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - REDIS_HOST=redis
    volumes:
      - .:/var/www/html
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: finaegis
      MYSQL_USER: finaegis
      MYSQL_PASSWORD: password
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass password
    ports:
      - "6379:6379"

  horizon:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan horizon
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
      - app

volumes:
  mysql_data:
```

### Dockerfile

```dockerfile
# Dockerfile
FROM php:8.4-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    zip \
    unzip \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    pcntl \
    bcmath \
    intl \
    gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader
RUN npm ci && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port
EXPOSE 8000

# Start command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

### Build and Run

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

---

## Kubernetes Deployment

### Namespace and ConfigMap

```yaml
# k8s/namespace.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: finaegis

---
# k8s/configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: finaegis-config
  namespace: finaegis
data:
  APP_ENV: "production"
  APP_DEBUG: "false"
  LOG_CHANNEL: "stderr"
  QUEUE_CONNECTION: "redis"
  CACHE_DRIVER: "redis"
  SESSION_DRIVER: "redis"
```

### Secrets

```yaml
# k8s/secrets.yaml
apiVersion: v1
kind: Secret
metadata:
  name: finaegis-secrets
  namespace: finaegis
type: Opaque
stringData:
  APP_KEY: "base64:your-app-key-here"
  DB_PASSWORD: "your-db-password"
  REDIS_PASSWORD: "your-redis-password"
```

### Deployment

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: finaegis-app
  namespace: finaegis
spec:
  replicas: 3
  selector:
    matchLabels:
      app: finaegis
  template:
    metadata:
      labels:
        app: finaegis
    spec:
      containers:
        - name: app
          image: finaegis/core-banking:latest
          ports:
            - containerPort: 8000
          envFrom:
            - configMapRef:
                name: finaegis-config
            - secretRef:
                name: finaegis-secrets
          resources:
            requests:
              memory: "512Mi"
              cpu: "250m"
            limits:
              memory: "1Gi"
              cpu: "1000m"
          livenessProbe:
            httpGet:
              path: /health
              port: 8000
            initialDelaySeconds: 30
            periodSeconds: 10
          readinessProbe:
            httpGet:
              path: /health
              port: 8000
            initialDelaySeconds: 5
            periodSeconds: 5
```

### Service and Ingress

```yaml
# k8s/service.yaml
apiVersion: v1
kind: Service
metadata:
  name: finaegis-service
  namespace: finaegis
spec:
  selector:
    app: finaegis
  ports:
    - port: 80
      targetPort: 8000
  type: ClusterIP

---
# k8s/ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: finaegis-ingress
  namespace: finaegis
  annotations:
    kubernetes.io/ingress.class: nginx
    cert-manager.io/cluster-issuer: letsencrypt-prod
spec:
  tls:
    - hosts:
        - finaegis.example.com
      secretName: finaegis-tls
  rules:
    - host: finaegis.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: finaegis-service
                port:
                  number: 80
```

### Horizon Worker

```yaml
# k8s/horizon.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: finaegis-horizon
  namespace: finaegis
spec:
  replicas: 2
  selector:
    matchLabels:
      app: finaegis-horizon
  template:
    metadata:
      labels:
        app: finaegis-horizon
    spec:
      containers:
        - name: horizon
          image: finaegis/core-banking:latest
          command: ["php", "artisan", "horizon"]
          envFrom:
            - configMapRef:
                name: finaegis-config
            - secretRef:
                name: finaegis-secrets
          resources:
            requests:
              memory: "256Mi"
              cpu: "100m"
            limits:
              memory: "512Mi"
              cpu: "500m"
```

### Deploy to Kubernetes

```bash
# Create namespace and configs
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/secrets.yaml

# Deploy application
kubectl apply -f k8s/deployment.yaml
kubectl apply -f k8s/service.yaml
kubectl apply -f k8s/ingress.yaml
kubectl apply -f k8s/horizon.yaml

# Run migrations (one-time)
kubectl exec -it deployment/finaegis-app -n finaegis -- php artisan migrate --force

# Check status
kubectl get pods -n finaegis
kubectl get services -n finaegis
```

---

## Database Setup

### Initial Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE finaegis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create user
mysql -u root -p -e "CREATE USER 'finaegis'@'%' IDENTIFIED BY 'secure-password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON finaegis.* TO 'finaegis'@'%';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

### Run Migrations

```bash
# Run all migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --class=ProductionSeeder

# Seed GCU basket
php artisan db:seed --class=GCUBasketSeeder
```

### Backup Strategy

```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | gzip > backup_$DATE.sql.gz

# Upload to S3
aws s3 cp backup_$DATE.sql.gz s3://your-bucket/backups/
```

---

## Queue Workers

### Horizon Configuration

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
    ],
],
```

### Queue Priority

```bash
# Start Horizon (recommended)
php artisan horizon

# Or manual workers
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks
```

### Supervisor Configuration

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

---

## Scaling Considerations

### Horizontal Scaling

```yaml
# Horizontal Pod Autoscaler
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: finaegis-hpa
  namespace: finaegis
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: finaegis-app
  minReplicas: 3
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
```

### Database Read Replicas

```env
# Primary for writes
DB_HOST=primary.db.example.com

# Read replicas for queries
DB_HOST_READ=replica.db.example.com
```

### Redis Cluster

```env
REDIS_CLUSTER=redis
REDIS_HOST=redis-cluster.example.com
```

---

## Health Checks

### Endpoints

| Endpoint | Purpose |
|----------|---------|
| `/health` | Basic health check |
| `/health/db` | Database connectivity |
| `/health/redis` | Redis connectivity |
| `/health/queue` | Queue health |

### Implementation

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/health/db', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 503);
    }
});
```

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| 500 errors | Check `storage/logs/laravel.log` |
| Queue not processing | Restart Horizon, check Redis connection |
| Slow queries | Enable query logging, add indexes |
| Memory issues | Increase PHP memory limit |

### Debug Commands

```bash
# Clear all caches
php artisan optimize:clear

# Check queue status
php artisan horizon:status

# Tail logs
tail -f storage/logs/laravel.log

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo()"
```

---

## Related Documentation

- [SECURITY_AUDIT_CHECKLIST.md](SECURITY_AUDIT_CHECKLIST.md)
- [OPERATIONAL_RUNBOOK.md](OPERATIONAL_RUNBOOK.md)
- [CLAUDE.md](../../CLAUDE.md) - Development commands
