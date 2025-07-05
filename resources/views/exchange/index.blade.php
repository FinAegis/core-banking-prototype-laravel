@extends('layouts.app')

@section('title', 'FinAegis Exchange - Trade Digital Assets')

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <!-- Market Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $baseCurrency }}/{{ $quoteCurrency }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">FinAegis Exchange</p>
                </div>
                
                <div class="flex items-center gap-8">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Last Price</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $orderBook['last_price'] ? number_format($orderBook['last_price'], 2) : 'N/A' }} {{ $quoteCurrency }}
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">24h Change</p>
                        <p class="text-lg font-semibold {{ ($orderBook['change_24h_percentage'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $orderBook['change_24h_percentage'] ? number_format($orderBook['change_24h_percentage'], 2) : '0.00' }}%
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">24h Volume</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($orderBook['volume_24h'] ?? 0, 2) }} {{ $baseCurrency }}
                        </p>
                    </div>
                    
                    @auth
                    <div>
                        <a href="{{ route('exchange.external.index') }}" 
                           class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            External Exchanges
                        </a>
                    </div>
                    @endauth
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Order Book -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Order Book</h2>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Buy Orders -->
                        <div>
                            <h3 class="text-sm font-medium text-green-600 mb-2">Buy Orders</h3>
                            <div class="space-y-1">
                                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-400 pb-2 border-b">
                                    <div>Price ({{ $quoteCurrency }})</div>
                                    <div class="text-right">Amount ({{ $baseCurrency }})</div>
                                </div>
                                @forelse($orderBook['bids'] ?? [] as $bid)
                                    <div class="grid grid-cols-2 gap-2 text-sm hover:bg-green-50 dark:hover:bg-green-900/20 px-1 py-0.5 rounded">
                                        <div class="text-green-600">{{ number_format($bid['price'], 2) }}</div>
                                        <div class="text-right text-gray-700 dark:text-gray-300">{{ number_format($bid['amount'], 8) }}</div>
                                    </div>
                                @empty
                                    <p class="text-gray-500 text-sm">No buy orders</p>
                                @endforelse
                            </div>
                        </div>
                        
                        <!-- Sell Orders -->
                        <div>
                            <h3 class="text-sm font-medium text-red-600 mb-2">Sell Orders</h3>
                            <div class="space-y-1">
                                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-400 pb-2 border-b">
                                    <div>Price ({{ $quoteCurrency }})</div>
                                    <div class="text-right">Amount ({{ $baseCurrency }})</div>
                                </div>
                                @forelse($orderBook['asks'] ?? [] as $ask)
                                    <div class="grid grid-cols-2 gap-2 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 px-1 py-0.5 rounded">
                                        <div class="text-red-600">{{ number_format($ask['price'], 2) }}</div>
                                        <div class="text-right text-gray-700 dark:text-gray-300">{{ number_format($ask['amount'], 8) }}</div>
                                    </div>
                                @empty
                                    <p class="text-gray-500 text-sm">No sell orders</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    
                    @if($orderBook['spread'])
                        <div class="mt-4 pt-4 border-t text-center">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Spread: {{ number_format($orderBook['spread'], 2) }} {{ $quoteCurrency }} 
                                ({{ number_format($orderBook['spread_percentage'] ?? 0, 2) }}%)
                            </p>
                        </div>
                    @endif
                </div>
                
                <!-- Recent Trades -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mt-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Recent Trades</h2>
                    
                    <div class="space-y-1">
                        <div class="grid grid-cols-4 gap-2 text-xs text-gray-600 dark:text-gray-400 pb-2 border-b">
                            <div>Price</div>
                            <div>Amount</div>
                            <div>Total</div>
                            <div class="text-right">Time</div>
                        </div>
                        @forelse($recentTrades as $trade)
                            <div class="grid grid-cols-4 gap-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 px-1 py-0.5 rounded">
                                <div class="{{ $trade->maker_side === 'buy' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($trade->price, 2) }}
                                </div>
                                <div class="text-gray-700 dark:text-gray-300">{{ number_format($trade->amount, 8) }}</div>
                                <div class="text-gray-700 dark:text-gray-300">{{ number_format($trade->value, 2) }}</div>
                                <div class="text-right text-gray-500">{{ $trade->created_at->format('H:i:s') }}</div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm py-4">No recent trades</p>
                        @endforelse
                    </div>
                </div>
            </div>
            
            <!-- Trading Panel -->
            <div>
                @auth
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Place Order</h2>
                        
                        <form method="POST" action="{{ route('exchange.place-order') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="base_currency" value="{{ $baseCurrency }}">
                            <input type="hidden" name="quote_currency" value="{{ $quoteCurrency }}">
                            
                            <!-- Order Type Tabs -->
                            <div class="flex gap-2 mb-4">
                                <button type="button" 
                                        onclick="setOrderType('buy')"
                                        class="order-type-btn flex-1 py-2 px-4 rounded font-medium transition-colors"
                                        data-type="buy">
                                    Buy {{ $baseCurrency }}
                                </button>
                                <button type="button" 
                                        onclick="setOrderType('sell')"
                                        class="order-type-btn flex-1 py-2 px-4 rounded font-medium transition-colors"
                                        data-type="sell">
                                    Sell {{ $baseCurrency }}
                                </button>
                            </div>
                            <input type="hidden" name="type" id="order-type" value="buy">
                            
                            <!-- Market/Limit Toggle -->
                            <div class="flex gap-2">
                                <label class="flex items-center">
                                    <input type="radio" name="order_type" value="market" checked
                                           onchange="togglePriceInput()"
                                           class="mr-2">
                                    <span class="text-sm">Market Order</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="order_type" value="limit"
                                           onchange="togglePriceInput()"
                                           class="mr-2">
                                    <span class="text-sm">Limit Order</span>
                                </label>
                            </div>
                            
                            <!-- Amount Input -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Amount ({{ $baseCurrency }})
                                </label>
                                <input type="number" 
                                       name="amount" 
                                       step="0.00000001"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <!-- Price Input (for limit orders) -->
                            <div id="price-input" style="display: none;">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Price ({{ $quoteCurrency }})
                                </label>
                                <input type="number" 
                                       name="price" 
                                       step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" 
                                    id="submit-btn"
                                    class="w-full py-3 px-4 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                                Place Buy Order
                            </button>
                        </form>
                    </div>
                    
                    <!-- User's Open Orders -->
                    @if($userOrders->isNotEmpty())
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mt-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Your Open Orders</h2>
                            
                            <div class="space-y-2">
                                @foreach($userOrders as $order)
                                    <div class="border rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="inline-block px-2 py-1 text-xs font-medium rounded {{ $order->type === 'buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ strtoupper($order->type) }}
                                                </span>
                                                <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">
                                                    {{ ucfirst($order->order_type) }}
                                                </span>
                                            </div>
                                            <form method="POST" action="{{ route('exchange.cancel-order', $order->order_id) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        onclick="return confirm('Cancel this order?')"
                                                        class="text-sm text-red-600 hover:text-red-800">
                                                    Cancel
                                                </button>
                                            </form>
                                        </div>
                                        <div class="mt-2 text-sm">
                                            <p>Amount: {{ number_format($order->remaining_amount, 8) }} {{ $baseCurrency }}</p>
                                            @if($order->price)
                                                <p>Price: {{ number_format($order->price, 2) }} {{ $quoteCurrency }}</p>
                                            @endif
                                            <p class="text-xs text-gray-500 mt-1">{{ $order->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Start Trading</h2>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Sign in to place orders and start trading on FinAegis Exchange.
                        </p>
                        <a href="{{ route('login') }}" class="block w-full text-center py-3 px-4 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                            Sign In to Trade
                        </a>
                    </div>
                @endauth
                
                <!-- Market Pairs -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mt-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Markets</h2>
                    
                    <div class="space-y-2">
                        @foreach($markets as $market)
                            <a href="{{ route('exchange.index', ['base' => explode('/', $market['pair'])[0], 'quote' => explode('/', $market['pair'])[1]]) }}"
                               class="block p-3 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $market['pair'] === "$baseCurrency/$quoteCurrency" ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">{{ $market['pair'] }}</span>
                                    <span class="{{ ($market['change_24h_percentage'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }} text-sm">
                                        {{ number_format($market['change_24h_percentage'] ?? 0, 2) }}%
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ number_format($market['last_price'] ?? 0, 2) }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setOrderType(type) {
    document.getElementById('order-type').value = type;
    
    // Update button styles
    document.querySelectorAll('.order-type-btn').forEach(btn => {
        if (btn.dataset.type === type) {
            btn.classList.add(type === 'buy' ? 'bg-green-600' : 'bg-red-600', 'text-white');
            btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
        } else {
            btn.classList.remove('bg-green-600', 'bg-red-600', 'text-white');
            btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
        }
    });
    
    // Update submit button
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.textContent = `Place ${type.charAt(0).toUpperCase() + type.slice(1)} Order`;
    submitBtn.className = `w-full py-3 px-4 ${type === 'buy' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'} text-white font-medium rounded-lg transition-colors`;
}

function togglePriceInput() {
    const orderType = document.querySelector('input[name="order_type"]:checked').value;
    const priceInput = document.getElementById('price-input');
    const priceField = document.querySelector('input[name="price"]');
    
    if (orderType === 'limit') {
        priceInput.style.display = 'block';
        priceField.required = true;
    } else {
        priceInput.style.display = 'none';
        priceField.required = false;
    }
}

// Initialize
setOrderType('buy');
</script>
@endsection