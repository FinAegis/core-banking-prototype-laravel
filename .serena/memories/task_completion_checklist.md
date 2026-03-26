# Task Completion Checklist

Before committing any change, run these in order:

1. `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` (on changed files)
2. `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` (on changed files)
3. `./vendor/bin/pest` (relevant test files or --parallel for full suite)
4. `git add` specific files (never git add -A)
5. Commit with conventional format + Co-Authored-By

## After merging features
- Update version badges on feature pages if applicable
- Add feature card to resources/views/features/index.blade.php
- Update CLAUDE.md if new architectural patterns were introduced
- Cross-link between related feature pages

## Package changes
- Add PSR-4 to root composer.json autoload-dev
- Run composer dump-autoload
- Avoid parallel agents touching composer.json or bootstrap/app.php

## Content changes
- Verify all version numbers match (domain count, CLI version, platform version)
- Check SEO: unique meta tags, Schema.org markup, Open Graph tags
- Ensure bidirectional cross-links between feature pages
