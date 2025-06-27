<x-app-layout>
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
                        <div class="bg-gray-50 px-6 py-4 border-b">
                            <h3 class="text-xl font-semibold text-gray-900">Create Account and Get Balance</h3>
                            <p class="text-gray-600 mt-1">Set up a new account and check its balance</p>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">JavaScript/Node.js</h4>
                                    <x-code-block language="javascript">
import { FinAegis } from '@finaegis/sdk';

const client = new FinAegis({
  apiKey: process.env.FINAEGIS_API_KEY,
  environment: 'sandbox'
});

async function createAccountAndCheckBalance() {
  try {
    // Create a new account
    const account = await client.accounts.create({
      name: 'My Main Account',
      type: 'personal'
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
                                
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Python</h4>
                                    <x-code-block language="python">
from finaegis import FinAegis
import os

client = FinAegis(
    api_key=os.environ['FINAEGIS_API_KEY'],
    environment='sandbox'
)

def create_account_and_check_balance():
    try:
        # Create a new account
        account = client.accounts.create(
            name='My Main Account',
            type='personal'
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
                            </div>
                        </div>
                    </div>

                    <!-- Simple Transfer Example -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b">
                            <h3 class="text-xl font-semibold text-gray-900">Simple Transfer</h3>
                            <p class="text-gray-600 mt-1">Transfer funds between accounts</p>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">JavaScript/Node.js</h4>
                                    <x-code-block language="javascript">
async function transferFunds(fromAccount, toAccount, amount) {
  try {
    const transfer = await client.transfers.create({
      from_account: fromAccount,
      to_account: toAccount,
      amount: amount,
      asset_code: 'USD',
      reference: 'Payment for services',
      workflow_enabled: true
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
                                
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Response Example</h4>
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
      environment: options.environment || 'sandbox'
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

    @include('partials.footer')

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
    </script>
</x-app-layout>