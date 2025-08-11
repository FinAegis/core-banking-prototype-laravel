@extends('layouts.public')

@section('title', 'Code Examples - FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Code Examples - FinAegis',
        'description' => 'Working examples and integration patterns for common use cases with the FinAegis API.',
        'keywords' => 'FinAegis API examples, code samples, integration patterns, developer examples',
    ])
@endsection

@push('styles')
<style>
    /* Tab styling */
    .code-tabs {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 1.5rem;
        background: #f3f4f6;
        padding: 0.25rem;
        border-radius: 0.5rem;
    }
    .code-tab {
        padding: 0.5rem 1rem;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        border-radius: 0.375rem;
        transition: all 0.2s;
        font-size: 0.875rem;
    }
    .code-tab:hover {
        color: #374151;
        background: #e5e7eb;
    }
    .code-tab.active {
        color: white;
        background: #4f46e5;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    /* Code example wrapper */
    .code-example-wrapper {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .code-example-header {
        background: #f9fafb;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    /* Animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }
</style>
@endpush

@section('content')
    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-green-600 to-blue-600 text-white">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
        </div>
        
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-5xl md:text-6xl font-bold mb-6">
                    Code Examples
                </h1>
                <p class="text-xl md:text-2xl text-green-100 max-w-3xl mx-auto">
                    Working examples and integration patterns for common use cases with the FinAegis API.
                </p>
            </div>
        </div>
    </section>

    <!-- Example Categories -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900">Browse by Category</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <a href="#basic-operations" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-blue-200 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Basic Operations</h3>
                    <p class="text-gray-600 text-sm">Account creation, balance queries, and simple transfers</p>
                </a>

                <a href="#advanced-workflows" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-green-200 transition-colors">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Advanced Workflows</h3>
                    <p class="text-gray-600 text-sm">Multi-step transactions, batch operations, and saga patterns</p>
                </a>

                <a href="#webhooks" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-purple-200 transition-colors">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v2a2 2 0 002 2h4a2 2 0 002-2v-2h2a2 2 0 002-2V9a2 2 0 00-2-2h-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v2H4a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Webhooks</h3>
                    <p class="text-gray-600 text-sm">Real-time notifications and event handling</p>
                </a>

                <a href="#error-handling" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-red-200 transition-colors">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Error Handling</h3>
                    <p class="text-gray-600 text-sm">Robust error handling and retry patterns</p>
                </a>

                <a href="#ai-agent" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-indigo-200 transition-colors">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">AI Agent Integration</h3>
                    <p class="text-gray-600 text-sm">Banking AI assistant and MCP tools</p>
                </a>

                <a href="#mcp-tools" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-yellow-200 transition-colors">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">MCP Tools</h3>
                    <p class="text-gray-600 text-sm">Model Context Protocol integration</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Examples Content -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Basic Operations -->
            <div id="basic-operations" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Basic Operations</h2>
                
                <div class="space-y-12">
                    <!-- Create Account Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Create Account and Get Balance</h3>
                                    <p class="text-gray-600 mt-1">Initialize a new account and retrieve balance information</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">POST</span>
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">GET</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'create-js')" class="code-tab active">JavaScript</button>
                                <button onclick="switchTab(event, 'create-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'create-php')" class="code-tab">PHP</button>
                                <button onclick="switchTab(event, 'create-curl')" class="code-tab">cURL</button>
                            </div>
                            
                            <!-- JavaScript -->
                            <div id="create-js" class="tab-content active animate-fade-in">
                                <x-code-block language="javascript">
import { FinAegis } from '@finaegis/sdk';

const client = new FinAegis({
  apiKey: process.env.FINAEGIS_API_KEY,
  baseURL: 'https://api.finaegis.org/v2'
});

async function createAccountAndCheckBalance() {
  try {
    // Create a new account
    const account = await client.accounts.create({
      name: 'My Main Account',
      type: 'personal',
      metadata: {
        customer_id: 'cust_123',
        purpose: 'savings'
      }
    });
    
    console.log('Account created:', account.uuid);
    
    // Get account balances
    const balances = await client.accounts.getBalances(account.uuid);
    
    console.log('Current balances:');
    balances.data.balances.forEach(balance => {
      console.log(`${balance.asset_code}: ${balance.available_balance}`);
    });
    
    return account;
  } catch (error) {
    console.error('Error:', error.message);
    throw error;
  }
}

createAccountAndCheckBalance();
                                </x-code-block>
                            </div>
                            
                            <!-- Python -->
                            <div id="create-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
from finaegis import FinAegis
import os

client = FinAegis(
    api_key=os.environ['FINAEGIS_API_KEY'],
    base_url='https://api.finaegis.org/v2'
)

def create_account_and_check_balance():
    try:
        # Create a new account
        account = client.accounts.create(
            name='My Main Account',
            type='personal',
            metadata={
                'customer_id': 'cust_123',
                'purpose': 'savings'
            }
        )
        
        print(f'Account created: {account.uuid}')
        
        # Get account balances
        balances = client.accounts.get_balances(account.uuid)
        
        print('Current balances:')
        for balance in balances.data.balances:
            print(f'{balance.asset_code}: {balance.available_balance}')
        
        return account
    except Exception as error:
        print(f'Error: {error}')
        raise

create_account_and_check_balance()
                                </x-code-block>
                            </div>
                            
                            <!-- PHP -->
                            <div id="create-php" class="tab-content animate-fade-in">
                                <x-code-block language="php">
require_once 'vendor/autoload.php';

use FinAegis\Client;

$client = new Client([
    'apiKey' => $_ENV['FINAEGIS_API_KEY'],
    'baseURL' => 'https://api.finaegis.org/v2'
]);

function createAccountAndCheckBalance($client) {
    try {
        // Create a new account
        $account = $client->accounts->create([
            'name' => 'My Main Account',
            'type' => 'personal',
            'metadata' => [
                'customer_id' => 'cust_123',
                'purpose' => 'savings'
            ]
        ]);
        
        echo "Account created: {$account->uuid}\n";
        
        // Get account balances
        $balances = $client->accounts->getBalances($account->uuid);
        
        echo "Current balances:\n";
        foreach ($balances->data->balances as $balance) {
            echo "{$balance->asset_code}: {$balance->available_balance}\n";
        }
        
        return $account;
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        throw $e;
    }
}

createAccountAndCheckBalance($client);
                                </x-code-block>
                            </div>
                            
                            <!-- cURL -->
                            <div id="create-curl" class="tab-content animate-fade-in">
                                <x-code-block language="bash">
# Create a new account
curl -X POST https://api.finaegis.org/v2/accounts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Main Account",
    "type": "personal",
    "metadata": {
      "customer_id": "cust_123",
      "purpose": "savings"
    }
  }'

# Response
{
  "data": {
    "uuid": "acct_1234567890abcdef",
    "name": "My Main Account",
    "type": "personal",
    "status": "active",
    "created_at": "2025-01-01T12:00:00Z"
  }
}

# Get account balances
curl -X GET https://api.finaegis.org/v2/accounts/acct_1234567890abcdef/balances \
  -H "Authorization: Bearer YOUR_API_KEY"
                                </x-code-block>
                            </div>
                        </div>
                    </div>

                    <!-- Simple Transfer Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Simple Money Transfer</h3>
                                    <p class="text-gray-600 mt-1">Transfer funds between accounts with automatic workflow processing</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">POST</span>
                                    <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">Workflow</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'transfer-js')" class="code-tab active">JavaScript</button>
                                <button onclick="switchTab(event, 'transfer-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'transfer-response')" class="code-tab">Response</button>
                            </div>
                            
                            <!-- JavaScript -->
                            <div id="transfer-js" class="tab-content active animate-fade-in">
                                <x-code-block language="javascript">
async function transferFunds(fromAccount, toAccount, amount) {
  try {
    const transfer = await client.transfers.create({
      from_account: fromAccount,
      to_account: toAccount,
      amount: amount,
      asset_code: 'USD',
      reference: 'Payment for services',
      workflow_enabled: true,
      metadata: {
        invoice_id: 'INV-2025-001',
        payment_type: 'service'
      }
    });
    
    console.log('Transfer initiated:', transfer.uuid);
    console.log('Status:', transfer.status);
    console.log('Workflow ID:', transfer.workflow_id);
    
    // Poll for completion
    const completed = await client.transfers.waitForCompletion(
      transfer.uuid, 
      { timeout: 30000 }
    );
    
    console.log('Transfer completed:', completed.status);
    return completed;
  } catch (error) {
    console.error('Transfer failed:', error.message);
    throw error;
  }
}

// Usage
transferFunds(
  'acct_1234567890',
  'acct_0987654321', 
  '100.00'
);
                                </x-code-block>
                            </div>
                            
                            <!-- Python -->
                            <div id="transfer-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
def transfer_funds(from_account, to_account, amount):
    try:
        transfer = client.transfers.create(
            from_account=from_account,
            to_account=to_account,
            amount=amount,
            asset_code='USD',
            reference='Payment for services',
            workflow_enabled=True,
            metadata={
                'invoice_id': 'INV-2025-001',
                'payment_type': 'service'
            }
        )
        
        print(f'Transfer initiated: {transfer.uuid}')
        print(f'Status: {transfer.status}')
        print(f'Workflow ID: {transfer.workflow_id}')
        
        # Poll for completion
        completed = client.transfers.wait_for_completion(
            transfer.uuid,
            timeout=30000
        )
        
        print(f'Transfer completed: {completed.status}')
        return completed
    except Exception as error:
        print(f'Transfer failed: {error}')
        raise

# Usage
transfer_funds(
    'acct_1234567890',
    'acct_0987654321',
    '100.00'
)
                                </x-code-block>
                            </div>
                            
                            <!-- Response -->
                            <div id="transfer-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "data": {
    "uuid": "txfr_abc123def456",
    "from_account": "acct_1234567890",
    "to_account": "acct_0987654321",
    "amount": "100.00",
    "asset_code": "USD",
    "status": "pending",
    "reference": "Payment for services",
    "workflow_id": "wf_789xyz012",
    "created_at": "2025-01-01T12:00:00Z",
    "estimated_completion": "2025-01-01T12:05:00Z",
    "metadata": {
      "invoice_id": "INV-2025-001",
      "payment_type": "service"
    },
    "fees": {
      "transfer_fee": "0.50",
      "currency": "USD"
    }
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>
                    <!-- List Transactions Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">List Account Transactions</h3>
                                    <p class="text-gray-600 mt-1">Retrieve transaction history with pagination and filtering</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">GET</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">Paginated</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'list-js')" class="code-tab active">JavaScript</button>
                                <button onclick="switchTab(event, 'list-curl')" class="code-tab">cURL</button>
                                <button onclick="switchTab(event, 'list-response')" class="code-tab">Response</button>
                            </div>
                            
                            <!-- JavaScript -->
                            <div id="list-js" class="tab-content active animate-fade-in">
                                <x-code-block language="javascript">
async function getAccountTransactions(accountId, options = {}) {
  try {
    const transactions = await client.accounts.getTransactions(accountId, {
      limit: options.limit || 20,
      page: options.page || 1,
      type: options.type, // 'deposit', 'withdrawal', 'transfer'
      status: options.status, // 'pending', 'completed', 'failed'
      start_date: options.startDate,
      end_date: options.endDate,
      sort: options.sort || '-created_at' // - for descending
    });
    
    console.log(`Found ${transactions.meta.total} transactions`);
    console.log(`Showing page ${transactions.meta.current_page} of ${transactions.meta.last_page}`);
    
    transactions.data.forEach(tx => {
      console.log(`${tx.created_at}: ${tx.type} ${tx.amount} ${tx.asset_code} - ${tx.status}`);
    });
    
    return transactions;
  } catch (error) {
    console.error('Failed to fetch transactions:', error.message);
    throw error;
  }
}

// Usage examples
// Get all transactions
getAccountTransactions('acct_1234567890');

// Get withdrawals from last 30 days
getAccountTransactions('acct_1234567890', {
  type: 'withdrawal',
  startDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString()
});
                                </x-code-block>
                            </div>
                            
                            <!-- cURL -->
                            <div id="list-curl" class="tab-content animate-fade-in">
                                <x-code-block language="bash">
# Get all transactions for an account
curl -X GET "https://api.finaegis.org/v2/accounts/acct_1234567890/transactions" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Get transactions with filters
curl -X GET "https://api.finaegis.org/v2/accounts/acct_1234567890/transactions?\
limit=20&\
page=1&\
type=withdrawal&\
status=completed&\
start_date=2025-01-01T00:00:00Z&\
end_date=2025-01-31T23:59:59Z&\
sort=-created_at" \
  -H "Authorization: Bearer YOUR_API_KEY"
                                </x-code-block>
                            </div>
                            
                            <!-- Response -->
                            <div id="list-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "data": [
    {
      "uuid": "tx_789xyz123abc",
      "account_uuid": "acct_1234567890",
      "type": "deposit",
      "amount": "500.00",
      "asset_code": "USD",
      "status": "completed",
      "reference": "Salary deposit",
      "created_at": "2025-01-15T10:30:00Z",
      "completed_at": "2025-01-15T10:30:05Z",
      "balance_after": "1500.00"
    },
    {
      "uuid": "tx_456def789ghi",
      "account_uuid": "acct_1234567890",
      "type": "withdrawal",
      "amount": "100.00",
      "asset_code": "USD",
      "status": "completed",
      "reference": "ATM withdrawal",
      "created_at": "2025-01-14T15:45:00Z",
      "completed_at": "2025-01-14T15:45:10Z",
      "balance_after": "1000.00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 95,
    "from": 1,
    "to": 20
  },
  "links": {
    "first": "https://api.finaegis.org/v2/accounts/acct_1234567890/transactions?page=1",
    "last": "https://api.finaegis.org/v2/accounts/acct_1234567890/transactions?page=5",
    "prev": null,
    "next": "https://api.finaegis.org/v2/accounts/acct_1234567890/transactions?page=2"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Workflows -->
            <div id="advanced-workflows" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Advanced Workflows</h2>
                
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h3 class="text-xl font-semibold text-gray-900">Multi-Currency Conversion with Transfer</h3>
                        <p class="text-gray-600 mt-1">Convert currency and transfer in one workflow</p>
                    </div>
                    
                    <div class="p-6">
                        <x-code-block language="javascript" title="JavaScript Implementation">
async function convertAndTransfer(fromAccount, toAccount, amount, fromCurrency, toCurrency) {
  try {
    // Get current exchange rate
    const rate = await client.exchangeRates.get(fromCurrency, toCurrency);
    const convertedAmount = (parseFloat(amount) * rate.rate).toFixed(2);
    
    console.log(`Exchange rate: 1 ${fromCurrency} = ${rate.rate} ${toCurrency}`);
    console.log(`Converting ${amount} ${fromCurrency} to ${convertedAmount} ${toCurrency}`);
    
    // Create workflow for conversion + transfer
    const workflow = await client.workflows.create({
      type: 'currency_conversion_transfer',
      steps: [
        {
          type: 'currency_conversion',
          from_account: fromAccount,
          amount: amount,
          from_currency: fromCurrency,
          to_currency: toCurrency,
          max_slippage: 0.01 // 1% max slippage
        },
        {
          type: 'transfer',
          from_account: fromAccount,
          to_account: toAccount,
          amount: convertedAmount,
          asset_code: toCurrency,
          reference: 'Converted payment'
        }
      ],
      compensation_enabled: true
    });
    
    console.log('Workflow started:', workflow.uuid);
    
    // Monitor workflow progress
    const result = await client.workflows.monitor(workflow.uuid, {
      onProgress: (step) => console.log(`Step ${step.name}: ${step.status}`),
      timeout: 60000
    });
    
    return result;
  } catch (error) {
    console.error('Workflow failed:', error.message);
    
    // Check if compensation was executed
    if (error.compensation_executed) {
      console.log('Automatic rollback completed');
    }
    
    throw error;
  }
}

convertAndTransfer(
  'acct_source123', 
  'acct_dest456', 
  '1000.00', 
  'USD', 
  'EUR'
);
                        </x-code-block>
                    </div>
                </div>
            </div>

            <!-- Webhooks -->
            <div id="webhooks" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Webhooks</h2>
                
                <div class="space-y-8">
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b">
                            <h3 class="text-xl font-semibold text-gray-900">Setting Up Webhook Endpoints</h3>
                            <p class="text-gray-600 mt-1">Handle real-time events from FinAegis</p>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Express.js Handler</h4>
                                    <x-code-block language="javascript">
const express = require('express');
const crypto = require('crypto');
const app = express();

// Middleware to verify webhook signatures
function verifyWebhookSignature(req, res, next) {
  const signature = req.headers['x-finaegis-signature'];
  const payload = JSON.stringify(req.body);
  const secret = process.env.WEBHOOK_SECRET;
  
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  if (signature !== expectedSignature) {
    return res.status(401).send('Invalid signature');
  }
  
  next();
}

// Webhook endpoint
app.post('/webhooks/finaegis', 
  express.json(),
  verifyWebhookSignature,
  (req, res) => {
    const { event_type, data } = req.body;
    
    console.log(`Received webhook: ${event_type}`);
    
    switch (event_type) {
      case 'transfer.completed':
        handleTransferCompleted(data);
        break;
      case 'transfer.failed':
        handleTransferFailed(data);
        break;
      case 'account.balance_updated':
        handleBalanceUpdated(data);
        break;
      case 'workflow.completed':
        handleWorkflowCompleted(data);
        break;
      case 'workflow.compensation_executed':
        handleCompensationExecuted(data);
        break;
      default:
        console.log(`Unhandled event type: ${event_type}`);
    }
    
    res.status(200).send('OK');
  }
);

async function handleTransferCompleted(transfer) {
  console.log(`Transfer ${transfer.uuid} completed`);
  
  // Update your database
  await updateTransferStatus(transfer.uuid, 'completed');
  
  // Send notification to user
  await notifyUser(transfer.from_account, {
    type: 'transfer_completed',
    amount: transfer.amount,
    currency: transfer.asset_code,
    reference: transfer.reference
  });
}

async function handleWorkflowCompleted(workflow) {
  console.log(`Workflow ${workflow.uuid} completed successfully`);
  
  // Process completion logic
  await processWorkflowCompletion(workflow);
}
                                    </x-code-block>
                                </div>
                                
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Webhook Configuration</h4>
                                    <x-code-block language="javascript">
// Configure webhook endpoints
async function setupWebhooks() {
  const webhook = await client.webhooks.create({
    url: 'https://yourapp.com/webhooks/finaegis',
    events: [
      'transfer.completed',
      'transfer.failed',
      'account.balance_updated',
      'workflow.completed',
      'workflow.failed',
      'workflow.compensation_executed'
    ],
    secret: process.env.WEBHOOK_SECRET,
    active: true
  });
  
  console.log('Webhook configured:', webhook.id);
  return webhook;
}

// Test webhook delivery
async function testWebhook(webhookId) {
  const result = await client.webhooks.test(webhookId, {
    event_type: 'test.webhook',
    data: { message: 'Test webhook delivery' }
  });
  
  console.log('Test result:', result.status);
}
                                    </x-code-block>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Agent Integration -->
            <div id="ai-agent" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">AI Agent Integration</h2>
                
                <div class="space-y-12">
                    <!-- Basic AI Chat Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">AI Banking Assistant Chat</h3>
                                    <p class="text-gray-600 mt-1">Integrate intelligent banking conversations with context awareness</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">AI</span>
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">MCP</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'ai-chat-js')" class="code-tab active">JavaScript</button>
                                <button onclick="switchTab(event, 'ai-chat-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'ai-chat-response')" class="code-tab">Response</button>
                            </div>
                            
                            <!-- JavaScript -->
                            <div id="ai-chat-js" class="tab-content active animate-fade-in">
                                <x-code-block language="javascript">
import { FinAegisAI } from '@finaegis/ai-sdk';

const aiClient = new FinAegisAI({
  apiKey: process.env.FINAEGIS_API_KEY,
  conversationId: 'conv_' + Math.random().toString(36).substr(2, 9)
});

async function bankingConversation() {
  try {
    // Send a message to the AI agent
    const response = await aiClient.chat({
      message: "I need to transfer $500 to John Smith and check my balance",
      context: {
        user_id: 'user_123',
        account_id: 'acct_primary',
        session_type: 'web',
        authentication_level: 'mfa_verified'
      },
      options: {
        enable_tools: true,
        confidence_threshold: 0.8,
        human_approval_required: true
      }
    });
    
    console.log('AI Response:', response.content);
    console.log('Confidence:', response.confidence);
    console.log('Tools Used:', response.tools_used);
    
    // Handle tool actions
    if (response.requires_action) {
      for (const action of response.actions) {
        console.log(`Action Required: ${action.type}`);
        
        switch(action.type) {
          case 'transfer':
            // Execute transfer with human approval
            await executeTransferWithApproval(action.parameters);
            break;
          case 'balance_check':
            // Get and display balance
            const balance = await getAccountBalance(action.parameters.account_id);
            console.log(`Current balance: ${balance}`);
            break;
        }
      }
    }
    
    // Provide feedback
    await aiClient.feedback({
      message_id: response.message_id,
      rating: 5,
      feedback: "Helpful and accurate"
    });
    
    return response;
  } catch (error) {
    console.error('AI conversation failed:', error);
    throw error;
  }
}

// Handle streaming responses for real-time interaction
async function streamingChat() {
  const stream = await aiClient.chatStream({
    message: "Explain my recent transactions and spending patterns",
    enable_analysis: true
  });
  
  for await (const chunk of stream) {
    if (chunk.type === 'content') {
      process.stdout.write(chunk.text);
    } else if (chunk.type === 'tool_call') {
      console.log('\n[Analyzing with:', chunk.tool_name, ']');
    } else if (chunk.type === 'complete') {
      console.log('\n\nAnalysis complete');
      console.log('Insights:', chunk.insights);
    }
  }
}
                                </x-code-block>
                            </div>
                            
                            <!-- Python -->
                            <div id="ai-chat-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
from finaegis_ai import FinAegisAI
import asyncio
import uuid

ai_client = FinAegisAI(
    api_key=os.environ['FINAEGIS_API_KEY'],
    conversation_id=f'conv_{uuid.uuid4().hex[:9]}'
)

async def banking_conversation():
    try:
        # Send a message to the AI agent
        response = await ai_client.chat(
            message="I need to transfer $500 to John Smith and check my balance",
            context={
                'user_id': 'user_123',
                'account_id': 'acct_primary',
                'session_type': 'web',
                'authentication_level': 'mfa_verified'
            },
            options={
                'enable_tools': True,
                'confidence_threshold': 0.8,
                'human_approval_required': True
            }
        )
        
        print(f'AI Response: {response.content}')
        print(f'Confidence: {response.confidence}')
        print(f'Tools Used: {response.tools_used}')
        
        # Handle tool actions
        if response.requires_action:
            for action in response.actions:
                print(f'Action Required: {action.type}')
                
                if action.type == 'transfer':
                    # Execute transfer with human approval
                    await execute_transfer_with_approval(action.parameters)
                elif action.type == 'balance_check':
                    # Get and display balance
                    balance = await get_account_balance(action.parameters['account_id'])
                    print(f'Current balance: {balance}')
        
        # Provide feedback
        await ai_client.feedback(
            message_id=response.message_id,
            rating=5,
            feedback="Helpful and accurate"
        )
        
        return response
    except Exception as error:
        print(f'AI conversation failed: {error}')
        raise

# Handle streaming responses
async def streaming_chat():
    stream = await ai_client.chat_stream(
        message="Explain my recent transactions and spending patterns",
        enable_analysis=True
    )
    
    async for chunk in stream:
        if chunk.type == 'content':
            print(chunk.text, end='', flush=True)
        elif chunk.type == 'tool_call':
            print(f'\n[Analyzing with: {chunk.tool_name}]')
        elif chunk.type == 'complete':
            print('\n\nAnalysis complete')
            print(f'Insights: {chunk.insights}')

# Run the conversation
asyncio.run(banking_conversation())
                                </x-code-block>
                            </div>
                            
                            <!-- Response -->
                            <div id="ai-chat-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "message_id": "msg_abc123xyz",
  "conversation_id": "conv_def456",
  "content": "I'll help you transfer $500 to John Smith and check your balance. Let me process this for you.",
  "confidence": 0.92,
  "requires_action": true,
  "actions": [
    {
      "type": "transfer",
      "description": "Transfer $500 to John Smith",
      "parameters": {
        "amount": "500.00",
        "currency": "USD",
        "recipient": "John Smith",
        "recipient_account": "acct_john_smith_789",
        "reference": "Transfer to John Smith",
        "requires_approval": true
      },
      "confidence": 0.89
    },
    {
      "type": "balance_check",
      "description": "Check account balance",
      "parameters": {
        "account_id": "acct_primary",
        "include_pending": true
      },
      "confidence": 0.95
    }
  ],
  "tools_used": [
    "RecipientLookupTool",
    "TransferValidationTool",
    "AccountBalanceTool",
    "FraudDetectionTool"
  ],
  "context_retained": {
    "user_intent": "transfer_and_balance",
    "entities": ["John Smith", "$500", "balance"],
    "sentiment": "neutral",
    "urgency": "normal"
  },
  "metadata": {
    "processing_time_ms": 342,
    "model_version": "finaegis-ai-v2.5",
    "mcp_tools_available": 12,
    "workflow_id": "wf_banking_assist_789"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>

                    <!-- AI Workflow Orchestration Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">AI-Driven Workflow Orchestration</h3>
                                    <p class="text-gray-600 mt-1">Complex multi-step operations with AI decision making</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">Workflow</span>
                                    <span class="px-3 py-1 bg-pink-100 text-pink-700 rounded-full text-xs font-medium">AI</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <x-code-block language="javascript" title="AI Customer Service Workflow">
// AI-powered customer service workflow
async function handleCustomerRequest(customerId, request) {
  const workflow = await aiClient.createWorkflow({
    type: 'customer_service',
    customer_id: customerId,
    initial_request: request,
    
    // AI configuration
    ai_config: {
      enable_sentiment_analysis: true,
      enable_intent_detection: true,
      enable_fraud_detection: true,
      confidence_threshold: 0.85
    },
    
    // Workflow steps with AI integration
    steps: [
      {
        name: 'analyze_request',
        type: 'ai_analysis',
        config: {
          detect_urgency: true,
          extract_entities: true,
          classify_intent: true
        }
      },
      {
        name: 'route_request',
        type: 'ai_routing',
        config: {
          routing_rules: 'dynamic',
          consider_agent_availability: true,
          skill_matching: true
        }
      },
      {
        name: 'process_request',
        type: 'ai_processing',
        config: {
          enable_tools: true,
          max_iterations: 5,
          human_in_loop: 'conditional'
        }
      },
      {
        name: 'quality_check',
        type: 'ai_validation',
        config: {
          check_compliance: true,
          verify_accuracy: true,
          sentiment_threshold: 0.7
        }
      }
    ],
    
    // Human-in-the-loop configuration
    human_intervention: {
      triggers: [
        { condition: 'confidence < 0.7', action: 'escalate_to_human' },
        { condition: 'fraud_score > 0.8', action: 'security_review' },
        { condition: 'amount > 10000', action: 'manual_approval' }
      ]
    }
  });
  
  // Monitor workflow progress
  workflow.on('step_complete', (step) => {
    console.log(`Step ${step.name} completed:`, step.result);
    
    if (step.ai_insights) {
      console.log('AI Insights:', step.ai_insights);
    }
  });
  
  workflow.on('human_intervention_required', async (intervention) => {
    console.log('Human intervention needed:', intervention.reason);
    
    // Get human decision
    const decision = await requestHumanDecision(intervention);
    workflow.resume(decision);
  });
  
  // Wait for completion
  const result = await workflow.execute();
  
  return {
    workflow_id: workflow.id,
    status: result.status,
    resolution: result.resolution,
    ai_summary: result.ai_summary,
    customer_satisfaction_prediction: result.satisfaction_score
  };
}

// Example usage
handleCustomerRequest('cust_123', 
  "I noticed unusual charges on my account and need help understanding them"
);
                            </x-code-block>
                        </div>
                    </div>
                    
                    <!-- Live Demo Link -->
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-8 text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Try the AI Agent Live</h3>
                        <p class="text-gray-600 mb-6">Experience our banking AI assistant in action with a live demo</p>
                        <a href="{{ route('demo.ai-agent') }}" class="inline-flex items-center justify-center px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all transform hover:scale-105 shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Launch Live Demo
                        </a>
                    </div>
                </div>
            </div>

            <!-- MCP Tools -->
            <div id="mcp-tools" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">MCP Tools Integration</h2>
                
                <div class="space-y-12">
                    <!-- MCP Tool Registration -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">MCP Tool Registration and Usage</h3>
                                    <p class="text-gray-600 mt-1">Register custom banking tools for AI agents to use</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">MCP</span>
                                    <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">Tools</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'mcp-register-js')" class="code-tab active">Tool Registration</button>
                                <button onclick="switchTab(event, 'mcp-usage-js')" class="code-tab">Tool Usage</button>
                                <button onclick="switchTab(event, 'mcp-manifest')" class="code-tab">Manifest</button>
                            </div>
                            
                            <!-- Tool Registration -->
                            <div id="mcp-register-js" class="tab-content active animate-fade-in">
                                <x-code-block language="javascript">
// Register custom MCP tools for banking operations
import { MCPServer } from '@finaegis/mcp-sdk';

const mcpServer = new MCPServer({
  name: 'banking-tools',
  version: '1.0.0',
  description: 'Custom banking tools for AI agents'
});

// Register Account Balance Tool
mcpServer.registerTool({
  name: 'get_account_balance',
  description: 'Retrieve the current balance for a customer account',
  parameters: {
    type: 'object',
    properties: {
      account_id: {
        type: 'string',
        description: 'The account identifier'
      },
      include_pending: {
        type: 'boolean',
        description: 'Include pending transactions in balance',
        default: false
      },
      currency: {
        type: 'string',
        description: 'Currency code for balance display',
        default: 'USD'
      }
    },
    required: ['account_id']
  },
  handler: async (params) => {
    // Tool implementation
    const balance = await getAccountBalance(params.account_id);
    
    if (params.include_pending) {
      const pending = await getPendingTransactions(params.account_id);
      balance.pending = pending.reduce((sum, tx) => sum + tx.amount, 0);
    }
    
    return {
      account_id: params.account_id,
      available_balance: balance.available,
      current_balance: balance.current,
      pending_balance: balance.pending || 0,
      currency: params.currency,
      as_of: new Date().toISOString()
    };
  }
});

// Register Transfer Authorization Tool
mcpServer.registerTool({
  name: 'authorize_transfer',
  description: 'Authorize and initiate a money transfer between accounts',
  parameters: {
    type: 'object',
    properties: {
      from_account: { type: 'string', description: 'Source account ID' },
      to_account: { type: 'string', description: 'Destination account ID' },
      amount: { type: 'number', description: 'Transfer amount' },
      currency: { type: 'string', description: 'Currency code' },
      reference: { type: 'string', description: 'Transfer reference' },
      require_2fa: { type: 'boolean', default: true }
    },
    required: ['from_account', 'to_account', 'amount', 'currency']
  },
  handler: async (params) => {
    // Validate transfer
    const validation = await validateTransfer(params);
    
    if (!validation.is_valid) {
      return {
        success: false,
        error: validation.error,
        error_code: validation.error_code
      };
    }
    
    // Check 2FA if required
    if (params.require_2fa) {
      const twoFaStatus = await check2FA(params.from_account);
      if (!twoFaStatus.verified) {
        return {
          success: false,
          error: '2FA verification required',
          action_required: 'verify_2fa',
          verification_url: twoFaStatus.verification_url
        };
      }
    }
    
    // Execute transfer
    const transfer = await executeTransfer(params);
    
    return {
      success: true,
      transfer_id: transfer.id,
      status: transfer.status,
      estimated_completion: transfer.estimated_completion,
      fee: transfer.fee,
      exchange_rate: transfer.exchange_rate
    };
  }
});

// Register Fraud Detection Tool
mcpServer.registerTool({
  name: 'check_fraud_risk',
  description: 'Analyze transaction or activity for fraud risk',
  parameters: {
    type: 'object',
    properties: {
      transaction_data: {
        type: 'object',
        description: 'Transaction details to analyze'
      },
      customer_id: {
        type: 'string',
        description: 'Customer identifier'
      },
      check_type: {
        type: 'string',
        enum: ['transaction', 'login', 'account_change'],
        description: 'Type of fraud check'
      }
    },
    required: ['transaction_data', 'customer_id', 'check_type']
  },
  handler: async (params) => {
    const riskAnalysis = await analyzeFraudRisk(params);
    
    return {
      risk_score: riskAnalysis.score,
      risk_level: riskAnalysis.level, // low, medium, high, critical
      flags: riskAnalysis.flags,
      recommended_action: riskAnalysis.recommended_action,
      similar_patterns: riskAnalysis.similar_patterns,
      ml_confidence: riskAnalysis.confidence
    };
  }
});

// Start MCP server
mcpServer.start({
  port: 3001,
  enableLogging: true,
  rateLimiting: {
    enabled: true,
    maxRequests: 100,
    windowMs: 60000
  }
});

console.log('MCP Banking Tools Server started on port 3001');
                                </x-code-block>
                            </div>
                            
                            <!-- Tool Usage -->
                            <div id="mcp-usage-js" class="tab-content animate-fade-in">
                                <x-code-block language="javascript">
// Using MCP tools in AI conversations
const aiClient = new FinAegisAI({
  apiKey: process.env.FINAEGIS_API_KEY,
  mcp_servers: [
    {
      url: 'http://localhost:3001',
      name: 'banking-tools',
      api_key: process.env.MCP_TOOLS_KEY
    }
  ]
});

// AI agent automatically discovers and uses available tools
async function intelligentBankingAssistant() {
  // List available tools
  const tools = await aiClient.getAvailableTools();
  console.log('Available MCP Tools:', tools.map(t => t.name));
  
  // Send a message that requires tool usage
  const response = await aiClient.chat({
    message: "Check my savings account balance and transfer $200 to checking",
    enable_tools: true,
    tool_preferences: {
      preferred_tools: ['get_account_balance', 'authorize_transfer'],
      auto_execute: false, // Require confirmation
      parallel_execution: true
    }
  });
  
  // AI automatically selects and uses appropriate tools
  console.log('AI Plan:', response.execution_plan);
  
  // Example execution plan:
  // 1. Use 'get_account_balance' for savings account
  // 2. Use 'get_account_balance' for checking account
  // 3. Validate sufficient funds
  // 4. Use 'authorize_transfer' to move money
  
  // Review tool calls before execution
  for (const toolCall of response.tool_calls) {
    console.log(`Tool: ${toolCall.tool_name}`);
    console.log(`Parameters:`, toolCall.parameters);
    console.log(`Reason: ${toolCall.reasoning}`);
    
    // Approve or modify tool call
    const approved = await confirmToolExecution(toolCall);
    
    if (approved) {
      const result = await aiClient.executeTool(toolCall);
      console.log(`Result:`, result);
    }
  }
  
  return response;
}

// Advanced: Custom tool selection logic
async function customToolStrategy() {
  const response = await aiClient.chat({
    message: "Analyze my spending and suggest budget improvements",
    tool_strategy: {
      // Custom tool selection function
      selector: async (availableTools, context) => {
        const relevantTools = [];
        
        // Select tools based on context
        if (context.intent.includes('spending')) {
          relevantTools.push('get_transaction_history');
          relevantTools.push('analyze_spending_patterns');
        }
        
        if (context.intent.includes('budget')) {
          relevantTools.push('create_budget_plan');
          relevantTools.push('spending_insights');
        }
        
        return relevantTools;
      },
      
      // Tool execution order
      execution_order: 'dependency_based', // or 'parallel', 'sequential'
      
      // Result aggregation
      aggregation: 'smart_summary' // or 'raw', 'structured'
    }
  });
  
  return response;
}

// Monitor tool performance
aiClient.on('tool_executed', (event) => {
  console.log(`Tool ${event.tool_name} executed in ${event.duration_ms}ms`);
  console.log(`Success: ${event.success}`);
  
  // Track tool usage metrics
  trackToolMetrics({
    tool: event.tool_name,
    duration: event.duration_ms,
    success: event.success,
    timestamp: event.timestamp
  });
});
                                </x-code-block>
                            </div>
                            
                            <!-- Manifest -->
                            <div id="mcp-manifest" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "name": "finaegis-banking-tools",
  "version": "1.0.0",
  "description": "MCP tools for FinAegis banking operations",
  "author": "FinAegis",
  "license": "MIT",
  "server": {
    "command": "node",
    "args": ["./mcp-server.js"],
    "env": {
      "NODE_ENV": "production",
      "MCP_PORT": "3001"
    }
  },
  "tools": [
    {
      "name": "get_account_balance",
      "description": "Retrieve account balance with pending transactions",
      "category": "account_management",
      "rate_limit": 100,
      "cache_ttl": 60
    },
    {
      "name": "authorize_transfer",
      "description": "Authorize money transfers between accounts",
      "category": "transactions",
      "requires_auth": true,
      "requires_2fa": true,
      "rate_limit": 20
    },
    {
      "name": "check_fraud_risk",
      "description": "Analyze activities for fraud risk",
      "category": "security",
      "ml_enabled": true,
      "real_time": true
    },
    {
      "name": "get_transaction_history",
      "description": "Retrieve transaction history with filtering",
      "category": "account_management",
      "pagination": true,
      "max_results": 1000
    },
    {
      "name": "analyze_spending_patterns",
      "description": "AI-powered spending analysis",
      "category": "insights",
      "ml_model": "spending-analyzer-v2"
    },
    {
      "name": "create_budget_plan",
      "description": "Generate personalized budget recommendations",
      "category": "financial_planning",
      "personalization": true
    }
  ],
  "capabilities": {
    "parallel_execution": true,
    "batch_operations": true,
    "streaming": true,
    "webhooks": true,
    "rate_limiting": {
      "enabled": true,
      "default_limit": 100,
      "window": "1m"
    }
  },
  "security": {
    "authentication": "api_key",
    "encryption": "tls_1_3",
    "audit_logging": true,
    "pii_masking": true
  },
  "monitoring": {
    "metrics_endpoint": "/metrics",
    "health_endpoint": "/health",
    "logging_level": "info"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>

                    <!-- MCP Workflow Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-orange-50 to-red-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Complex MCP Workflow Integration</h3>
                                    <p class="text-gray-600 mt-1">Multi-tool orchestration for complex banking operations</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">Advanced</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <x-code-block language="javascript" title="Loan Application Workflow with MCP">
// Complex loan application workflow using multiple MCP tools
async function processLoanApplication(customerId, loanDetails) {
  const workflow = await aiClient.createWorkflow({
    type: 'loan_application',
    mcp_enabled: true,
    
    stages: [
      {
        name: 'eligibility_check',
        tools: [
          'get_credit_score',
          'verify_income',
          'check_debt_ratio',
          'analyze_bank_statements'
        ],
        parallel: true,
        timeout: 30000
      },
      {
        name: 'risk_assessment',
        tools: [
          'calculate_risk_score',
          'check_fraud_history',
          'analyze_collateral',
          'market_analysis'
        ],
        ai_decision: {
          model: 'risk-assessment-v3',
          confidence_required: 0.85
        }
      },
      {
        name: 'offer_generation',
        tools: [
          'calculate_interest_rate',
          'generate_loan_terms',
          'prepare_documentation'
        ],
        human_review: {
          required_if: 'loan_amount > 100000',
          department: 'credit_risk'
        }
      },
      {
        name: 'approval_process',
        tools: [
          'final_verification',
          'compliance_check',
          'generate_contract',
          'setup_disbursement'
        ],
        sequential: true
      }
    ],
    
    compensation_strategy: {
      enabled: true,
      checkpoints: ['eligibility_check', 'risk_assessment'],
      rollback_actions: {
        'approval_process': ['cancel_contract', 'release_funds_hold']
      }
    }
  });
  
  // Execute workflow with progress monitoring
  workflow.on('stage_start', (stage) => {
    console.log(`Starting stage: ${stage.name}`);
  });
  
  workflow.on('tool_execution', (tool) => {
    console.log(`Executing MCP tool: ${tool.name}`);
    console.log(`Input parameters:`, tool.parameters);
  });
  
  workflow.on('ai_decision', (decision) => {
    console.log(`AI Decision: ${decision.outcome}`);
    console.log(`Confidence: ${decision.confidence}`);
    console.log(`Reasoning:`, decision.explanation);
  });
  
  workflow.on('human_review_required', async (review) => {
    console.log(`Human review needed: ${review.reason}`);
    const decision = await requestHumanReview(review);
    workflow.continueWithDecision(decision);
  });
  
  try {
    const result = await workflow.execute({
      customer_id: customerId,
      loan_details: loanDetails
    });
    
    console.log('Loan application processed successfully');
    console.log('Decision:', result.decision);
    console.log('Offer:', result.offer);
    
    // Send notification to customer
    await notifyCustomer(customerId, result);
    
    return result;
  } catch (error) {
    console.error('Workflow failed:', error);
    
    // Check if compensation was executed
    if (error.compensation_executed) {
      console.log('Rollback completed successfully');
    }
    
    throw error;
  }
}

// Usage
processLoanApplication('cust_456', {
  loan_type: 'personal',
  amount: 50000,
  term_months: 36,
  purpose: 'debt_consolidation'
});
                            </x-code-block>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Handling -->
            <div id="error-handling" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Error Handling</h2>
                
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h3 class="text-xl font-semibold text-gray-900">Robust Error Handling with Retries</h3>
                        <p class="text-gray-600 mt-1">Handle failures gracefully with automatic retries</p>
                    </div>
                    
                    <div class="p-6">
                        <x-code-block language="javascript" title="Complete Error Handling Implementation">
class FinAegisWrapper {
  constructor(apiKey, options = {}) {
    this.client = new FinAegis({
      apiKey,
      baseURL: options.baseURL || 'https://api.finaegis.org/v2'
    });
    
    this.retryOptions = {
      maxRetries: options.maxRetries || 3,
      backoffMultiplier: options.backoffMultiplier || 2,
      initialDelay: options.initialDelay || 1000
    };
  }
  
  async withRetry(operation, context = '') {
    let lastError;
    let delay = this.retryOptions.initialDelay;
    
    for (let attempt = 1; attempt <= this.retryOptions.maxRetries + 1; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error;
        
        console.log(`Attempt ${attempt} failed for ${context}:`, error.message);
        
        // Don't retry on client errors (4xx)
        if (error.status >= 400 && error.status < 500) {
          throw error;
        }
        
        // Don't retry on the last attempt
        if (attempt > this.retryOptions.maxRetries) {
          break;
        }
        
        // Wait before retrying
        console.log(`Retrying in ${delay}ms...`);
        await new Promise(resolve => setTimeout(resolve, delay));
        delay *= this.retryOptions.backoffMultiplier;
      }
    }
    
    throw lastError;
  }
  
  async createTransfer(transferData) {
    return this.withRetry(
      () => this.client.transfers.create(transferData),
      `transfer creation`
    );
  }
  
  async getAccountBalance(accountId) {
    return this.withRetry(
      () => this.client.accounts.getBalances(accountId),
      `balance query for ${accountId}`
    );
  }
  
  async executeWithFallback(primaryOperation, fallbackOperation, context) {
    try {
      return await this.withRetry(primaryOperation, context);
    } catch (primaryError) {
      console.log(`Primary operation failed, trying fallback:`, primaryError.message);
      
      try {
        return await this.withRetry(fallbackOperation, `${context} (fallback)`);
      } catch (fallbackError) {
        console.error('Both primary and fallback operations failed');
        throw new Error(`All operations failed. Primary: ${primaryError.message}, Fallback: ${fallbackError.message}`);
      }
    }
  }
}

// Usage example
const finAegis = new FinAegisWrapper(process.env.FINAEGIS_API_KEY, {
  environment: 'production',
  maxRetries: 3,
  backoffMultiplier: 2,
  initialDelay: 1000
});

async function robustTransfer(fromAccount, toAccount, amount) {
  try {
    // Primary: Direct transfer
    const primaryTransfer = () => finAegis.createTransfer({
      from_account: fromAccount,
      to_account: toAccount,
      amount: amount,
      asset_code: 'USD',
      workflow_enabled: true
    });
    
    // Fallback: Transfer via intermediate account
    const fallbackTransfer = () => finAegis.createTransfer({
      from_account: fromAccount,
      to_account: toAccount,
      amount: amount,
      asset_code: 'USD',
      workflow_enabled: true,
      routing: 'alternative'
    });
    
    const result = await finAegis.executeWithFallback(
      primaryTransfer,
      fallbackTransfer,
      'USD transfer'
    );
    
    console.log('Transfer successful:', result.uuid);
    return result;
    
  } catch (error) {
    console.error('Transfer completely failed:', error.message);
    
    // Log for monitoring
    await logTransferFailure({
      fromAccount,
      toAccount,
      amount,
      error: error.message,
      timestamp: new Date().toISOString()
    });
    
    throw error;
  }
}
                        </x-code-block>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 bg-gradient-to-br from-green-600 to-blue-600 text-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">Start Building Today</h2>
            <p class="text-xl text-green-100 mb-8">Use these examples as a starting point for your FinAegis integration.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-3 bg-white text-green-600 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
                    Get API Keys
                </a>
                <a href="{{ route('developers.show', 'api-docs') }}" class="inline-flex items-center justify-center px-8 py-3 bg-green-700 text-white rounded-lg font-semibold hover:bg-green-800 transition-all border border-green-500">
                    API Documentation
                </a>
            </div>
        </div>
    </section>

@endsection

@push('scripts')
<script>
    function copyCode(button) {
        const codeBlock = button.parentElement.querySelector('code');
        const text = codeBlock.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            const originalContent = button.innerHTML;
            button.innerHTML = '<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            
            setTimeout(() => {
                button.innerHTML = originalContent;
            }, 2000);
        });
    }
    
    function switchTab(event, tabId) {
        // Get the parent container
        const container = event.target.closest('.p-6') || event.target.closest('.p-8');
        
        // Hide all tab contents in this container
        const tabContents = container.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
            content.classList.add('animate-fade-in');
        });
        
        // Remove active class from all tabs in this container
        const tabs = container.querySelectorAll('.code-tab');
        tabs.forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show the selected tab content
        const selectedContent = container.querySelector('#' + tabId);
        if (selectedContent) {
            selectedContent.classList.add('active');
        }
        
        // Add active class to clicked tab
        event.target.classList.add('active');
    }
</script>
@endpush