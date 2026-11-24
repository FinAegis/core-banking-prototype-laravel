# Transaction Status Tracking Feature

## Overview

Transaction status tracking is a set of web pages for the FinAegis web wallet.
It shows a signed-in person their pending and finished transactions and the timeline of each one.
The person can also cancel a pending transaction or retry a failed one.

## Features

### 1. Dashboard

The dashboard lists pending transactions and finished transactions separately.
It shows statistics such as the success rate, the number of pending transactions and the average completion time.
It can filter by status, type, account and date range.

### 2. Detail view

The detail view shows one transaction with its timeline, its current status and its metadata.
It links to related transactions, such as a retry or its parent.

### 3. Actions

A person can cancel a transaction that is `pending` or on `hold`, within 30 minutes of its creation.
Deposits and internal transfers cannot be cancelled.
A person can retry a `failed` transaction once, within 24 hours of the failure.
A status endpoint returns the current state as JSON for refreshing a page.

## Implementation Details

### Controller

`App\Http\Controllers\TransactionStatusController` serves the pages.

| Method | Purpose |
|--------|---------|
| `index()` | Dashboard with pending and finished transactions and statistics |
| `show()` | Detail view with timeline and related transactions |
| `status()` | JSON status for one transaction |
| `cancel()` | Cancels a pending or held transaction that is less than 30 minutes old |
| `retry()` | Creates a retry of a failed transaction |

The controller reads the `transaction_projections` table and joins the person's accounts.

### Views

The pages are the Blade views `resources/views/transactions/status-tracking.blade.php` and `resources/views/transactions/status-detail.blade.php`.
Vue versions of the same pages, `resources/js/Pages/Transactions/StatusTracking.vue` and `StatusDetail.vue`, are in the repository.
The controller does not render them. They contain the 5-second auto-refresh, which the Blade pages do not have.

### Routes

The routes sit in the authenticated web group, which uses `auth:sanctum`, the Jetstream session guard and `verified`.

```php
Route::get('/transactions/status', [TransactionStatusController::class, 'index'])
    ->name('transactions.status');
Route::prefix('transactions/status')->name('transactions.status.')->group(function () {
    Route::get('/{transactionId}', [TransactionStatusController::class, 'show'])->name('show');
    Route::get('/{transactionId}/status', [TransactionStatusController::class, 'status'])->name('status');
    Route::post('/{transactionId}/cancel', [TransactionStatusController::class, 'cancel'])->name('cancel');
    Route::post('/{transactionId}/retry', [TransactionStatusController::class, 'retry'])->name('retry');
});
```

## What cancel and retry do

A cancel sets the projection row's status to `cancelled` and records who cancelled it and when.
It then logs a reversal. It does not reverse balances or release holds.

A retry copies the failed projection row into a new `pending` row.
The new row gets a `RETRY-` reference and points to the original transaction.
It does not run the payment or transfer again.

## Status Types

| Status | Meaning |
|--------|---------|
| `pending` | The transaction was started and waits for processing |
| `processing` | The transaction is being processed |
| `hold` | The transaction is held, for example for a compliance check or a limit |
| `completed` | The transaction finished successfully |
| `failed` | The transaction failed |
| `cancelled` | A person or the system cancelled it |

## Progress Calculation

The progress percentage is an estimate.
It compares the time since the transaction was created with a typical processing time for its type.
It never shows more than 95% until the transaction is completed.

## Security

A person only sees transactions on their own accounts.
The server checks every cancel and retry request.
Laravel's CSRF protection covers the POST routes.

## Limits

The dashboard loads at most 50 pending and 100 finished transactions.
The detail view shows at most 5 related transactions.

## Testing

`tests/Feature/TransactionStatusTrackingTest.php` covers the pages and actions.

### Manual testing

1. Create test transactions in several states.
2. Check the filters.
3. Cancel a pending transaction and retry a failed one.
4. Check the pages on a small screen.

## Future Enhancements

1. Live updates through WebSockets, browser notifications and mobile push
2. Reports on transaction trends and failures
3. Bulk cancel, bulk retry and export
4. A more detailed timeline with external and compliance events
5. Real reversal of balances on cancel and a real re-run on retry
