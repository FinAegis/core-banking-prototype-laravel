# Admin Dashboard Documentation

## Overview

The admin dashboard is the operator interface of FinAegis. It is built with Filament 3.3 and is served at `/admin`. Administrators use it to manage accounts, follow transactions and watch system health.

Only users with an administrator role can open the panel. Other signed-in users are sent to `/dashboard` with an error message. The brand name in the panel comes from `BRAND_NAME` and falls back to "Zelta".

### Module Visibility

The panel groups its resources into navigation groups, such as Banking, System, Ramp, Subscriptions, Mobile and MCP. `ADMIN_MODULES` lists the groups to show, and an empty value shows them all. The Zelta production setup limits the panel to the mobile wallet groups. Widgets follow the same setting.

This page describes the account and transaction screens in detail. The other groups have their own resources, for example for users, API keys, webhooks, Bridge customers, ramp sessions, subscriptions, MCP tool calls and anomaly detection. The Bank Operations and Audit Log pages were removed in v7.18.1, because they could never work.

## Features

### Account Management

#### Account List View
- **Real-time account overview** with balance and status
- **Advanced search** by account name, UUID, or user ID
- **Filters**:
  - Account status (Active/Frozen)
  - Balance ranges with operators (>, <, =)
- **Bulk operations** for freezing multiple accounts
- **Sortable columns** for all fields
- **Pagination** with customizable page size

#### Account Operations
The deposit action appears only when `LEDGER_DIRECT_FUNDING_ENABLED` is on. That is the default in local, testing and demo environments only. The withdrawal action appears for any account that is not frozen and has a positive balance.
- **Deposit Money**: Add funds to any account with real-time balance updates
- **Withdraw Money**: Remove funds with automatic validation
- **Freeze Account**: Prevent all transactions with confirmation dialog
- **Unfreeze Account**: Re-enable transactions
- **View Details**: Complete account information and history

#### Account Creation
- Create new accounts with:
  - Account name
  - User UUID assignment
  - Frozen status toggle

A new account always starts at zero. It is funded through a supported deposit method.

### Transaction Monitoring

#### Transaction History
- **Complete transaction log** for each account
- **Transaction types**:
  - Deposits (green badge)
  - Withdrawals (orange badge)
  - Transfers In (blue badge)
  - Transfers Out (red badge)
- **Detailed information**:
  - Transaction UUID (copyable)
  - Amount with color coding
  - Balance after transaction
  - Reference number
  - Cryptographic hash
  - Timestamp
- **Filtering** by transaction type
- **Sorting** by date (newest first)

#### Transaction Details Modal
- Click any transaction to view:
  - Full transaction hash
  - Complete reference information
  - Exact timestamp
  - All metadata

### Analytics & Statistics

#### Account Statistics Widget
Shows real-time metrics:
- **Total Accounts**: Count with active/frozen breakdown
- **Total Balance**: Sum across all accounts
- **Average Balance**: Per account calculation
- **Frozen Accounts**: Count and percentage

#### Advanced Analytics Charts

##### Account Balance Trends
- **Real-time balance tracking** over time
- **Dual metrics**: Total balance and average balance per account
- **Time filters**: Last 24 hours, 7 days, 30 days, or 90 days
- **Interactive line chart** with hover tooltips
- **Auto-refresh**: Updates every 30 seconds

##### Transaction Volume Chart
- **Transaction breakdown** by type (deposits, withdrawals, transfers)
- **Bar chart visualization** with stacked or grouped view
- **Time-based filtering**: Hourly for 24h view, daily for longer periods
- **Color coding**: Green for deposits, red for withdrawals, blue for transfers
- **Zero-fill**: Shows days with no transactions for complete picture

##### Turnover Flow Analysis
- **Monthly cash flow visualization**
- **Triple metric display**:
  - Debit (outflows) in red bars
  - Credit (inflows) in green bars
  - Net flow as blue trend line
- **Period selection**: 3, 6, 12, or 24 months
- **Currency formatting** with thousand separators
- **Trend analysis** for financial planning

##### Account Growth Chart
- **New account tracking** over time
- **Dual-axis visualization**:
  - Bar chart for new accounts per period
  - Line chart for cumulative total
- **Adaptive grouping**: Daily, weekly, or monthly based on period
- **Growth rate insights** for business metrics
- **Historical comparison** capabilities

##### System Health Monitor
- **Real-time system status** with operational indicators
- **Key metrics tracked**:
  - Database and Redis connectivity
  - Transaction processing rate per minute
  - Cache hit rate percentage
  - Queue processing status
- **Visual indicators**: Color-coded status badges
- **Performance charts**: Mini sparklines for trends
- **Auto-refresh**: Updates every 10 seconds

The current values come from the database, Redis and the queue. The small trend lines show fixed sample values, not measured history.

#### Individual Account Stats
When viewing a specific account:
- **Current Balance** with status indicator
- **Total Transactions** count
- **Monthly Credit** (current month)
- **Monthly Debit** (current month)
- **Last Transaction** timestamp

### Turnover Monitoring

#### Turnover History
- **Monthly aggregations** of account activity
- **Metrics displayed**:
  - Total Debit
  - Total Credit
  - Net Turnover (Credit - Debit)
  - Period (Month/Year)
- **Date range filtering**
- **Trend analysis** capabilities

### User Management

#### User List
- View all system users
- Search by name or email
- Edit user details
- Manage permissions

## Navigation

### Main Menu Structure

The menu starts with the Dashboard. Below it come the navigation groups that `ADMIN_MODULES` allows. The accounts described on this page are under **Banking → Accounts**, and users are under **System → Users**. Other groups include Ramp, Subscriptions, Payments, Webhooks, Mobile, MCP, Fraud Detection, Key Management and Support.

### Quick Actions
- **Global Search**: Search across all resources
- **User Menu**: Profile and logout options
- **Theme Toggle**: Light/dark mode support

## Security Features

### Access Control
- **Authentication Required**: All pages require login
- **Session Management**: Automatic timeout
- **Action Logging**: All operations are logged
- **Confirmation Dialogs**: For destructive actions

### Data Protection
- **Read-only Fields**: UUID, balance (except via operations)
- **Validation**: All inputs validated before processing
- **Error Handling**: Graceful error messages
- **Transaction Safety**: Database transactions for all operations

## Performance Features

### Caching Integration
- Account data cached for 1 hour
- Balance data cached for 5 minutes
- Automatic cache invalidation on updates
- Performance monitoring headers

### Optimizations
- Lazy loading for large datasets
- Efficient query builders
- Minimal N+1 queries
- Indexed database columns

## Usage Guide

### Getting Started

1. **Create Admin User**:
   ```bash
   php artisan user:create --admin
   ```
   Enter your name, email and password when prompted. To give an existing user the administrator role, run `php artisan user:promote user@example.com`. `php artisan user:admins` lists all administrators.

2. **Access Dashboard**:
   Navigate to `http://localhost:8000/admin`

3. **Login**:
   Use the credentials created in step 1. The panel has its own sign-in page at `/admin/login`.

### Common Tasks

#### Depositing Money
1. Navigate to Banking → Accounts
2. Find the target account
3. Click the "Deposit" action (green plus icon)
4. Enter amount in dollars (e.g., 50.00)
5. Confirm the transaction

#### Freezing an Account
1. Navigate to Banking → Accounts
2. Find the account to freeze
3. Click the "Freeze" action (red lock icon)
4. Confirm in the dialog
5. Account status updates immediately

#### Viewing Transaction History
1. Navigate to Banking → Accounts
2. Click on any account row
3. Scroll to Transaction History section
4. Use filters to narrow results
5. Click any transaction for details

#### Bulk Freezing Accounts
1. Navigate to Banking → Accounts
2. Select multiple accounts using checkboxes
3. Click "Bulk Actions" dropdown
4. Select "Freeze Selected"
5. Confirm the action

## Customization

### Theme Colors
The dashboard uses the following color scheme:
- **Primary**: Blue
- **Success**: Emerald
- **Warning**: Amber
- **Danger**: Red

### Extending Resources

To add new fields or actions to existing resources:

```php
// In AccountResource.php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Add new columns here
        ])
        ->actions([
            // Add new actions here
        ]);
}
```

## Troubleshooting

### Common Issues

1. **Redirected to `/dashboard`**
   - The user does not have an administrator role. Run `php artisan user:promote user@example.com`.
   - Check that the admin module you need is listed in `ADMIN_MODULES`.

2. **Missing Data**
   - Run migrations: `php artisan migrate`
   - Load demo data: `php artisan demo:populate`
   - Clear cache: `php artisan cache:clear`

3. **Slow Performance**
   - Check Redis connection
   - Monitor cache hit rates
   - Review database indexes

### Debug Mode

Enable debug information in a local environment only:
```bash
# In .env file
APP_DEBUG=true
```

## API Integration

The admin dashboard uses the same service layer as the API. Its actions follow the same business rules and record the same events, so the data stays consistent.

## Future Enhancements

The panel already has webhook management, custom widgets, an event store dashboard and exports for accounts, transactions and users. It has no audit log viewer. Planned features include PDF export, batch import and multi-language support.

## Support

For issues or questions:
- Check the [main documentation](../README.md)
- Review [CLAUDE.md](../06-DEVELOPMENT/CLAUDE.md) for development guidance
- Submit issues to the GitHub repository