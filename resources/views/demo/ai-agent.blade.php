@extends('layouts.public')

@section('title', 'AI Agent Demo - FinAegis')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">AI Agent Demo</h1>
            <p class="text-xl text-gray-600">Experience intelligent financial assistance powered by AI</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Demo Scenarios -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Try These Scenarios</h3>
                    <div class="space-y-3">
                        <button onclick="sendPredefinedMessage('What is my account balance?')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Account Balance</div>
                            <div class="text-sm text-gray-600">Check account balance</div>
                        </button>
                        <button onclick="sendPredefinedMessage('Show me my recent transactions')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Transaction History</div>
                            <div class="text-sm text-gray-600">View recent activity</div>
                        </button>
                        <button onclick="sendPredefinedMessage('I want to transfer money')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Transfer Money</div>
                            <div class="text-sm text-gray-600">Initiate a transfer</div>
                        </button>
                        <button onclick="sendPredefinedMessage('Analyze my spending patterns')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Spending Analysis</div>
                            <div class="text-sm text-gray-600">Get insights</div>
                        </button>
                        <button onclick="sendPredefinedMessage('What is the GCU exchange rate?')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Exchange Rates</div>
                            <div class="text-sm text-gray-600">Check GCU rates</div>
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
                    <h3 class="text-lg font-semibold mb-4">Demo Mode Active</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        This is a simulated demo environment. No real transactions will be processed.
                    </p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Simulated responses</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>No authentication required</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Safe to experiment</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Interface -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg h-[600px] flex flex-col">
                    <!-- Chat Header -->
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-4 rounded-t-lg">
                        <h2 class="text-xl font-semibold">AI Financial Assistant</h2>
                        <p class="text-sm opacity-90">Powered by FinAegis AI Framework</p>
                    </div>

                    <!-- Chat Messages -->
                    <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-4">
                        <div class="flex justify-start">
                            <div class="max-w-xs lg:max-w-md">
                                <div class="bg-gray-100 rounded-lg px-4 py-2">
                                    <p class="text-sm">Hello! I'm your AI financial assistant. How can I help you today?</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">AI Assistant</p>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Input -->
                    <div class="border-t px-6 py-4">
                        <div class="flex space-x-2">
                            <input 
                                type="text" 
                                id="chat-input" 
                                class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Type your message..."
                                onkeypress="if(event.key === 'Enter') sendMessage()"
                            >
                            <button 
                                onclick="sendMessage()"
                                class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition"
                            >
                                Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Demo responses for different queries
const demoResponses = {
    'balance': {
        text: 'Your current account balance is:\n\n• Main Account: $12,456.78\n• Savings Account: $45,231.50\n• GCU Wallet: 1,250 GCU\n\nTotal value: $58,938.28',
        confidence: 0.95,
        tools: ['AccountBalanceTool', 'ExchangeRateTool']
    },
    'transactions': {
        text: 'Here are your recent transactions:\n\n1. Amazon Purchase - $156.32 (Today)\n2. Transfer to John - $500.00 (Yesterday)\n3. Salary Credit - $5,000.00 (3 days ago)\n4. Utility Bill - $234.56 (5 days ago)\n\nWould you like to see more details?',
        confidence: 0.92,
        tools: ['TransactionHistoryTool']
    },
    'transfer': {
        text: 'I can help you transfer money. Please provide:\n\n• Recipient name or account\n• Amount to transfer\n• Currency (USD, EUR, GCU)\n\nFor example: "Transfer $100 to John Smith"',
        confidence: 0.88,
        tools: ['TransferTool', 'KycTool']
    },
    'spending': {
        text: 'Based on your transaction history:\n\n📊 Monthly Spending Analysis:\n• Shopping: $2,345 (35%)\n• Food & Dining: $1,234 (18%)\n• Transportation: $890 (13%)\n• Utilities: $567 (8%)\n• Entertainment: $456 (7%)\n• Other: $1,258 (19%)\n\n💡 Insight: Your shopping expenses increased 15% this month.',
        confidence: 0.87,
        tools: ['AnalyticsTool', 'TransactionHistoryTool']
    },
    'exchange': {
        text: 'Current GCU Exchange Rates:\n\n• 1 GCU = 1.00 USD\n• 1 GCU = 0.92 EUR\n• 1 GCU = 0.79 GBP\n• 1 GCU = 150.23 JPY\n\nThe GCU is stable with 0.1% daily volatility.',
        confidence: 0.94,
        tools: ['ExchangeRateTool', 'MarketDataTool']
    },
    'default': {
        text: 'I understand you\'re asking about "{query}". In a production environment, I would process this request using our AI models and banking tools. For this demo, try one of the predefined scenarios on the left.',
        confidence: 0.65,
        tools: []
    }
};

function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message
    addMessage(message, 'user');
    
    // Clear input
    input.value = '';
    
    // Simulate AI response
    setTimeout(() => {
        const response = getAIResponse(message);
        addMessage(response.text, 'assistant', response);
    }, 1000);
}

function sendPredefinedMessage(message) {
    document.getElementById('chat-input').value = message;
    sendMessage();
}

function addMessage(text, sender, metadata = {}) {
    const messagesDiv = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = sender === 'user' ? 'flex justify-end' : 'flex justify-start';
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'max-w-xs lg:max-w-md';
    
    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = sender === 'user' 
        ? 'bg-indigo-600 text-white rounded-lg px-4 py-2'
        : 'bg-gray-100 rounded-lg px-4 py-2';
    
    const textP = document.createElement('p');
    textP.className = 'text-sm whitespace-pre-wrap';
    textP.textContent = text;
    
    bubbleDiv.appendChild(textP);
    contentDiv.appendChild(bubbleDiv);
    
    // Add metadata
    const metaP = document.createElement('p');
    metaP.className = 'text-xs text-gray-500 mt-1';
    
    if (sender === 'user') {
        metaP.textContent = 'You';
    } else {
        let metaText = 'AI Assistant';
        if (metadata.confidence) {
            metaText += ` • Confidence: ${(metadata.confidence * 100).toFixed(0)}%`;
        }
        if (metadata.tools && metadata.tools.length > 0) {
            metaText += ` • Tools: ${metadata.tools.join(', ')}`;
        }
        metaP.textContent = metaText;
    }
    
    contentDiv.appendChild(metaP);
    messageDiv.appendChild(contentDiv);
    messagesDiv.appendChild(messageDiv);
    
    // Scroll to bottom
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function getAIResponse(query) {
    const lowerQuery = query.toLowerCase();
    
    if (lowerQuery.includes('balance')) {
        return demoResponses.balance;
    } else if (lowerQuery.includes('transaction') || lowerQuery.includes('recent')) {
        return demoResponses.transactions;
    } else if (lowerQuery.includes('transfer') || lowerQuery.includes('send')) {
        return demoResponses.transfer;
    } else if (lowerQuery.includes('spending') || lowerQuery.includes('analyze')) {
        return demoResponses.spending;
    } else if (lowerQuery.includes('exchange') || lowerQuery.includes('gcu') || lowerQuery.includes('rate')) {
        return demoResponses.exchange;
    } else {
        return {
            text: demoResponses.default.text.replace('{query}', query),
            confidence: demoResponses.default.confidence,
            tools: demoResponses.default.tools
        };
    }
}
</script>
@endsection