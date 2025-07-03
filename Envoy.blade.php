@servers(['demo' => env('DEMO_SERVER'), 'production' => env('PRODUCTION_SERVER')])

@setup
    $repository = 'git@github.com:YOzaz/finaegis.git';
    $releases_dir = '/srv/finaegis/releases';
    $app_dir = '/srv/finaegis';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir . '/' . $release;
@endsetup

@story('deploy', ['on' => 'demo'])
    clone_repository
    run_composer
    update_symlinks
    build_assets
    run_migrations
    optimize_application
    activate_release
    restart_queue_workers
    clean_old_releases
@endstory

@story('deploy-production', ['on' => 'production'])
    maintenance_mode_on
    backup_database
    clone_repository
    run_composer
    update_symlinks
    build_assets
    run_migrations
    optimize_application
    activate_release
    restart_queue_workers
    maintenance_mode_off
    clean_old_releases
@endstory

@task('clone_repository')
    echo "Cloning repository..."
    [ -d {{ $releases_dir }} ] || mkdir -p {{ $releases_dir }}
    cd {{ $releases_dir }}
    git clone --depth 1 --branch main {{ $repository }} {{ $release }}
    cd {{ $new_release_dir }}
    git reset --hard {{ $commit ?? 'HEAD' }}
@endtask

@task('run_composer')
    echo "Installing composer dependencies..."
    cd {{ $new_release_dir }}
    composer install --prefer-dist --no-scripts --no-dev --optimize-autoloader
    composer dump-autoload --optimize
@endtask

@task('update_symlinks')
    echo "Updating symlinks..."
    cd {{ $new_release_dir }}
    
    # Remove existing storage directory
    rm -rf storage
    
    # Create symlinks to shared directories
    ln -nfs {{ $app_dir }}/storage storage
    ln -nfs {{ $app_dir }}/storage/app/public public/storage
    
    # Copy environment file
    ln -nfs {{ $app_dir }}/.env .env
@endtask

@task('build_assets')
    echo "Building assets..."
    cd {{ $new_release_dir }}
    npm ci --prefer-offline --no-audit
    npm run build
    rm -rf node_modules
@endtask

@task('run_migrations')
    echo "Running migrations..."
    cd {{ $new_release_dir }}
    php artisan migrate --force
@endtask

@task('optimize_application')
    echo "Optimizing application..."
    cd {{ $new_release_dir }}
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan filament:cache-components
    php artisan optimize
@endtask

@task('activate_release')
    echo "Activating new release..."
    cd {{ $app_dir }}
    ln -nfs {{ $new_release_dir }} current_temp
    mv -Tf current_temp current
@endtask

@task('restart_queue_workers')
    echo "Restarting queue workers..."
    cd {{ $app_dir }}/current
    php artisan queue:restart
    
    # If using Supervisor
    sudo supervisorctl restart all
    
    # Clear opcache if available
    php artisan opcache:clear || true
@endtask

@task('clean_old_releases')
    echo "Cleaning old releases..."
    cd {{ $releases_dir }}
    ls -dt {{ $releases_dir }}/* | tail -n +6 | xargs rm -rf
@endtask

@task('maintenance_mode_on')
    echo "Enabling maintenance mode..."
    cd {{ $app_dir }}/current
    php artisan down --render="maintenance" --retry=60
@endtask

@task('maintenance_mode_off')
    echo "Disabling maintenance mode..."
    cd {{ $app_dir }}/current
    php artisan up
@endtask

@task('backup_database')
    echo "Backing up database..."
    cd {{ $app_dir }}/current
    php artisan backup:run --only-db
@endtask

@task('rollback')
    echo "Rolling back to previous release..."
    cd {{ $releases_dir }}
    ln -nfs $(find . -maxdepth 1 -name "20*" -type d | sort -r | sed -n 2p) {{ $app_dir }}/current
    cd {{ $app_dir }}/current
    php artisan queue:restart
    sudo supervisorctl restart all
@endtask

@task('health_check')
    echo "Running health check..."
    cd {{ $app_dir }}/current
    php artisan health:check
    curl -f http://localhost/health || exit 1
@endtask