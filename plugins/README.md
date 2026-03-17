# Plugin SDK

This directory contains example plugins for the Zelta platform. Each plugin demonstrates
the hook system, permission model, and sandboxed execution environment.

## Example Plugins

### zelta/payment-analytics
Tracks payment volumes and success rates via `payment.completed` and `payment.failed` hooks.
- **Permissions**: `database:read`, `events:listen`, `cache:read`, `cache:write`
- **Hooks**: `payment.completed` (priority 10), `payment.failed` (priority 10)

### zelta/compliance-notifier
Sends Slack/webhook notifications on compliance alerts and KYC status changes.
- **Permissions**: `events:listen`, `api:external`, `config:read`
- **Hooks**: `compliance.alert` (priority 50), `compliance.kyc` (priority 50)

## Creating a Plugin

```bash
php artisan plugin:create my-vendor my-plugin
```

## Plugin Structure

```
plugins/{vendor}/{name}/
├── plugin.json              # Manifest (metadata, permissions, dependencies)
├── src/
│   └── ServiceProvider.php  # Entry point (register + boot)
├── routes/                  # Plugin routes (optional)
├── config/                  # Plugin config files (optional)
├── migrations/              # Database migrations (optional)
└── tests/                   # Plugin tests (optional)
```

## Interfaces

### PluginHookInterface

```php
use App\Infrastructure\Plugins\PluginHookInterface;

class MyListener implements PluginHookInterface
{
    public function getHookName(): string;   // e.g., 'payment.completed'
    public function getPriority(): int;      // Lower = runs first
    public function handle(array $payload): void;
}
```

### Available Hooks

| Hook | Description |
|------|-------------|
| `account.created` | New account created |
| `account.updated` | Account details updated |
| `payment.initiated` | Payment started |
| `payment.completed` | Payment confirmed |
| `payment.failed` | Payment failed |
| `compliance.alert` | Compliance alert triggered |
| `compliance.kyc` | KYC verification status changed |
| `wallet.transfer` | Token transfer occurred |
| `wallet.created` | New wallet created |
| `order.placed` | Exchange order submitted |
| `order.matched` | Order matched/filled |
| `loan.applied` | Loan application submitted |
| `loan.approved` | Loan approved |
| `bridge.initiated` | Cross-chain bridge started |
| `bridge.completed` | Cross-chain bridge completed |
| `defi.position.opened` | DeFi position opened |
| `defi.position.closed` | DeFi position closed |

### Available Permissions

| Permission | Description |
|------------|-------------|
| `database:read` | Read from database tables |
| `database:write` | Write to database tables |
| `api:internal` | Access internal API endpoints |
| `api:external` | Make outbound HTTP requests |
| `events:listen` | Listen to application events |
| `events:dispatch` | Dispatch application events |
| `queue:dispatch` | Queue background jobs |
| `cache:read` | Read from cache |
| `cache:write` | Write to cache |
| `filesystem:read` | Read files |
| `filesystem:write` | Write files |
| `config:read` | Read application configuration |

## CLI Commands

```bash
php artisan plugin:create {vendor} {name}     # Scaffold a plugin
php artisan plugin:install {manifest.json}     # Install a plugin
php artisan plugin:enable {vendor} {name}      # Enable a plugin
php artisan plugin:disable {vendor} {name}     # Disable a plugin
php artisan plugin:remove {vendor} {name}      # Remove a plugin
php artisan plugin:list                        # List all plugins
php artisan plugin:verify {vendor} {name}      # Security scan
```

## Full Documentation

See the [Plugin Development Guide](/developers/plugins) for complete documentation
including security scanner rules, manifest schema, and the plugin lifecycle.
