@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', 'Event Streaming - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title' => 'Event Streaming - ' . $brand . ' Developer Documentation',
        'description' => 'Event streaming in ' . $brand . ' publishes domain events to Redis Streams for 15 domains. Workers in consumer groups read the events as they arrive.',
        'keywords' => $brand . ', event streaming, Redis Streams, publish and subscribe, domain events, consumer groups',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font { font-family: 'Fira Code', monospace; }
    .stream-gradient { background: linear-gradient(135deg, #dc2626 0%, #f97316 100%); }
    .code-container { position: relative; background: #0f1419; border-radius: 0.75rem; overflow: hidden; }
    .code-header { background: #0f172a; padding: 0.5rem 1rem; font-size: 0.75rem; font-family: 'Figtree', sans-serif; color: #94a3b8; display: flex; justify-content: space-between; align-items: center; }
    .code-block { font-family: 'Fira Code', monospace; font-size: 0.875rem; line-height: 1.5; overflow-x: auto; white-space: pre; }
</style>
@endpush

@section('content')

<!-- Hero -->
<section class="stream-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0" aria-hidden="true">
        <div class="absolute top-20 left-10 w-72 h-72 bg-red-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-orange-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 border border-white/20 mb-4">15 Domain Streams</span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Event Streaming</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Event streaming sends domain events through Redis Streams as they happen. Workers in consumer groups share the reading and processing.
            </p>
        </div>
    </div>
</section>

<!-- Architecture -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">How It Works</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-3xl mb-2">1</div>
                <h3 class="font-semibold mb-2">Publish</h3>
                <p class="text-sm text-slate-600">Domain services publish their events. Each domain has its own Redis Stream.</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-3xl mb-2">2</div>
                <h3 class="font-semibold mb-2">Stream</h3>
                <p class="text-sm text-slate-600">Redis Streams keeps the events for the consumer groups. It trims old entries when a stream reaches its maximum length.</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-3xl mb-2">3</div>
                <h3 class="font-semibold mb-2">Consume</h3>
                <p class="text-sm text-slate-600">Workers read events in batches, process them and confirm each one. A message that is not confirmed is delivered again.</p>
            </div>
        </div>

        <!-- Publisher example -->
        <div class="code-container mb-6">
            <div class="code-header"><span>Publishing events</span></div>
            <pre class="code-block p-4 text-slate-300"><span class="text-purple-400">use</span> App\Domain\Shared\EventSourcing\EventStreamPublisher;

<span class="text-slate-500">// Inject via constructor</span>
<span class="text-purple-400">public function</span> <span class="text-blue-300">__construct</span>(
    <span class="text-purple-400">private readonly</span> EventStreamPublisher <span class="text-orange-300">$publisher</span>
) {}

<span class="text-slate-500">// Publish a domain event</span>
<span class="text-orange-300">$messageId</span> = <span class="text-purple-400">$this</span>->publisher->publish(<span class="text-green-400">'account'</span>, [
    <span class="text-green-400">'event_class'</span>    => AccountCreatedEvent::class,
    <span class="text-green-400">'aggregate_uuid'</span> => <span class="text-orange-300">$account</span>->uuid,
    <span class="text-green-400">'name'</span>           => <span class="text-orange-300">$account</span>->name,
    <span class="text-green-400">'balance'</span>        => <span class="text-green-400">'0.00'</span>,
]);

<span class="text-slate-500">// Batch publish</span>
<span class="text-orange-300">$ids</span> = <span class="text-purple-400">$this</span>->publisher->publishBatch([
    [<span class="text-green-400">'domain'</span> => <span class="text-green-400">'account'</span>, <span class="text-green-400">'data'</span> => [...]],
    [<span class="text-green-400">'domain'</span> => <span class="text-green-400">'payment'</span>, <span class="text-green-400">'data'</span> => [...]],
]);</pre>
        </div>

        <!-- Consumer example -->
        <div class="code-container">
            <div class="code-header"><span>Consuming events</span></div>
            <pre class="code-block p-4 text-slate-300"><span class="text-purple-400">use</span> App\Domain\Shared\EventSourcing\EventStreamConsumer;

<span class="text-orange-300">$consumer</span> = app(EventStreamConsumer::class);

<span class="text-slate-500">// Create consumer group (once, on startup)</span>
<span class="text-orange-300">$consumer</span>->createConsumerGroup(<span class="text-green-400">'account'</span>, <span class="text-green-400">'0'</span>);

<span class="text-slate-500">// Read messages in a loop</span>
<span class="text-purple-400">while</span> (<span class="text-orange-300">true</span>) {
    <span class="text-orange-300">$messages</span> = <span class="text-orange-300">$consumer</span>->consume(<span class="text-green-400">'account'</span>, <span class="text-green-400">'worker-1'</span>);

    <span class="text-purple-400">foreach</span> (<span class="text-orange-300">$messages</span> <span class="text-purple-400">as</span> [<span class="text-orange-300">$id</span>, <span class="text-orange-300">$fields</span>]) {
        <span class="text-orange-300">$payload</span> = json_decode(<span class="text-orange-300">$fields</span>[<span class="text-green-400">'payload'</span>], <span class="text-orange-300">true</span>);

        <span class="text-slate-500">// Process event...</span>

        <span class="text-orange-300">$consumer</span>->acknowledge(<span class="text-green-400">'account'</span>, <span class="text-orange-300">$id</span>);
    }
}</pre>
        </div>
    </div>
</section>

<!-- Domain Streams -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Available Streams</h2>
        <p class="text-slate-600 mb-8">Each domain publishes to its own Redis Stream. The stream keys start with the value of <code class="code-font bg-slate-200 px-1.5 py-0.5 rounded">EVENT_STREAMING_PREFIX</code>, which is <code class="code-font bg-slate-200 px-1.5 py-0.5 rounded">{{ config('event-streaming.prefix', 'finaegis:events') }}</code> here.</p>

        @php
        $streams = [
            ['domain' => 'account', 'stream' => 'account-events', 'desc' => 'Accounts created, updated and frozen, balance changes'],
            ['domain' => 'exchange', 'stream' => 'exchange-events', 'desc' => 'Orders placed, matched and cancelled, trades'],
            ['domain' => 'wallet', 'stream' => 'wallet-events', 'desc' => 'Wallets created, transfers, multi-signature approvals'],
            ['domain' => 'compliance', 'stream' => 'compliance-events', 'desc' => 'Identity check submissions, anti-money laundering alerts, sanctions screening'],
            ['domain' => 'lending', 'stream' => 'lending-events', 'desc' => 'Loan applications, approvals, disbursements'],
            ['domain' => 'treasury', 'stream' => 'treasury-events', 'desc' => 'Portfolio rebalancing, net asset value calculations'],
            ['domain' => 'payment', 'stream' => 'payment-events', 'desc' => 'Deposits, withdrawals, transfer completions'],
            ['domain' => 'fraud', 'stream' => 'fraud-events', 'desc' => 'Fraud alerts, case escalations, pattern detections'],
            ['domain' => 'mobile', 'stream' => 'mobile-events', 'desc' => 'Device registrations, session events'],
            ['domain' => 'mobile-payment', 'stream' => 'mobile-payment-events', 'desc' => 'Payment intents, receipt generation'],
            ['domain' => 'trust-cert', 'stream' => 'trust-cert-events', 'desc' => 'Certificate issuance, revocation'],
            ['domain' => 'cross-chain', 'stream' => 'cross-chain-events', 'desc' => 'Bridge transfers, completions'],
            ['domain' => 'defi', 'stream' => 'defi-events', 'desc' => 'Positions opened and closed, swaps'],
            ['domain' => 'stablecoin', 'stream' => 'stablecoin-events', 'desc' => 'Minting, redemption, collateral changes'],
            ['domain' => 'privacy', 'stream' => 'privacy-events', 'desc' => 'Identity proofs, delegated proof progress'],
        ];
        @endphp

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Domain</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Stream Key</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Events</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($streams as $s)
                    <tr>
                        <td class="px-4 py-2.5 font-medium">{{ $s['domain'] }}</td>
                        <td class="px-4 py-2.5"><code class="code-font text-xs bg-red-50 text-red-700 px-2 py-0.5 rounded">{{ $s['stream'] }}</code></td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $s['desc'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Message Format & Config -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div>
                <h2 class="text-2xl font-bold mb-4">Message Format</h2>
                <div class="code-container">
                    <div class="code-header"><span>Redis Stream entry</span></div>
                    <pre class="code-block p-4 text-slate-300">{
  <span class="text-blue-300">"domain"</span>: <span class="text-green-400">"account"</span>,
  <span class="text-blue-300">"event_class"</span>: <span class="text-green-400">"App\\Domain\\Account\\Events\\AccountCreatedEvent"</span>,
  <span class="text-blue-300">"aggregate_uuid"</span>: <span class="text-green-400">"550e8400-..."</span>,
  <span class="text-blue-300">"payload"</span>: <span class="text-green-400">"{...serialized event...}"</span>,
  <span class="text-blue-300">"published_at"</span>: <span class="text-green-400">"2026-03-16T10:30:00Z"</span>
}</pre>
                </div>
            </div>
            <div>
                <h2 class="text-2xl font-bold mb-4">Configuration</h2>
                <div class="space-y-2 text-sm">
                    @php
                    $configs = [
                        ['EVENT_STREAMING_ENABLED', 'false', 'Turns streaming on or off'],
                        ['EVENT_STREAMING_REDIS_CONNECTION', 'default', 'Redis connection name'],
                        ['EVENT_STREAMING_PREFIX', (string) config('event-streaming.prefix', 'finaegis:events'), 'Stream key prefix'],
                        ['EVENT_STREAMING_MAX_LENGTH', '100000', 'Maximum entries per stream'],
                        ['EVENT_STREAMING_CONSUMER_GROUP', (string) config('event-streaming.consumer_group', 'finaegis-consumers'), 'Consumer group name'],
                        ['EVENT_STREAMING_BLOCK_TIMEOUT', '5000', 'Read wait time in milliseconds'],
                        ['EVENT_STREAMING_BATCH_SIZE', '100', 'Messages per read'],
                        ['EVENT_STREAMING_IDLE_TIMEOUT', '30000', 'Idle time in milliseconds before another worker can claim a message'],
                        ['EVENT_STREAMING_TTL_HOURS', '168', 'Retention period (7 days)'],
                    ];
                    @endphp
                    @foreach($configs as $c)
                    <div class="flex items-baseline gap-2 border border-slate-200 rounded-lg px-3 py-2">
                        <code class="code-font text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $c[0] }}</code>
                        <span class="text-slate-400 text-xs">{{ $c[1] }}</span>
                        <span class="text-slate-500 text-xs ml-auto">{{ $c[2] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-8">
            <h3 class="font-semibold text-lg mb-3">Monitoring</h3>
            <div class="code-container">
                <div class="code-header"><span>CLI commands</span></div>
                <pre class="code-block p-4 text-green-400">php artisan event-stream:monitor                    <span class="text-slate-500"># All streams</span>
php artisan event-stream:monitor --domain account   <span class="text-slate-500"># Specific domain</span>
php artisan event-stream:monitor --json             <span class="text-slate-500"># JSON output for tooling</span></pre>
            </div>
        </div>
    </div>
</section>

<!-- Consumer Group Operations -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold mb-4">Consumer Group Operations</h2>
        <p class="text-slate-600 mb-6">A consumer group lets several workers share one stream. If a worker stops, another worker claims its unconfirmed messages.</p>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Method</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Purpose</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">createConsumerGroup($domain, $startId)</code></td><td class="px-4 py-2.5 text-slate-600">Creates the group. Start at <code class="code-font">'0'</code> to read from the beginning, or at <code class="code-font">'$'</code> to read only new messages.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">consume($domain, $consumerName)</code></td><td class="px-4 py-2.5 text-slate-600">Waits for and reads a batch of messages for this consumer</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">acknowledge($domain, $messageId)</code></td><td class="px-4 py-2.5 text-slate-600">Marks a message as processed, so it is not delivered again</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">getPending($domain)</code></td><td class="px-4 py-2.5 text-slate-600">Lists messages that are not confirmed yet</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">claimIdleMessages($domain, $consumer)</code></td><td class="px-4 py-2.5 text-slate-600">Takes over messages from consumers that stopped</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">getConsumerGroupInfo($domain)</code></td><td class="px-4 py-2.5 text-slate-600">Shows the group's consumers and the number of waiting messages</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Back -->
<section class="bg-white py-12">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="{{ route('developers') }}" class="inline-flex items-center gap-2 text-red-600 hover:text-red-800 font-medium">
            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Back to Developer Hub
        </a>
    </div>
</section>

@endsection
