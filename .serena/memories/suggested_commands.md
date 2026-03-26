# Suggested Commands

## Code Quality (run before every commit)
```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest --parallel --stop-on-failure
```

## Development
```bash
php artisan serve                        # Start server
npm run dev                              # Vite dev server
php artisan l5-swagger:generate          # Regenerate Swagger docs
composer dump-autoload                   # After adding new packages/namespaces
```

## Testing
```bash
./vendor/bin/pest tests/Unit/Domain/X402/  # Run specific domain tests
./vendor/bin/pest --filter=WebSocket       # Filter by name
./vendor/bin/pest --parallel               # Full parallel run
```

## Git & CI
```bash
gh pr checks <PR_NUMBER>                 # Check CI status
gh run view <RUN_ID> --log-failed        # View failed CI logs
gh pr merge <PR_NUMBER> --squash --delete-branch  # Merge PR
```

## CLI
```bash
php packages/zelta-cli/zelta list        # Test CLI binary locally
php packages/zelta-cli/zelta auth:login --key zk_test_xxx
```

## Demo Data
```bash
php artisan sms:setup-demo               # Seed SMS + rewards demo data
php artisan demo:populate                 # Full demo data population
```
