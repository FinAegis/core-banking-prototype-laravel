# Fund Flow Visualization Feature

## Overview

Fund Flow Visualization is a web page that shows how money moves through a user's accounts. It shows flow statistics and a table of recent flows, filtered by period, account and flow type. It helps users understand their own financial patterns.

The page is only partly built. The live page is a Blade view, and its chart area is still a placeholder. The planned Vue pages with Chart.js and D3.js exist in `resources/js/Pages/FundFlow/`, but they are not used. Inertia and Vue are not installed, and `vite.config.js` does not build them.

## Components

### 1. Controller: `FundFlowController`
Location: `app/Http/Controllers/FundFlowController.php`

**Methods:**
- `index()` renders the Blade view `resources/views/fund-flow/index.blade.php` with the filters, statistics, chart data and network data.
- `accountFlow()` returns the flow analysis for one account as JSON. It includes the balances, inflows, outflows, flow balance and counterparty analysis.
- `data()` returns the flow data as JSON.

### 2. Blade View (live)

`resources/views/fund-flow/index.blade.php` shows:
- filters for time period, account and flow type
- statistics cards for total inflow, total outflow, net flow and internal transfers
- a "Daily Flow Chart" area that is still a placeholder
- a table of recent fund flows

The view does not render the network data, and it has no export button.

### 3. Vue Components (not used)

These files were written for an Inertia front end that was never installed:
- `FundFlow/Visualization.vue` would add a Chart.js line chart, a D3.js account network graph and a CSV export.
- `FundFlow/AccountDetail.vue` would show one account with its inflows, outflows, flow distribution and key metrics.

### 4. Routes
```php
// Fund Flow Visualization Routes
Route::prefix('fund-flow')->name('fund-flow.')->group(function () {
    Route::get('/', [FundFlowController::class, 'index'])->name('index');
    Route::get('/account/{accountUuid}', [FundFlowController::class, 'accountFlow'])->name('account');
    Route::get('/data', [FundFlowController::class, 'data'])->name('data');
});
```

The routes live in `routes/web.php` inside the group that requires `auth:sanctum`, the Jetstream session and a verified email.

## Features

### 1. Time-based Filtering
- Last 24 hours
- Last 7 days (default)
- Last 30 days
- Last 90 days

### 2. Account Filtering
- All accounts view
- Individual account selection

### 3. Flow Type Filtering
- All types
- Deposits only
- Withdrawals only
- Transfers only

### 4. Visual Analytics (planned)

The controller already prepares the daily chart data and the network data. The unused Vue page shows how they were meant to look. The line chart would show daily inflows in green, outflows in red and net flow in blue. The network graph would be a D3.js force layout with user accounts as blue nodes and external entities as gray nodes, and fund flows as weighted edges.

### 5. Statistics
The page calculates these values for the selected filters:
- Total inflow amount
- Total outflow amount
- Net flow (inflow - outflow)
- Number of active days
- Average daily flows

### 6. Export (planned)
`GET /fund-flow/data` returns the flow data as JSON. The unused Vue page turns it into a CSV file with the columns Type, From, To, Amount, Currency and Date. The live Blade page has no export button yet.

## Data Flow

1. The controller reads the user's completed transactions from the `transaction_projections` table. It filters them by date range, account and type, and groups them by day for the chart.
2. It builds the network by creating a node for each account and each external entity. It adds an edge for each fund flow and merges repeated flows between the same two nodes.
3. It sums the inflows and outflows and calculates the net flow and the averages.

The counterparty analysis on the account page is a placeholder. It returns empty lists for now.

## Security Considerations

1. **Access Control.** The pages require a signed-in, verified user. Users see only their own accounts, because every query starts from the user's own accounts.
2. **Data Privacy.** The JSON data contains only the user's own data. External sources and destinations are shown by name, with "External Bank" when no name is stored.

## Performance

The queries filter on `account_uuid` and `created_at`. The inflow and outflow lists are limited to the 100 most recent rows. The charts use aggregated daily data rather than single transactions.

The live Blade page loads no chart libraries. The filters submit a normal form.

## Testing

The tests cover view authorization, the filters, data accuracy, the export and users with several accounts. See `tests/Feature/FundFlowControllerTest.php`.

## Future Enhancements

1. **Advanced Analytics**
   - Predictive flow patterns
   - Anomaly detection
   - Category-based analysis

2. **Additional Visualizations**
   - Sankey diagrams
   - Heat maps
   - Bubble charts

3. **Integration Features**
   - Scheduled reports
   - Alert notifications
   - Budget tracking
