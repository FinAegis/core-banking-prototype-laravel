# FinAegis Python SDK

Official Python SDK for the FinAegis API.

## Installation

```bash
pip install finaegis
```

## Quick Start

```python
from finaegis import FinAegis

# Initialize the client
client = FinAegis(
    api_key='your-api-key',
    environment='sandbox'  # or 'production'
)

# List accounts
accounts = client.accounts.list()
for account in accounts.data:
    print(f"{account.name}: ${account.balance / 100:.2f}")

# Create a new account
account = client.accounts.create(
    user_uuid='user-uuid',
    name='My Savings Account',
    initial_balance=10000  # in cents
)

# Make a transfer
transfer = client.transfers.create(
    from_account='account-uuid-1',
    to_account='account-uuid-2',
    amount=5000,  # in cents
    asset_code='USD',
    reference='Payment for services'
)
```

## Configuration

### Basic Configuration

```python
client = FinAegis(
    api_key='your-api-key',
    environment='production',  # 'production' | 'sandbox' | 'local'
    timeout=30,  # Request timeout in seconds
    max_retries=3  # Number of retries for failed requests
)
```

### Environment Variables

You can also set your API key via environment variable:

```bash
export FINAEGIS_API_KEY='your-api-key'
```

Then initialize without passing the key:

```python
client = FinAegis(environment='sandbox')
```

## Resources

### Accounts

```python
# List all accounts
accounts = client.accounts.list(page=1, per_page=20)

# Get account details
account = client.accounts.get('account-uuid')

# Get account balances
balances = client.accounts.get_balances('account-uuid')

# Deposit funds
deposit = client.accounts.deposit('account-uuid', 10000, 'USD')

# Withdraw funds
withdrawal = client.accounts.withdraw('account-uuid', 5000, 'USD')

# Freeze/unfreeze account
client.accounts.freeze('account-uuid', 'Suspicious activity')
client.accounts.unfreeze('account-uuid', 'Investigation completed')

# Get transaction history
transactions = client.accounts.get_transactions('account-uuid')
```

### Transfers

```python
# Create a transfer
transfer = client.transfers.create(
    from_account='account-uuid-1',
    to_account='account-uuid-2',
    amount=10000,
    asset_code='USD',
    reference='Invoice #123'
)

# Get transfer details
transfer_details = client.transfers.get('transfer-uuid')
```

### Exchange Rates

```python
# Get exchange rate
rate = client.exchange_rates.get('USD', 'EUR')
print(f"1 USD = {rate.rate} EUR")

# Convert currency
conversion = client.exchange_rates.convert('USD', 'EUR', 100)
print(f"${conversion['from_amount']} = €{conversion['to_amount']}")

# Refresh rates
client.exchange_rates.refresh()
```

### GCU (Global Currency Unit)

```python
# Get GCU composition
composition = client.gcu.get_composition()
for asset in composition.composition:
    print(f"{asset.asset_code}: {asset.percentage_of_basket}%")

# Get value history
history = client.gcu.get_value_history(period='7d', interval='daily')

# Get active governance polls
polls = client.gcu.get_active_polls()
```

### Webhooks

```python
# Create a webhook
webhook = client.webhooks.create(
    name='Transaction Updates',
    url='https://your-app.com/webhooks',
    events=['transaction.created', 'transaction.completed'],
    secret='your-webhook-secret'
)

# List webhook deliveries
deliveries = client.webhooks.get_deliveries(webhook.uuid)

# Get available events
events = client.webhooks.get_events()
```

### Baskets

```python
# Get basket information
basket = client.baskets.get('GCU')

# Get basket value history
history = client.baskets.get_history('GCU', period='30d')

# Create a custom basket
basket = client.baskets.create(
    code='MYBASKET',
    name='My Custom Basket',
    composition={'USD': 0.5, 'EUR': 0.3, 'GBP': 0.2}
)

# Compose/decompose basket tokens
client.baskets.compose('account-uuid', 'GCU', 1000)
client.baskets.decompose('account-uuid', 'GCU', 500)
```

## Error Handling

```python
from finaegis import FinAegisError, ValidationError, NotFoundError, RateLimitError

try:
    account = client.accounts.get('invalid-uuid')
except NotFoundError as e:
    print(f"Account not found: {e}")
except ValidationError as e:
    print(f"Validation error: {e}")
    print(f"Errors: {e.errors}")
except RateLimitError as e:
    print(f"Rate limit exceeded. Retry after {e.retry_after} seconds")
except FinAegisError as e:
    print(f"API error: {e}")
    print(f"Status code: {e.status_code}")
```

## Type Hints

The SDK provides comprehensive type hints for all methods and responses:

```python
from finaegis import FinAegis, Account, Transfer
from typing import List

def get_high_value_accounts(client: FinAegis, min_balance: float) -> List[Account]:
    """Get all accounts with balance above threshold."""
    accounts = client.accounts.list()
    return [
        account for account in accounts.data 
        if account.balance >= min_balance * 100  # Convert to cents
    ]
```

## Async Support

For async operations, install with async support:

```bash
pip install finaegis[async]
```

Then use the async client:

```python
import asyncio
from finaegis.async_client import AsyncFinAegis

async def main():
    client = AsyncFinAegis(api_key='your-api-key')
    
    # Concurrent requests
    accounts, rates = await asyncio.gather(
        client.accounts.list(),
        client.exchange_rates.list()
    )
    
    print(f"Found {len(accounts.data)} accounts")
    print(f"Found {len(rates.data)} exchange rates")

asyncio.run(main())
```

## Webhook Signature Verification

```python
import hmac
import hashlib

def verify_webhook_signature(payload: str, signature: str, secret: str) -> bool:
    """Verify webhook signature."""
    expected_signature = hmac.new(
        secret.encode(),
        payload.encode(),
        hashlib.sha256
    ).hexdigest()
    
    return hmac.compare_digest(signature, expected_signature)

# In your webhook handler
@app.route('/webhooks', methods=['POST'])
def handle_webhook():
    payload = request.get_data(as_text=True)
    signature = request.headers.get('X-FinAegis-Signature')
    
    if not verify_webhook_signature(payload, signature, 'your-secret'):
        return 'Invalid signature', 401
    
    # Process webhook
    data = json.loads(payload)
    print(f"Received {data['event']} event")
    
    return 'OK', 200
```

## Advanced Usage

### Custom Requests

```python
# Make custom API requests
response = client.request(
    method='GET',
    path='/custom-endpoint',
    params={'key': 'value'}
)
```

### Pagination

```python
# Iterate through all pages
page = 1
all_accounts = []

while True:
    response = client.accounts.list(page=page, per_page=50)
    all_accounts.extend(response.data)
    
    if page >= response.last_page:
        break
    
    page += 1

print(f"Total accounts: {len(all_accounts)}")
```

### Retry Configuration

```python
# Custom retry configuration
client = FinAegis(
    api_key='your-api-key',
    max_retries=5,  # Increase retries
    timeout=60      # Increase timeout
)
```

## Examples

### Complete Payment Flow

```python
def process_payment(client: FinAegis, from_account_id: str, to_account_id: str, amount_usd: float):
    """Process a payment between two accounts."""
    try:
        # Convert dollars to cents
        amount_cents = int(amount_usd * 100)
        
        # Check sender balance
        balances = client.accounts.get_balances(from_account_id)
        usd_balance = next(
            (b for b in balances['balances'] if b['asset_code'] == 'USD'),
            None
        )
        
        if not usd_balance or float(usd_balance['available_balance']) < amount_cents:
            raise ValueError("Insufficient balance")
        
        # Create transfer
        transfer = client.transfers.create(
            from_account=from_account_id,
            to_account=to_account_id,
            amount=amount_cents,
            asset_code='USD',
            reference=f"Payment on {datetime.now().isoformat()}"
        )
        
        print(f"Transfer completed: {transfer.uuid}")
        return transfer
        
    except Exception as e:
        print(f"Payment failed: {e}")
        raise
```

### Monitoring Account Activity

```python
def monitor_account_activity(client: FinAegis, account_id: str):
    """Monitor recent account activity."""
    # Get recent transactions
    transactions = client.accounts.get_transactions(account_id, per_page=10)
    
    print(f"Recent transactions for account {account_id}:")
    for tx in transactions.data:
        sign = '+' if tx.type == 'deposit' else '-'
        print(f"{tx.created_at}: {sign}${tx.amount / 100:.2f} - {tx.status}")
    
    # Get recent transfers
    transfers = client.accounts.get_transfers(account_id, per_page=10)
    
    print(f"\nRecent transfers:")
    for transfer in transfers.data:
        if transfer.from_account == account_id:
            print(f"{transfer.created_at}: Sent ${transfer.amount / 100:.2f} to {transfer.to_account}")
        else:
            print(f"{transfer.created_at}: Received ${transfer.amount / 100:.2f} from {transfer.from_account}")
```

## License

MIT