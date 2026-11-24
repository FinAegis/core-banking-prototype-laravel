# Bank and Open Banking Withdrawals

## Overview

The web wallet has two demo pages for withdrawing money to a bank account. The first page takes bank details that the person types in. The second page shows how a deployment could start a withdrawal through a bank's Open Banking API. Licensing and regulatory compliance are the responsibility of the operator of each deployment.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Status at v7.18.1

- Both pages sit behind `feature:ledger.direct_funding_enabled`, which reads `LEDGER_DIRECT_FUNDING_ENABLED`. The switch is on by default only for `APP_ENV=local`, `testing` and `demo`. It is off in production and for Zelta.
- A withdrawal only debits the FinAegis ledger. The bank payout step is a stub, so no money reaches a bank.
- Since v7.18.0 the bank withdrawal page no longer saves bank accounts. The saved-account feature wrote columns that the `bank_accounts` table never had, so it always failed. People now type their bank details for each withdrawal. The add and remove bank-account routes and the "Manage Bank Accounts" block are gone.
- The Open Banking steps call bank connector methods that the connector adapters do not implement: `getLogoUrl()`, `getAuthorizationUrl()`, `exchangeAuthorizationCode()` and `initiatePayment()`. The flow therefore runs only in tests with mocked connectors. The bank switches `BANK_PAYSERA_ENABLED`, `BANK_DEUTSCHE_ENABLED`, `BANK_SANTANDER_ENABLED` and `FLUTTERWAVE_ENABLED` are off by default, so the Open Banking page lists no banks.

## Bank Withdrawal Page

### What it does

The page at `/wallet/withdraw/bank` asks for an amount, a currency and the bank details. The minimum amount is 10, and the currency is USD, EUR or GBP. The page checks the balance and then creates a withdrawal request through `PaymentGatewayService::createWithdrawalRequest`.

The amount is converted to cents exactly with `bcmath`. Earlier versions used a float product, which turned amounts such as 19.99 into 1998 cents.

### Fields

| Field | Required |
|-------|----------|
| `amount` | Yes |
| `currency` | Yes |
| `bank_name` | Yes |
| `account_number` | Yes |
| `account_holder_name` | Yes |
| `routing_number` | No |
| `iban` | No |
| `swift` | No |

### Controller

`WithdrawalController` has two actions.
- `create()` shows the form and the person's balances.
- `store()` validates the form and submits the withdrawal request.

## Open Banking Withdrawal Page

### Intended flow

1. The person opens Withdraw in the wallet and chooses the Open Banking option.
2. The person picks a bank and enters an amount.
3. The app sends the person to the bank to authorise access.
4. The bank sends the person back to the callback.
5. The app stores the bank connection and reads the person's bank accounts.
6. The app creates a withdrawal request and asks the bank to pay out.

The callback uses the first bank account it finds. The account selection page is reached only from a bank that is already connected.

### Controller

`OpenBankingWithdrawalController` has five actions.
- `create()` shows connected banks, available banks and balances.
- `initiate()` stores the withdrawal details in the session and redirects to the bank.
- `callback()` checks the `state` value, stores the bank connection and submits the withdrawal.
- `selectAccount()` lists the bank accounts of a connected bank.
- `processWithAccount()` submits the withdrawal to the chosen bank account.

### Services

- `BankIntegrationService` manages bank connectors, bank connections and bank accounts. It stores bank credentials and account numbers encrypted.
- `PaymentGatewayService` creates the withdrawal request and passes it to the payment service.

### Bank connector adapters

`BankIntegrationServiceProvider` registers example connectors for the Paysera, Deutsche Bank and Santander APIs, and for Flutterwave. These are integration adapters for third-party APIs; no partnership or endorsement implied.

## Views

The views are in `resources/views/wallet/`.

1. `withdraw-options.blade.php` lets the person choose between the Open Banking page and the bank withdrawal page.
2. `withdraw-bank.blade.php` is the bank details form.
3. `withdraw-openbanking.blade.php` shows bank selection, connected banks and the withdrawal form.
4. `withdraw-openbanking-accounts.blade.php` lists the bank accounts of a connected bank.

## Routes

```php
// GET /wallet/withdraw shows the options page and stays available.
Route::prefix('withdraw')->name('withdraw.')->middleware('feature:ledger.direct_funding_enabled')->group(function () {
    Route::get('/bank', [WithdrawalController::class, 'create'])->name('create');
    Route::post('/bank', [WithdrawalController::class, 'store'])->name('store');

    Route::get('/openbanking', [OpenBankingWithdrawalController::class, 'create'])->name('openbanking');
    Route::post('/openbanking/initiate', [OpenBankingWithdrawalController::class, 'initiate'])->name('openbanking.initiate');
    Route::get('/openbanking/callback', [OpenBankingWithdrawalController::class, 'callback'])->name('openbanking.callback');
    Route::post('/openbanking/select-account', [OpenBankingWithdrawalController::class, 'selectAccount'])->name('openbanking.select-account');
    Route::post('/openbanking/process', [OpenBankingWithdrawalController::class, 'processWithAccount'])->name('openbanking.process');
});
```

These routes are inside the `wallet` group in `routes/web.php`, so the full paths start with `/wallet/withdraw` and the route names start with `wallet.withdraw.`.

## Security Features

1. **Authorisation.** The redirect to the bank carries a `state` value, and the callback rejects a response whose `state` does not match.
2. **Data protection.** Bank passwords are never stored. Bank credentials and account numbers are stored encrypted. The withdrawal details stay in the session until the callback.
3. **Checks.** Both pages check the balance and the minimum amount, and every withdrawal gets a reference.

## Configuration

### Environment variables

```env
# Paysera
BANK_PAYSERA_ENABLED=false
BANK_PAYSERA_CLIENT_ID=your_client_id
BANK_PAYSERA_CLIENT_SECRET=your_client_secret
BANK_PAYSERA_BASE_URL=https://bank.paysera.com/rest/v1
BANK_PAYSERA_OAUTH_URL=https://bank.paysera.com/oauth/v1

# Deutsche Bank
BANK_DEUTSCHE_ENABLED=false
BANK_DEUTSCHE_CLIENT_ID=your_client_id
BANK_DEUTSCHE_CLIENT_SECRET=your_client_secret
BANK_DEUTSCHE_BASE_URL=https://api.db.com/v2

# Santander
BANK_SANTANDER_ENABLED=false

# Wallet pages that move ledger balances (production keeps this off)
LEDGER_DIRECT_FUNDING_ENABLED=true
```

The bank settings are read from `config/services.php` under `banks`.

### Bank connector registration

Banks are registered in `app/Providers/BankIntegrationServiceProvider.php`.

```php
if (config('services.banks.paysera.enabled', true)) {
    $payseraAdapter = new BankConnectorAdapter(
        new PayseraConnector([
            'name'          => 'Paysera',
            'client_id'     => config('services.banks.paysera.client_id'),
            'client_secret' => config('services.banks.paysera.client_secret'),
            'base_url'      => config('services.banks.paysera.base_url', 'https://bank.paysera.com/rest/v1'),
        ]),
        'PAYSERA',
        'Paysera'
    );
    $service->registerConnector('PAYSERA', $payseraAdapter);
}
```

## Testing

### Feature tests

- `tests/Feature/OpenBankingWithdrawalTest.php` covers the Open Banking pages with mocked connectors.
- `tests/Feature/Wallet/OpenBankingWithdrawalNoAccountTest.php` covers a person without an account.
- `tests/Feature/Http/Controllers/WithdrawalControllerTest.php` covers the bank withdrawal form, including the exact conversion to cents.

### Manual testing

1. Run the app with `APP_ENV=demo` or set `LEDGER_DIRECT_FUNDING_ENABLED=true`.
2. Create a test user with a balance.
3. Open Wallet, then Withdraw.
4. Choose the bank transfer option and type the bank details.
5. Check that the withdrawal is accepted and the balance goes down once the withdrawal workflow has run.

## Future Enhancements

These ideas are not built.

1. Implement the authorisation and payment methods in the bank connector adapters, so the Open Banking flow works against a real sandbox.
2. Let the person pick the bank account in the callback instead of using the first one.
3. Replace the stub payout with a real payout through a licensed payment provider.
