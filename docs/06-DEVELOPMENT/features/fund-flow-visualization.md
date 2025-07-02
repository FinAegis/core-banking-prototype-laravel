# Fund Flow Visualization Feature

## Overview

The Fund Flow Visualization feature provides users with comprehensive insights into their money movements across all accounts. It offers visual representations of fund flows, detailed statistics, and network analysis to help users understand their financial patterns.

## Components

### 1. Controller: `FundFlowController`
Location: `app/Http/Controllers/FundFlowController.php`

**Methods:**
- `index()` - Main visualization page with filters and statistics
- `accountFlow()` - Detailed flow analysis for a specific account
- `data()` - API endpoint for exporting flow data

### 2. Vue Components

#### `FundFlow/Visualization.vue`
Main dashboard showing:
- Statistics cards (total inflow, outflow, net flow, total flows)
- Time period, account, and flow type filters
- Daily fund flow line chart (Chart.js)
- Account network visualization (D3.js)
- Recent fund flows table
- Export functionality

#### `FundFlow/AccountDetail.vue`
Account-specific view showing:
- Account balance summary
- Recent inflows and outflows lists
- Flow distribution visualization
- Key metrics (flow ratio, averages)

### 3. Routes
```php
// Fund Flow Visualization Routes
Route::prefix('fund-flow')->name('fund-flow.')->group(function () {
    Route::get('/', [FundFlowController::class, 'index'])->name('index');
    Route::get('/account/{accountUuid}', [FundFlowController::class, 'accountFlow'])->name('account');
    Route::get('/data', [FundFlowController::class, 'data'])->name('data');
});
```

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

### 4. Visual Analytics

#### Line Chart
Shows daily aggregated data for:
- Total inflows (green)
- Total outflows (red)
- Net flow (blue)

#### Network Visualization
Interactive D3.js network showing:
- User accounts as blue nodes
- External entities as gray nodes
- Fund flows as weighted edges
- Drag-and-drop interaction

### 5. Statistics
Real-time calculations of:
- Total inflow amount
- Total outflow amount
- Net flow (inflow - outflow)
- Number of active days
- Average daily flows

### 6. Export Functionality
Users can export flow data as CSV including:
- Transaction type
- Source and destination
- Amount and currency
- Timestamp

## Data Flow

1. **Transaction Aggregation**
   - Queries user's transactions from the database
   - Filters by date range, account, and type
   - Groups by day for chart data

2. **Network Generation**
   - Creates nodes for each account and external entity
   - Generates edges for each fund flow
   - Aggregates multiple flows between same entities

3. **Statistics Calculation**
   - Sums inflows and outflows
   - Calculates net flow and averages
   - Identifies top flow categories

## Security Considerations

1. **Access Control**
   - Requires authentication
   - Users can only view their own accounts
   - Account ownership verified in queries

2. **Data Privacy**
   - External entity names sanitized
   - No sensitive transaction details exposed
   - Export limited to user's own data

## Performance Optimizations

1. **Query Optimization**
   - Indexed on account_uuid and created_at
   - Limited result sets (100 recent flows)
   - Aggregated data for visualizations

2. **Frontend Optimization**
   - Lazy loading of chart libraries
   - Debounced filter updates
   - Virtual scrolling for large datasets

## Testing

Comprehensive test coverage includes:
- View authorization
- Filter functionality
- Data accuracy
- Export functionality
- Multi-account scenarios

See `tests/Feature/FundFlowControllerTest.php` for test implementation.

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