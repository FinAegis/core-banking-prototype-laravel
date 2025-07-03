# Server Setup Guide for Finaegis

This guide covers the complete server setup for deploying the Finaegis Core Banking platform.

## Prerequisites

- Ubuntu 22.04 LTS or newer
- Root or sudo access
- Domain name configured with DNS pointing to server
- SSL certificate (Let's Encrypt recommended)

## 1. Initial Server Setup

### Update System
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip
```

### Create Deploy User
```bash
sudo adduser deploy
sudo usermod -aG sudo deploy
sudo su - deploy
```

### Setup SSH Key Authentication
```bash
mkdir ~/.ssh
chmod 700 ~/.ssh
# Add your public key to:
nano ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

## 2. Install Required Software

### PHP 8.3
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
    php8.3-pgsql php8.3-sqlite3 php8.3-xml php8.3-mbstring php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-redis \
    php8.3-opcache php8.3-soap php8.3-imagick
```

### Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### Node.js & NPM
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### Nginx
```bash
sudo apt install -y nginx
```

### PostgreSQL
```bash
sudo apt install -y postgresql postgresql-contrib
```

### Redis
```bash
sudo apt install -y redis-server
```

### Supervisor (for queue workers)
```bash
sudo apt install -y supervisor
```

## 3. Configure PHP

Edit PHP-FPM configuration:
```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

Update these values:
```ini
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

## 4. Configure PostgreSQL

```bash
sudo -u postgres psql
```

Create database and user:
```sql
CREATE USER finaegis_user WITH ENCRYPTED PASSWORD 'your_secure_password';
CREATE DATABASE finaegis_production;
GRANT ALL PRIVILEGES ON DATABASE finaegis_production TO finaegis_user;
\q
```

## 5. Configure Redis

Edit Redis configuration:
```bash
sudo nano /etc/redis/redis.conf
```

Set password:
```conf
requirepass your_redis_password
```

Restart Redis:
```bash
sudo systemctl restart redis-server
```

## 6. Setup Application Directory

```bash
sudo mkdir -p /srv/finaegis/{releases,storage}
sudo chown -R deploy:deploy /srv/finaegis
```

Create storage structure:
```bash
cd /srv/finaegis/storage
mkdir -p app/{public,private} framework/{cache,sessions,views,testing} logs
chmod -R 775 .
```

## 7. Configure Nginx

Create site configuration:
```bash
sudo nano /etc/nginx/sites-available/finaegis
```

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /srv/finaegis/current/public;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/finaegis /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 8. Setup SSL with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

## 9. Configure Supervisor for Queue Workers

Create supervisor configuration:
```bash
sudo nano /etc/supervisor/conf.d/finaegis-workers.conf
```

```ini
[program:finaegis-default]
process_name=%(program_name)s_%(process_num)02d
command=php /srv/finaegis/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/srv/finaegis/storage/logs/worker.log
stopwaitsecs=3600

[program:finaegis-events]
process_name=%(program_name)s_%(process_num)02d
command=php /srv/finaegis/current/artisan queue:work --queue=events --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/srv/finaegis/storage/logs/events-worker.log
stopwaitsecs=3600

[program:finaegis-ledger]
process_name=%(program_name)s_%(process_num)02d
command=php /srv/finaegis/current/artisan queue:work --queue=ledger --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/srv/finaegis/storage/logs/ledger-worker.log
stopwaitsecs=3600

[program:finaegis-horizon]
process_name=%(program_name)s
command=php /srv/finaegis/current/artisan horizon
autostart=true
autorestart=true
user=deploy
redirect_stderr=true
stdout_logfile=/srv/finaegis/storage/logs/horizon.log
stopwaitsecs=3600
```

Update supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
```

## 10. Create Environment File

```bash
cd /srv/finaegis
nano .env
```

Example production .env:
```env
APP_NAME=Finaegis
APP_ENV=production
APP_KEY=base64:your_generated_key
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=finaegis_production
DB_USERNAME=finaegis_user
DB_PASSWORD=your_secure_password

BROADCAST_DRIVER=redis
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Add other service credentials as needed
```

## 11. Setup Cron Jobs

```bash
crontab -e
```

Add:
```cron
* * * * * cd /srv/finaegis/current && php artisan schedule:run >> /dev/null 2>&1
```

## 12. Firewall Configuration

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

## 13. Setup GitHub Actions Secrets

In your GitHub repository settings, add these secrets:

- `DEMO_SERVER`: demo server IP/hostname
- `DEMO_USER`: deploy
- `DEMO_PATH`: /srv/finaegis
- `DEMO_URL`: https://demo.your-domain.com
- `DEMO_SSH_PRIVATE_KEY`: Private SSH key for deploy user
- `DEMO_SSH_KNOWN_HOSTS`: Output of `ssh-keyscan demo.your-domain.com`

- `PRODUCTION_SERVER`: production server IP/hostname
- `PRODUCTION_USER`: deploy
- `PRODUCTION_PATH`: /srv/finaegis
- `PRODUCTION_URL`: https://your-domain.com
- `PRODUCTION_SSH_PRIVATE_KEY`: Private SSH key for deploy user
- `PRODUCTION_SSH_KNOWN_HOSTS`: Output of `ssh-keyscan your-domain.com`

## 14. First Deployment

1. SSH into server as deploy user
2. Clone repository manually for first time:
```bash
cd /srv/finaegis
git clone git@github.com:YOzaz/finaegis.git current
cd current
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

3. Set proper permissions:
```bash
chmod -R 755 /srv/finaegis/current
chmod -R 775 /srv/finaegis/storage
```

4. Test the deployment:
```bash
curl https://your-domain.com/health
```

## Monitoring & Maintenance

### Log Files
- Application logs: `/srv/finaegis/storage/logs/`
- Nginx logs: `/var/log/nginx/`
- PHP-FPM logs: `/var/log/php8.3-fpm.log`

### Useful Commands
```bash
# View queue workers status
sudo supervisorctl status

# Restart all workers
sudo supervisorctl restart all

# Clear application cache
cd /srv/finaegis/current
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Monitor logs
tail -f /srv/finaegis/storage/logs/laravel.log
```

### Backup Strategy
- Database: Daily automated backups using pg_dump
- Files: Weekly backup of storage directory
- Code: Version controlled in Git

### Security Updates
```bash
# Regular security updates
sudo apt update && sudo apt upgrade -y

# Check for composer vulnerabilities
cd /srv/finaegis/current
composer audit
```