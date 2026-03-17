@extends('layouts.public')

@section('title', 'AI Agent Demo - ' . config('brand.name', 'Zelta') . '')

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
                        <button onclick="sendPredefinedMessage('Get me a quote for BTC to USD')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Trading Quote</div>
                            <div class="text-sm text-gray-600">Get exchange rates</div>
                        </button>
                        <button onclick="sendPredefinedMessage('Check my KYC compliance status')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Compliance Status</div>
                            <div class="text-sm text-gray-600">KYC/AML status</div>
                        </button>
                        <button onclick="sendPredefinedMessage('What can you do?')" class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <div class="font-medium">Capabilities</div>
                            <div class="text-sm text-gray-600">See what I can do</div>
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
                            <span>Real agent routing &amp; tool execution</span>
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
                        <p class="text-sm opacity-90">Multi-agent orchestration powered by {{ config('brand.name', 'Zelta') }} AI Framework</p>
                    </div>

                    <!-- Chat Messages -->
                    <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-4">
                        <div class="flex justify-start">
                            <div class="max-w-xs lg:max-w-md">
                                <div class="bg-gray-100 rounded-lg px-4 py-2">
                                    <p class="text-sm">Hello! I'm your AI financial assistant. I route your queries to specialized agents — Financial, Trading, Compliance, and Transfer. How can I help you today?</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">General Assistant</p>
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
                                id="send-btn"
                                onclick="sendMessage()"
                                class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
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
let isSending = false;

function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();

    if (!message || isSending) return;

    addMessage(message, 'user');
    input.value = '';
    setLoading(true);

    fetch('/api/demo/ai-chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ message: message }),
    })
    .then(response => {
        if (response.status === 429) {
            throw new Error('Rate limit exceeded. Please wait a moment before trying again.');
        }
        if (!response.ok) {
            throw new Error('Server error. Please try again.');
        }
        return response.json();
    })
    .then(data => {
        addMessage(data.content, 'assistant', {
            confidence: data.confidence,
            tools: data.tools_used || [],
            agents: data.agents_used || [],
            responseTime: data.response_time_ms,
        });
    })
    .catch(error => {
        addMessage(error.message || 'Something went wrong. Please try again.', 'assistant', { error: true });
    })
    .finally(() => {
        setLoading(false);
    });
}

function sendPredefinedMessage(message) {
    document.getElementById('chat-input').value = message;
    sendMessage();
}

function setLoading(loading) {
    isSending = loading;
    const btn = document.getElementById('send-btn');
    const input = document.getElementById('chat-input');
    btn.disabled = loading;
    input.disabled = loading;

    if (loading) {
        addTypingIndicator();
    } else {
        removeTypingIndicator();
    }
}

function addTypingIndicator() {
    const messagesDiv = document.getElementById('chat-messages');
    const indicator = document.createElement('div');
    indicator.id = 'typing-indicator';
    indicator.className = 'flex justify-start';
    indicator.innerHTML = `
        <div class="max-w-xs lg:max-w-md">
            <div class="bg-gray-100 rounded-lg px-4 py-3">
                <div class="flex space-x-1">
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-1">AI is thinking...</p>
        </div>
    `;
    messagesDiv.appendChild(indicator);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) indicator.remove();
}

function addMessage(text, sender, metadata = {}) {
    const messagesDiv = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = sender === 'user' ? 'flex justify-end' : 'flex justify-start';

    const contentDiv = document.createElement('div');
    contentDiv.className = 'max-w-xs lg:max-w-md';

    const bubbleDiv = document.createElement('div');
    if (sender === 'user') {
        bubbleDiv.className = 'bg-indigo-600 text-white rounded-lg px-4 py-2';
    } else if (metadata.error) {
        bubbleDiv.className = 'bg-red-50 border border-red-200 rounded-lg px-4 py-2';
    } else {
        bubbleDiv.className = 'bg-gray-100 rounded-lg px-4 py-2';
    }

    // Render markdown-style bold and line breaks
    const formattedText = text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');

    const textP = document.createElement('div');
    textP.className = 'text-sm';
    textP.innerHTML = formattedText;

    bubbleDiv.appendChild(textP);
    contentDiv.appendChild(bubbleDiv);

    // Metadata line
    const metaP = document.createElement('p');
    metaP.className = 'text-xs text-gray-500 mt-1';

    if (sender === 'user') {
        metaP.textContent = 'You';
    } else {
        let metaText = (metadata.agents && metadata.agents.length > 0)
            ? metadata.agents.join(', ')
            : 'AI Assistant';

        if (metadata.confidence) {
            metaText += ` · ${(metadata.confidence * 100).toFixed(0)}% confidence`;
        }
        if (metadata.responseTime) {
            metaText += ` · ${metadata.responseTime}ms`;
        }
        metaP.textContent = metaText;
    }

    contentDiv.appendChild(metaP);

    // Tool chips
    if (metadata.tools && metadata.tools.length > 0) {
        const chipDiv = document.createElement('div');
        chipDiv.className = 'flex flex-wrap gap-1 mt-1';
        metadata.tools.forEach(tool => {
            const chip = document.createElement('span');
            chip.className = 'inline-block text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full';
            chip.textContent = tool;
            chipDiv.appendChild(chip);
        });
        contentDiv.appendChild(chipDiv);
    }

    messageDiv.appendChild(contentDiv);
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}
</script>
@endsection
