@extends('layouts.public')

@section('title', 'Code Examples - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Code Examples - ' . config('brand.name', 'Zelta') . '',
        'description' => 'Working examples and integration patterns for common use cases with the ' . config('brand.name', 'Zelta') . ' API.',
        'keywords' => config('brand.name', 'Zelta') . ' API examples, code samples, integration patterns, developer examples',
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
                    Working examples and integration patterns for common use cases with the {{ config('brand.name', 'Zelta') }} API.
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

                <a href="#crosschain-defi" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-cyan-200 transition-colors">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Cross-Chain &amp; DeFi</h3>
                    <p class="text-gray-600 text-sm">Bridge protocols, DEX swaps, and multi-chain operations</p>
                </a>

                <a href="#regtech-compliance" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-emerald-200 transition-colors">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">RegTech &amp; Compliance</h3>
                    <p class="text-gray-600 text-sm">Travel Rule, MiFID II, MiCA compliance checks</p>
                </a>

                <a href="#baas-partner" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-amber-200 transition-colors">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">BaaS &amp; Partner API</h3>
                    <p class="text-gray-600 text-sm">Partner onboarding and Banking-as-a-Service integration</p>
                </a>

                <a href="#ai-transactions" class="group bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 hover:transform hover:-translate-y-1">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center mb-4 group-hover:bg-violet-200 transition-colors">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">AI Transaction Query</h3>
                    <p class="text-gray-600 text-sm">Natural language transaction search and analysis</p>
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
import { {{ config('brand.name', 'Zelta') }} } from '@finaegis/sdk';

const client = new {{ config('brand.name', 'Zelta') }}({
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
from finaegis import {{ config('brand.name', 'Zelta') }}
import os

client = {{ config('brand.name', 'Zelta') }}(
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

use {{ config('brand.name', 'Zelta') }}\Client;

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
                            <p class="text-gray-600 mt-1">Handle real-time events from {{ config('brand.name', 'Zelta') }}</p>
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
import { {{ config('brand.name', 'Zelta') }}AI } from '@finaegis/ai-sdk';

const aiClient = new {{ config('brand.name', 'Zelta') }}AI({
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
from finaegis_ai import {{ config('brand.name', 'Zelta') }}AI
import asyncio
import uuid

ai_client = {{ config('brand.name', 'Zelta') }}AI(
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
const aiClient = new {{ config('brand.name', 'Zelta') }}AI({
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
  "description": "MCP tools for {{ config('brand.name', 'Zelta') }} banking operations",
  "author": "{{ config('brand.name', 'Zelta') }}",
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

            <!-- Cross-Chain & DeFi -->
            <div id="crosschain-defi" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Cross-Chain &amp; DeFi</h2>

                <div class="space-y-12">
                    <!-- Cross-Chain Bridge Quote + Initiate -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-cyan-50 to-blue-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Cross-Chain Bridge: Quote and Initiate</h3>
                                    <p class="text-gray-600 mt-1">Get a bridge quote across chains (Wormhole, LayerZero, Axelar) and initiate the transfer</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-cyan-100 text-cyan-700 rounded-full text-xs font-medium">v3.0</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">POST</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'bridge-curl')" class="code-tab active">cURL</button>
                                <button onclick="switchTab(event, 'bridge-js')" class="code-tab">JavaScript</button>
                                <button onclick="switchTab(event, 'bridge-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'bridge-response')" class="code-tab">Response</button>
                            </div>

                            <!-- cURL -->
                            <div id="bridge-curl" class="tab-content active animate-fade-in">
                                <x-code-block language="bash">
# Step 1: Get a bridge quote
curl -X POST https://api.finaegis.org/api/v1/crosschain/bridge/quote \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_chain": "ethereum",
    "destination_chain": "polygon",
    "token": "USDC",
    "amount": "1000.00",
    "preferred_bridge": "wormhole"
  }'

# Step 2: Initiate the bridge transfer using the quote_id
curl -X POST https://api.finaegis.org/api/v1/crosschain/bridge/initiate \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "quote_id": "quote_br_abc123def456",
    "sender_address": "0x1234...abcd",
    "recipient_address": "0x5678...efgh",
    "slippage_tolerance": 0.005,
    "deadline_minutes": 30
  }'
                                </x-code-block>
                            </div>

                            <!-- JavaScript -->
                            <div id="bridge-js" class="tab-content animate-fade-in">
                                <x-code-block language="javascript">
import { {{ config('brand.name', 'Zelta') }} } from '@finaegis/sdk';

const client = new {{ config('brand.name', 'Zelta') }}({
  apiKey: process.env.FINAEGIS_API_KEY,
  baseURL: 'https://api.finaegis.org'
});

async function bridgeTokens(sourceChain, destChain, token, amount) {
  try {
    // Step 1: Get a bridge quote comparing protocols
    const quote = await client.crosschain.bridge.quote({
      source_chain: sourceChain,
      destination_chain: destChain,
      token: token,
      amount: amount,
      preferred_bridge: 'wormhole' // or 'layerzero', 'axelar'
    });

    console.log('Bridge Quote:');
    console.log(`  Protocol: ${quote.data.bridge_protocol}`);
    console.log(`  Estimated output: ${quote.data.estimated_receive_amount} ${token}`);
    console.log(`  Fee: ${quote.data.fee_amount} ${quote.data.fee_currency}`);
    console.log(`  Estimated time: ${quote.data.estimated_duration_seconds}s`);

    // Step 2: Initiate the bridge transfer
    const transfer = await client.crosschain.bridge.initiate({
      quote_id: quote.data.quote_id,
      sender_address: '0x1234...abcd',
      recipient_address: '0x5678...efgh',
      slippage_tolerance: 0.005,
      deadline_minutes: 30
    });

    console.log('Bridge initiated:', transfer.data.bridge_tx_id);
    console.log('Status:', transfer.data.status);

    return transfer;
  } catch (error) {
    console.error('Bridge failed:', error.message);
    throw error;
  }
}

// Bridge 1000 USDC from Ethereum to Polygon
bridgeTokens('ethereum', 'polygon', 'USDC', '1000.00');
                                </x-code-block>
                            </div>

                            <!-- Python -->
                            <div id="bridge-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
from finaegis import {{ config('brand.name', 'Zelta') }}
import os

client = {{ config('brand.name', 'Zelta') }}(
    api_key=os.environ['FINAEGIS_API_KEY'],
    base_url='https://api.finaegis.org'
)

def bridge_tokens(source_chain, dest_chain, token, amount):
    try:
        # Step 1: Get a bridge quote
        quote = client.crosschain.bridge.quote(
            source_chain=source_chain,
            destination_chain=dest_chain,
            token=token,
            amount=amount,
            preferred_bridge='wormhole'
        )

        print(f'Bridge Quote:')
        print(f'  Protocol: {quote.data.bridge_protocol}')
        print(f'  Estimated output: {quote.data.estimated_receive_amount} {token}')
        print(f'  Fee: {quote.data.fee_amount} {quote.data.fee_currency}')
        print(f'  Estimated time: {quote.data.estimated_duration_seconds}s')

        # Step 2: Initiate the bridge transfer
        transfer = client.crosschain.bridge.initiate(
            quote_id=quote.data.quote_id,
            sender_address='0x1234...abcd',
            recipient_address='0x5678...efgh',
            slippage_tolerance=0.005,
            deadline_minutes=30
        )

        print(f'Bridge initiated: {transfer.data.bridge_tx_id}')
        print(f'Status: {transfer.data.status}')

        return transfer
    except Exception as error:
        print(f'Bridge failed: {error}')
        raise

# Bridge 1000 USDC from Ethereum to Polygon
bridge_tokens('ethereum', 'polygon', 'USDC', '1000.00')
                                </x-code-block>
                            </div>

                            <!-- Response -->
                            <div id="bridge-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
// POST /api/v1/crosschain/bridge/quote response
{
  "data": {
    "quote_id": "quote_br_abc123def456",
    "bridge_protocol": "wormhole",
    "source_chain": "ethereum",
    "destination_chain": "polygon",
    "token": "USDC",
    "input_amount": "1000.00",
    "estimated_receive_amount": "998.50",
    "fee_amount": "1.50",
    "fee_currency": "USDC",
    "exchange_rate": "1.0000",
    "estimated_duration_seconds": 180,
    "quote_expires_at": "2026-01-15T12:15:00Z",
    "alternative_quotes": [
      {
        "bridge_protocol": "layerzero",
        "estimated_receive_amount": "997.80",
        "fee_amount": "2.20",
        "estimated_duration_seconds": 120
      },
      {
        "bridge_protocol": "axelar",
        "estimated_receive_amount": "998.00",
        "fee_amount": "2.00",
        "estimated_duration_seconds": 240
      }
    ]
  }
}

// POST /api/v1/crosschain/bridge/initiate response
{
  "data": {
    "bridge_tx_id": "btx_xyz789012345",
    "quote_id": "quote_br_abc123def456",
    "status": "pending",
    "source_chain": "ethereum",
    "destination_chain": "polygon",
    "source_tx_hash": null,
    "destination_tx_hash": null,
    "sender_address": "0x1234...abcd",
    "recipient_address": "0x5678...efgh",
    "amount": "1000.00",
    "token": "USDC",
    "bridge_protocol": "wormhole",
    "created_at": "2026-01-15T12:05:30Z",
    "estimated_completion_at": "2026-01-15T12:08:30Z"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>

                    <!-- DeFi Swap Quote + Execute -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-teal-50 to-green-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">DeFi Swap: Quote and Execute</h3>
                                    <p class="text-gray-600 mt-1">Aggregate DEX quotes (Uniswap, Curve, SushiSwap) and execute optimal swaps</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-teal-100 text-teal-700 rounded-full text-xs font-medium">v3.0</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">POST</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'swap-curl')" class="code-tab active">cURL</button>
                                <button onclick="switchTab(event, 'swap-js')" class="code-tab">JavaScript</button>
                                <button onclick="switchTab(event, 'swap-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'swap-response')" class="code-tab">Response</button>
                            </div>

                            <!-- cURL -->
                            <div id="swap-curl" class="tab-content active animate-fade-in">
                                <x-code-block language="bash">
# Step 1: Get a swap quote with DEX aggregation
curl -X POST https://api.finaegis.org/api/v1/defi/swap/quote \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "chain": "ethereum",
    "token_in": "WETH",
    "token_out": "USDC",
    "amount_in": "2.5",
    "slippage_tolerance": 0.005,
    "dex_sources": ["uniswap_v3", "curve", "sushiswap"]
  }'

# Step 2: Execute the swap with the best route
curl -X POST https://api.finaegis.org/api/v1/defi/swap/execute \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "quote_id": "quote_sw_def456ghi789",
    "wallet_address": "0xABCD...1234",
    "max_gas_price_gwei": 50,
    "deadline_minutes": 20
  }'
                                </x-code-block>
                            </div>

                            <!-- JavaScript -->
                            <div id="swap-js" class="tab-content animate-fade-in">
                                <x-code-block language="javascript">
async function swapTokens(chain, tokenIn, tokenOut, amountIn) {
  try {
    // Step 1: Get aggregated swap quote from multiple DEXes
    const quote = await client.defi.swap.quote({
      chain: chain,
      token_in: tokenIn,
      token_out: tokenOut,
      amount_in: amountIn,
      slippage_tolerance: 0.005,
      dex_sources: ['uniswap_v3', 'curve', 'sushiswap']
    });

    console.log('Swap Quote:');
    console.log(`  Best DEX: ${quote.data.best_route.dex}`);
    console.log(`  Input: ${amountIn} ${tokenIn}`);
    console.log(`  Output: ${quote.data.estimated_output} ${tokenOut}`);
    console.log(`  Price impact: ${quote.data.price_impact_percent}%`);
    console.log(`  Gas estimate: ${quote.data.estimated_gas_usd} USD`);

    // Step 2: Execute the swap
    const execution = await client.defi.swap.execute({
      quote_id: quote.data.quote_id,
      wallet_address: '0xABCD...1234',
      max_gas_price_gwei: 50,
      deadline_minutes: 20
    });

    console.log('Swap executed:', execution.data.tx_hash);
    console.log('Status:', execution.data.status);

    return execution;
  } catch (error) {
    console.error('Swap failed:', error.message);
    throw error;
  }
}

// Swap 2.5 WETH for USDC on Ethereum
swapTokens('ethereum', 'WETH', 'USDC', '2.5');
                                </x-code-block>
                            </div>

                            <!-- Python -->
                            <div id="swap-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
def swap_tokens(chain, token_in, token_out, amount_in):
    try:
        # Step 1: Get aggregated swap quote
        quote = client.defi.swap.quote(
            chain=chain,
            token_in=token_in,
            token_out=token_out,
            amount_in=amount_in,
            slippage_tolerance=0.005,
            dex_sources=['uniswap_v3', 'curve', 'sushiswap']
        )

        print(f'Swap Quote:')
        print(f'  Best DEX: {quote.data.best_route.dex}')
        print(f'  Input: {amount_in} {token_in}')
        print(f'  Output: {quote.data.estimated_output} {token_out}')
        print(f'  Price impact: {quote.data.price_impact_percent}%')
        print(f'  Gas estimate: {quote.data.estimated_gas_usd} USD')

        # Step 2: Execute the swap
        execution = client.defi.swap.execute(
            quote_id=quote.data.quote_id,
            wallet_address='0xABCD...1234',
            max_gas_price_gwei=50,
            deadline_minutes=20
        )

        print(f'Swap executed: {execution.data.tx_hash}')
        print(f'Status: {execution.data.status}')

        return execution
    except Exception as error:
        print(f'Swap failed: {error}')
        raise

# Swap 2.5 WETH for USDC on Ethereum
swap_tokens('ethereum', 'WETH', 'USDC', '2.5')
                                </x-code-block>
                            </div>

                            <!-- Response -->
                            <div id="swap-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
// POST /api/v1/defi/swap/quote response
{
  "data": {
    "quote_id": "quote_sw_def456ghi789",
    "chain": "ethereum",
    "token_in": "WETH",
    "token_out": "USDC",
    "amount_in": "2.5",
    "estimated_output": "4875.32",
    "price_impact_percent": "0.12",
    "estimated_gas_usd": "8.45",
    "best_route": {
      "dex": "uniswap_v3",
      "path": ["WETH", "USDC"],
      "pools": ["0x8ad5...pool1"],
      "fee_tier": "0.05%"
    },
    "alternative_routes": [
      {
        "dex": "curve",
        "estimated_output": "4870.18",
        "price_impact_percent": "0.15"
      },
      {
        "dex": "sushiswap",
        "estimated_output": "4865.90",
        "price_impact_percent": "0.18"
      }
    ],
    "quote_expires_at": "2026-01-15T12:10:00Z"
  }
}

// POST /api/v1/defi/swap/execute response
{
  "data": {
    "swap_id": "swap_mno345pqr678",
    "quote_id": "quote_sw_def456ghi789",
    "status": "submitted",
    "tx_hash": "0x9f8e7d6c5b4a3...transaction_hash",
    "chain": "ethereum",
    "token_in": "WETH",
    "token_out": "USDC",
    "amount_in": "2.5",
    "expected_output": "4875.32",
    "dex": "uniswap_v3",
    "gas_price_gwei": "35",
    "created_at": "2026-01-15T12:06:00Z"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RegTech & Compliance -->
            <div id="regtech-compliance" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">RegTech &amp; Compliance</h2>

                <div class="space-y-12">
                    <!-- Travel Rule Compliance Check -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-50 to-green-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Travel Rule Compliance Check</h3>
                                    <p class="text-gray-600 mt-1">Validate FATF Travel Rule compliance before executing cross-border or virtual asset transfers</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium">v2.8</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">POST</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'travel-curl')" class="code-tab active">cURL</button>
                                <button onclick="switchTab(event, 'travel-js')" class="code-tab">JavaScript</button>
                                <button onclick="switchTab(event, 'travel-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'travel-response')" class="code-tab">Response</button>
                            </div>

                            <!-- cURL -->
                            <div id="travel-curl" class="tab-content active animate-fade-in">
                                <x-code-block language="bash">
# Run a Travel Rule compliance check before a transfer
curl -X POST https://api.finaegis.org/api/v1/regtech/travel-rule/check \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "transfer_id": "txfr_abc123def456",
    "originator": {
      "name": "Alice Johnson",
      "account_number": "acct_1234567890",
      "institution_name": "{{ config('brand.name', 'Zelta') }}",
      "institution_country": "US"
    },
    "beneficiary": {
      "name": "Bob Smith",
      "account_number": "ext_0987654321",
      "institution_name": "Partner Bank",
      "institution_country": "DE"
    },
    "transfer_details": {
      "amount": "15000.00",
      "currency": "USD",
      "asset_type": "fiat",
      "purpose": "business_payment"
    },
    "jurisdiction": "EU"
  }'
                                </x-code-block>
                            </div>

                            <!-- JavaScript -->
                            <div id="travel-js" class="tab-content animate-fade-in">
                                <x-code-block language="javascript">
async function checkTravelRuleCompliance(transferData) {
  try {
    const result = await client.regtech.travelRule.check({
      transfer_id: transferData.transferId,
      originator: {
        name: 'Alice Johnson',
        account_number: transferData.fromAccount,
        institution_name: config('brand.name', 'Zelta') . '',
        institution_country: 'US'
      },
      beneficiary: {
        name: transferData.recipientName,
        account_number: transferData.toAccount,
        institution_name: transferData.recipientInstitution,
        institution_country: transferData.recipientCountry
      },
      transfer_details: {
        amount: transferData.amount,
        currency: transferData.currency,
        asset_type: transferData.assetType || 'fiat',
        purpose: transferData.purpose || 'business_payment'
      },
      jurisdiction: transferData.jurisdiction || 'EU'
    });

    console.log('Travel Rule Check:');
    console.log(`  Compliant: ${result.data.is_compliant}`);
    console.log(`  Risk level: ${result.data.risk_level}`);
    console.log(`  Jurisdiction: ${result.data.applicable_jurisdiction}`);

    if (!result.data.is_compliant) {
      console.log('  Issues:');
      result.data.compliance_issues.forEach(issue => {
        console.log(`    - ${issue.code}: ${issue.description}`);
      });
    }

    return result;
  } catch (error) {
    console.error('Compliance check failed:', error.message);
    throw error;
  }
}

// Check compliance before a cross-border transfer
checkTravelRuleCompliance({
  transferId: 'txfr_abc123def456',
  fromAccount: 'acct_1234567890',
  recipientName: 'Bob Smith',
  toAccount: 'ext_0987654321',
  recipientInstitution: 'Partner Bank',
  recipientCountry: 'DE',
  amount: '15000.00',
  currency: 'USD',
  jurisdiction: 'EU'
});
                                </x-code-block>
                            </div>

                            <!-- Python -->
                            <div id="travel-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
def check_travel_rule_compliance(transfer_data):
    try:
        result = client.regtech.travel_rule.check(
            transfer_id=transfer_data['transfer_id'],
            originator={
                'name': 'Alice Johnson',
                'account_number': transfer_data['from_account'],
                'institution_name': config('brand.name', 'Zelta') . '',
                'institution_country': 'US'
            },
            beneficiary={
                'name': transfer_data['recipient_name'],
                'account_number': transfer_data['to_account'],
                'institution_name': transfer_data['recipient_institution'],
                'institution_country': transfer_data['recipient_country']
            },
            transfer_details={
                'amount': transfer_data['amount'],
                'currency': transfer_data['currency'],
                'asset_type': transfer_data.get('asset_type', 'fiat'),
                'purpose': transfer_data.get('purpose', 'business_payment')
            },
            jurisdiction=transfer_data.get('jurisdiction', 'EU')
        )

        print(f'Travel Rule Check:')
        print(f'  Compliant: {result.data.is_compliant}')
        print(f'  Risk level: {result.data.risk_level}')
        print(f'  Jurisdiction: {result.data.applicable_jurisdiction}')

        if not result.data.is_compliant:
            print('  Issues:')
            for issue in result.data.compliance_issues:
                print(f'    - {issue.code}: {issue.description}')

        return result
    except Exception as error:
        print(f'Compliance check failed: {error}')
        raise

# Check compliance before a cross-border transfer
check_travel_rule_compliance({
    'transfer_id': 'txfr_abc123def456',
    'from_account': 'acct_1234567890',
    'recipient_name': 'Bob Smith',
    'to_account': 'ext_0987654321',
    'recipient_institution': 'Partner Bank',
    'recipient_country': 'DE',
    'amount': '15000.00',
    'currency': 'USD',
    'jurisdiction': 'EU'
})
                                </x-code-block>
                            </div>

                            <!-- Response -->
                            <div id="travel-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "data": {
    "check_id": "trc_mno345pqr678",
    "transfer_id": "txfr_abc123def456",
    "is_compliant": true,
    "risk_level": "low",
    "applicable_jurisdiction": "EU",
    "applicable_regulations": [
      "FATF_Recommendation_16",
      "EU_Transfer_of_Funds_Regulation",
      "MiCA_Article_76"
    ],
    "originator_verified": true,
    "beneficiary_verified": true,
    "threshold_analysis": {
      "amount_usd": "15000.00",
      "exceeds_threshold": true,
      "threshold_amount": "1000.00",
      "full_identification_required": true
    },
    "sanctions_screening": {
      "originator_cleared": true,
      "beneficiary_cleared": true,
      "screening_provider": "internal",
      "screened_at": "2026-01-15T12:00:05Z"
    },
    "compliance_issues": [],
    "recommendations": [
      "Enhanced due diligence recommended for transfers above 10000 USD"
    ],
    "checked_at": "2026-01-15T12:00:05Z",
    "valid_until": "2026-01-15T12:30:05Z"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BaaS & Partner API -->
            <div id="baas-partner" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">BaaS &amp; Partner API</h2>

                <div class="space-y-12">
                    <!-- Partner Onboarding -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">BaaS Partner Onboarding</h3>
                                    <p class="text-gray-600 mt-1">Onboard a new partner organization onto the Banking-as-a-Service platform with API keys and SDK access</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-medium">v2.9</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">POST</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'partner-curl')" class="code-tab active">cURL</button>
                                <button onclick="switchTab(event, 'partner-js')" class="code-tab">JavaScript</button>
                                <button onclick="switchTab(event, 'partner-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'partner-response')" class="code-tab">Response</button>
                            </div>

                            <!-- cURL -->
                            <div id="partner-curl" class="tab-content active animate-fade-in">
                                <x-code-block language="bash">
# Onboard a new BaaS partner
curl -X POST https://api.finaegis.org/api/v1/partner/onboard \
  -H "Authorization: Bearer YOUR_ADMIN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "organization_name": "Acme Fintech",
    "contact_email": "integration@acmefintech.com",
    "tier": "growth",
    "modules": ["accounts", "transfers", "compliance", "crosschain"],
    "sdk_languages": ["typescript", "python"],
    "webhook_url": "https://acmefintech.com/webhooks/finaegis",
    "ip_whitelist": ["203.0.113.0/24"],
    "rate_limit_override": 5000,
    "metadata": {
      "use_case": "neobank",
      "expected_monthly_volume": "500000"
    }
  }'
                                </x-code-block>
                            </div>

                            <!-- JavaScript -->
                            <div id="partner-js" class="tab-content animate-fade-in">
                                <x-code-block language="javascript">
async function onboardPartner(partnerDetails) {
  try {
    const partner = await client.partner.onboard({
      organization_name: partnerDetails.name,
      contact_email: partnerDetails.email,
      tier: partnerDetails.tier || 'growth', // starter, growth, enterprise
      modules: partnerDetails.modules,
      sdk_languages: partnerDetails.sdkLanguages || ['typescript', 'python'],
      webhook_url: partnerDetails.webhookUrl,
      ip_whitelist: partnerDetails.ipWhitelist || [],
      rate_limit_override: partnerDetails.rateLimit,
      metadata: partnerDetails.metadata || {}
    });

    console.log('Partner Onboarded:');
    console.log(`  Partner ID: ${partner.data.partner_id}`);
    console.log(`  API Key: ${partner.data.api_credentials.api_key}`);
    console.log(`  Sandbox Key: ${partner.data.api_credentials.sandbox_key}`);
    console.log(`  Tier: ${partner.data.tier}`);
    console.log(`  SDK Download URLs:`);
    partner.data.sdk_packages.forEach(sdk => {
      console.log(`    ${sdk.language}: ${sdk.download_url}`);
    });

    return partner;
  } catch (error) {
    console.error('Partner onboarding failed:', error.message);
    throw error;
  }
}

// Onboard a new fintech partner
onboardPartner({
  name: 'Acme Fintech',
  email: 'integration@acmefintech.com',
  tier: 'growth',
  modules: ['accounts', 'transfers', 'compliance', 'crosschain'],
  sdkLanguages: ['typescript', 'python'],
  webhookUrl: 'https://acmefintech.com/webhooks/finaegis',
  ipWhitelist: ['203.0.113.0/24'],
  rateLimit: 5000,
  metadata: {
    use_case: 'neobank',
    expected_monthly_volume: '500000'
  }
});
                                </x-code-block>
                            </div>

                            <!-- Python -->
                            <div id="partner-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
def onboard_partner(partner_details):
    try:
        partner = client.partner.onboard(
            organization_name=partner_details['name'],
            contact_email=partner_details['email'],
            tier=partner_details.get('tier', 'growth'),
            modules=partner_details['modules'],
            sdk_languages=partner_details.get('sdk_languages', ['typescript', 'python']),
            webhook_url=partner_details['webhook_url'],
            ip_whitelist=partner_details.get('ip_whitelist', []),
            rate_limit_override=partner_details.get('rate_limit'),
            metadata=partner_details.get('metadata', {})
        )

        print(f'Partner Onboarded:')
        print(f'  Partner ID: {partner.data.partner_id}')
        print(f'  API Key: {partner.data.api_credentials.api_key}')
        print(f'  Sandbox Key: {partner.data.api_credentials.sandbox_key}')
        print(f'  Tier: {partner.data.tier}')
        print(f'  SDK Download URLs:')
        for sdk in partner.data.sdk_packages:
            print(f'    {sdk.language}: {sdk.download_url}')

        return partner
    except Exception as error:
        print(f'Partner onboarding failed: {error}')
        raise

# Onboard a new fintech partner
onboard_partner({
    'name': 'Acme Fintech',
    'email': 'integration@acmefintech.com',
    'tier': 'growth',
    'modules': ['accounts', 'transfers', 'compliance', 'crosschain'],
    'sdk_languages': ['typescript', 'python'],
    'webhook_url': 'https://acmefintech.com/webhooks/finaegis',
    'ip_whitelist': ['203.0.113.0/24'],
    'rate_limit': 5000,
    'metadata': {
        'use_case': 'neobank',
        'expected_monthly_volume': '500000'
    }
})
                                </x-code-block>
                            </div>

                            <!-- Response -->
                            <div id="partner-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "data": {
    "partner_id": "partner_acme_abc123",
    "organization_name": "Acme Fintech",
    "status": "active",
    "tier": "growth",
    "modules_enabled": [
      "accounts",
      "transfers",
      "compliance",
      "crosschain"
    ],
    "api_credentials": {
      "api_key": "pk_live_xxxxxxxxxxxxxxxxxxxxxxxx",
      "sandbox_key": "pk_test_xxxxxxxxxxxxxxxxxxxxxxxx",
      "webhook_secret": "whsec_xxxxxxxxxxxxxxxxxxxxxxxx"
    },
    "sdk_packages": [
      {
        "language": "typescript",
        "version": "3.0.0",
        "package_name": "@finaegis/sdk",
        "download_url": "https://sdk.finaegis.org/packages/typescript/finaegis-sdk-3.0.0.tgz",
        "docs_url": "https://docs.finaegis.org/sdk/typescript"
      },
      {
        "language": "python",
        "version": "3.0.0",
        "package_name": "finaegis-sdk",
        "download_url": "https://sdk.finaegis.org/packages/python/finaegis-sdk-3.0.0.tar.gz",
        "docs_url": "https://docs.finaegis.org/sdk/python"
      }
    ],
    "rate_limits": {
      "requests_per_minute": 5000,
      "burst_limit": 500
    },
    "sandbox_url": "https://sandbox.finaegis.org",
    "dashboard_url": "https://partners.finaegis.org/acme-fintech",
    "created_at": "2026-01-15T12:00:00Z",
    "onboarding_checklist": {
      "api_key_generated": true,
      "webhook_configured": true,
      "sdk_generated": true,
      "sandbox_tested": false,
      "compliance_review": "pending"
    }
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Transaction Query -->
            <div id="ai-transactions" class="mb-20">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">AI Transaction Query</h2>

                <div class="space-y-12">
                    <!-- AI Transaction Search -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-50 to-purple-50 px-6 py-5 border-b">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">AI-Powered Transaction Search</h3>
                                    <p class="text-gray-600 mt-1">Query transactions using natural language -- the AI engine translates to structured filters</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-violet-100 text-violet-700 rounded-full text-xs font-medium">v2.8</span>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">POST</span>
                                    <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">AI</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <!-- Tabs -->
                            <div class="code-tabs">
                                <button onclick="switchTab(event, 'aitx-curl')" class="code-tab active">cURL</button>
                                <button onclick="switchTab(event, 'aitx-js')" class="code-tab">JavaScript</button>
                                <button onclick="switchTab(event, 'aitx-py')" class="code-tab">Python</button>
                                <button onclick="switchTab(event, 'aitx-response')" class="code-tab">Response</button>
                            </div>

                            <!-- cURL -->
                            <div id="aitx-curl" class="tab-content active animate-fade-in">
                                <x-code-block language="bash">
# Query transactions with natural language
curl -X POST https://api.finaegis.org/api/v1/ai/transactions \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Show me all cross-chain transfers over $5000 in the last 7 days that had compliance flags",
    "account_id": "acct_1234567890",
    "options": {
      "include_analytics": true,
      "include_risk_scores": true,
      "max_results": 50,
      "format": "detailed"
    }
  }'
                                </x-code-block>
                            </div>

                            <!-- JavaScript -->
                            <div id="aitx-js" class="tab-content animate-fade-in">
                                <x-code-block language="javascript">
async function queryTransactionsWithAI(naturalLanguageQuery, accountId) {
  try {
    const result = await client.ai.transactions({
      query: naturalLanguageQuery,
      account_id: accountId,
      options: {
        include_analytics: true,
        include_risk_scores: true,
        max_results: 50,
        format: 'detailed'
      }
    });

    console.log('AI Query Interpretation:');
    console.log(`  Parsed filters: ${JSON.stringify(result.data.interpreted_query)}`);
    console.log(`  Confidence: ${result.data.interpretation_confidence}`);
    console.log(`  Results found: ${result.data.total_results}`);

    result.data.transactions.forEach(tx => {
      console.log(`  ${tx.created_at}: ${tx.type} ${tx.amount} ${tx.currency}`);
      console.log(`    Status: ${tx.status} | Risk: ${tx.risk_score}`);
      if (tx.compliance_flags.length > 0) {
        console.log(`    Flags: ${tx.compliance_flags.join(', ')}`);
      }
    });

    if (result.data.analytics) {
      console.log('\nAnalytics Summary:');
      console.log(`  Total volume: ${result.data.analytics.total_volume}`);
      console.log(`  Average amount: ${result.data.analytics.avg_amount}`);
      console.log(`  Flagged count: ${result.data.analytics.flagged_count}`);
    }

    return result;
  } catch (error) {
    console.error('AI query failed:', error.message);
    throw error;
  }
}

// Natural language transaction search
queryTransactionsWithAI(
  'Show me all cross-chain transfers over $5000 in the last 7 days that had compliance flags',
  'acct_1234567890'
);
                                </x-code-block>
                            </div>

                            <!-- Python -->
                            <div id="aitx-py" class="tab-content animate-fade-in">
                                <x-code-block language="python">
def query_transactions_with_ai(natural_language_query, account_id):
    try:
        result = client.ai.transactions(
            query=natural_language_query,
            account_id=account_id,
            options={
                'include_analytics': True,
                'include_risk_scores': True,
                'max_results': 50,
                'format': 'detailed'
            }
        )

        print(f'AI Query Interpretation:')
        print(f'  Parsed filters: {result.data.interpreted_query}')
        print(f'  Confidence: {result.data.interpretation_confidence}')
        print(f'  Results found: {result.data.total_results}')

        for tx in result.data.transactions:
            print(f'  {tx.created_at}: {tx.type} {tx.amount} {tx.currency}')
            print(f'    Status: {tx.status} | Risk: {tx.risk_score}')
            if tx.compliance_flags:
                print(f'    Flags: {", ".join(tx.compliance_flags)}')

        if result.data.analytics:
            print(f'\nAnalytics Summary:')
            print(f'  Total volume: {result.data.analytics.total_volume}')
            print(f'  Average amount: {result.data.analytics.avg_amount}')
            print(f'  Flagged count: {result.data.analytics.flagged_count}')

        return result
    except Exception as error:
        print(f'AI query failed: {error}')
        raise

# Natural language transaction search
query_transactions_with_ai(
    'Show me all cross-chain transfers over $5000 in the last 7 days that had compliance flags',
    'acct_1234567890'
)
                                </x-code-block>
                            </div>

                            <!-- Response -->
                            <div id="aitx-response" class="tab-content animate-fade-in">
                                <x-code-block language="json">
{
  "data": {
    "query_id": "aiq_stu901vwx234",
    "original_query": "Show me all cross-chain transfers over $5000 in the last 7 days that had compliance flags",
    "interpreted_query": {
      "type": ["cross_chain_transfer"],
      "amount_min": 5000,
      "currency": "USD",
      "date_range": {
        "from": "2026-01-08T00:00:00Z",
        "to": "2026-01-15T23:59:59Z"
      },
      "has_compliance_flags": true
    },
    "interpretation_confidence": 0.96,
    "total_results": 3,
    "transactions": [
      {
        "uuid": "tx_cross_001",
        "type": "cross_chain_transfer",
        "amount": "7500.00",
        "currency": "USDC",
        "status": "completed",
        "source_chain": "ethereum",
        "destination_chain": "arbitrum",
        "bridge_protocol": "wormhole",
        "risk_score": 0.35,
        "compliance_flags": ["high_value_transfer"],
        "created_at": "2026-01-12T14:30:00Z"
      },
      {
        "uuid": "tx_cross_002",
        "type": "cross_chain_transfer",
        "amount": "12000.00",
        "currency": "USDT",
        "status": "completed",
        "source_chain": "polygon",
        "destination_chain": "ethereum",
        "bridge_protocol": "layerzero",
        "risk_score": 0.62,
        "compliance_flags": ["high_value_transfer", "new_destination"],
        "created_at": "2026-01-10T09:15:00Z"
      },
      {
        "uuid": "tx_cross_003",
        "type": "cross_chain_transfer",
        "amount": "25000.00",
        "currency": "USDC",
        "status": "pending_review",
        "source_chain": "ethereum",
        "destination_chain": "bsc",
        "bridge_protocol": "axelar",
        "risk_score": 0.78,
        "compliance_flags": ["high_value_transfer", "jurisdiction_mismatch", "velocity_alert"],
        "created_at": "2026-01-09T16:45:00Z"
      }
    ],
    "analytics": {
      "total_volume": "44500.00",
      "avg_amount": "14833.33",
      "median_amount": "12000.00",
      "flagged_count": 3,
      "avg_risk_score": 0.58,
      "chains_involved": ["ethereum", "polygon", "arbitrum", "bsc"],
      "bridges_used": ["wormhole", "layerzero", "axelar"]
    }
  }
}
                                </x-code-block>
                            </div>
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
class {{ config('brand.name', 'Zelta') }}Wrapper {
  constructor(apiKey, options = {}) {
    this.client = new {{ config('brand.name', 'Zelta') }}({
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
const finAegis = new {{ config('brand.name', 'Zelta') }}Wrapper(process.env.FINAEGIS_API_KEY, {
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
            <p class="text-xl text-green-100 mb-8">Use these examples as a starting point for your {{ config('brand.name', 'Zelta') }} integration.</p>
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