# Code Style and Conventions

## PHP
- `declare(strict_types=1)` in every file
- Import order: App\Domain -> App\Http -> App\Models -> Illuminate -> Third-party
- PHPStan Level 8 — no ignores, fix the root cause
- Cast json_encode to (string) — returns string|false
- Cast env() to (string) in config files before explode() etc.
- Use assert($x instanceof Model) after ->first() for PHPStan narrowing

## Eloquent
- Set explicit $table when class name doesn't snake_case match (WebSocketSubscription -> websocket_subscriptions)
- Users table: $table->id() (BIGINT) — use foreignId() not foreignUuid() for user_id FKs
- UUID primary keys: use HasUuids trait

## Tests (Pest)
- Always: Sanctum::actingAs($user, ['read', 'write', 'delete'])
- Services using config(): add uses(Tests\TestCase::class) at top
- PHPStan on tests: use assert() for type narrowing, not just expect()->not->toBeNull()

## Symfony Console (CLI package)
- Use constructor: parent::__construct('command:name') — NOT $defaultName static property (deprecated in 7.x)
- Pass $input to shouldOutputJson($input)

## Commits
- Format: feat: / fix: / test: / refactor: + Co-Authored-By: Claude <noreply@anthropic.com>
- Always feature branches, never commit directly to main
