# CLAUDE.md

## Essential Commands

```bash
# Code quality (run before commit)
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest --parallel --stop-on-failure

# Development
php artisan serve                    # Start server
npm run dev                          # Vite dev server
php artisan l5-swagger:generate      # API docs
```

## Architecture

- **47 domains** in `app/Domain/` (DDD bounded contexts)
- **Event Sourcing**: Spatie v7.7+ with domain-specific tables
- **CQRS**: Command/Query Bus in `app/Infrastructure/`
- **GraphQL**: Lighthouse PHP, 39 domain schemas
- **Multi-Tenancy**: Team-based isolation (`UsesTenantConnection` trait)
- **Event Streaming**: Redis Streams publisher/consumer with DLQ + backpressure
- **Post-Quantum Crypto**: ML-KEM-768, ML-DSA-65, hybrid encryption
- **Stack**: PHP 8.4 / Laravel 12 / MySQL 8 / Redis / Pest / PHPStan Level 8

## Code Conventions

```php
<?php
declare(strict_types=1);
namespace App\Domain\Exchange\Services;
```

- Import order: `App\Domain` → `App\Http` → `App\Models` → `Illuminate` → Third-party
- Commits: `feat:` / `fix:` / `test:` / `refactor:` + `Co-Authored-By: Claude <noreply@anthropic.com>`
- Tests: Always pass `['read', 'write', 'delete']` abilities to `Sanctum::actingAs()`

## CI/CD

| Issue | Fix |
|-------|-----|
| PHPStan type errors | Cast return types, add `@var` PHPDoc, null checks |
| Test scope 403s | Add abilities to `Sanctum::actingAs($user, ['read', 'write', 'delete'])` |
| Code style | `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` |
| PHPCS | `./vendor/bin/phpcbf --standard=PSR12 app/` |

```bash
gh pr checks <PR_NUMBER>              # Check PR status
gh run view <RUN_ID> --log-failed     # View failed logs
```

## Notes

- Always work in feature branches
- Ensure GitHub Actions pass before merging
- Never create docs files unless explicitly requested
- Prefer editing existing files over creating new ones
- Use Serena memories for deep architectural context when needed
