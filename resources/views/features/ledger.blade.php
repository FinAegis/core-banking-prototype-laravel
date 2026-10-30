@extends('layouts.public')

@section('title', 'Double-Entry Ledger Engine - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Double-Entry Ledger Engine',
        'description' => 'Double-entry accounting with PHP-native and TigerBeetle drivers. Chart of accounts, journal entries, trial balance, GL auto-posting, and reconciliation.',
        'keywords' => 'double-entry ledger, accounting engine, chart of accounts, journal entries, trial balance, TigerBeetle, GL posting, reconciliation, financial accounting, ledger software',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Double-Entry Ledger', 'url' => url('/features/ledger')]
    ]" />
@endsection

@push('styles')
<style>
    .ledger-card {
        transition: all 0.3s ease;
    }
    .ledger-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center bg-gray-500/10 border border-gray-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-gray-400 text-sm font-medium">Double-Entry Invariant Enforced</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Double-Entry Ledger</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    The accounting foundation for financial platforms. Double-entry engine with bcmath precision, 21-account chart of accounts, TigerBeetle driver for extreme throughput, and automatic GL posting from domain events.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#drivers" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Compare Drivers
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gray-500/20 to-transparent"></div>
    </section>

    <!-- Core Engine -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Core Engine</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                LedgerService enforces the double-entry invariant on every journal posting — debits must equal credits to the penny. All arithmetic uses PHP's <code class="text-sm bg-gray-100 px-1 rounded">bcmath</code> extension for exact decimal precision.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="ledger-card card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Journal Entries</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Every financial event creates a balanced journal entry. Multi-leg entries are atomic — either all lines post or none do. Supports debit/credit, account codes, and optional narrative description.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Atomic multi-leg entries in DB transaction</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Debit = Credit invariant enforced pre-commit</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>bcmath 4-decimal precision throughout</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Immutable entry log with timestamp</li>
                    </ul>
                </div>

                <div class="ledger-card card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Trial Balance</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Generate a trial balance at any point in time by summing all journal entry lines per account. Total debits and total credits must balance to zero — the ledger's self-verification mechanism.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Point-in-time trial balance query</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Net balance per account code</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Automatic imbalance detection and alert</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>CSV and JSON export</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Chart of Accounts -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Chart of Accounts</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                21 pre-configured accounts across 5 account types, ready for a financial platform. Extend or replace the default chart per tenant.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-blue-50 rounded-xl p-5 border border-blue-100">
                    <h3 class="font-bold text-blue-800 mb-3">Assets</h3>
                    <ul class="space-y-1 text-sm text-blue-700">
                        <li>1000 Cash</li>
                        <li>1100 Receivables</li>
                        <li>1200 Crypto Holdings</li>
                        <li>1300 Prepaid</li>
                    </ul>
                </div>
                <div class="bg-red-50 rounded-xl p-5 border border-red-100">
                    <h3 class="font-bold text-red-800 mb-3">Liabilities</h3>
                    <ul class="space-y-1 text-sm text-red-700">
                        <li>2000 Payables</li>
                        <li>2100 Customer Deposits</li>
                        <li>2200 Accrued Liabilities</li>
                        <li>2300 Deferred Revenue</li>
                    </ul>
                </div>
                <div class="bg-purple-50 rounded-xl p-5 border border-purple-100">
                    <h3 class="font-bold text-purple-800 mb-3">Equity</h3>
                    <ul class="space-y-1 text-sm text-purple-700">
                        <li>3000 Capital</li>
                        <li>3100 Retained Earnings</li>
                        <li>3200 Distributions</li>
                    </ul>
                </div>
                <div class="bg-green-50 rounded-xl p-5 border border-green-100">
                    <h3 class="font-bold text-green-800 mb-3">Revenue</h3>
                    <ul class="space-y-1 text-sm text-green-700">
                        <li>4000 Fee Income</li>
                        <li>4100 Interest Income</li>
                        <li>4200 FX Gains</li>
                        <li>4300 Other Income</li>
                    </ul>
                </div>
                <div class="bg-orange-50 rounded-xl p-5 border border-orange-100">
                    <h3 class="font-bold text-orange-800 mb-3">Expenses</h3>
                    <ul class="space-y-1 text-sm text-orange-700">
                        <li>5000 Operating Expenses</li>
                        <li>5100 Network Fees</li>
                        <li>5200 FX Losses</li>
                        <li>5300 Provisions</li>
                        <li>5400 Depreciation</li>
                        <li>5500 Other Expenses</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Drivers -->
    <section id="drivers" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Storage Drivers</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Choose the right storage backend for your throughput requirement. Both drivers share the same LedgerService interface — swap without changing business logic.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="ledger-card card-feature !p-8">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">EloquentDriver</h3>
                                <span class="text-xs text-gray-400">Default — MySQL</span>
                            </div>
                        </div>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">Default</span>
                    </div>
                    <p class="text-slate-500 mb-4">Uses Eloquent ORM with MySQL. Transactions use <code class="text-xs bg-gray-100 px-1 rounded">DB::transaction()</code> with <code class="text-xs bg-gray-100 px-1 rounded">lockForUpdate()</code> for balance consistency. Best for most deployments.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Zero additional infrastructure</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Full Eloquent query builder access</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Thousands of TPS on standard hardware</li>
                    </ul>
                </div>

                <div class="ledger-card card-feature !p-8">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">TigerBeetleDriver</h3>
                                <span class="text-xs text-gray-400">Optional — high throughput</span>
                            </div>
                        </div>
                        <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">Optional</span>
                    </div>
                    <p class="text-slate-500 mb-4">Connects to a TigerBeetle cluster for financial-grade fault-tolerant storage. Designed for millions of accounts and billions of transfers with strict consistency.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>1M+ TPS on commodity hardware</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>ACID with Byzantine fault tolerance</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Built for financial workloads</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Auto-Posting & Reconciliation -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Auto-Posting &amp; Reconciliation</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">PostingRuleEngine</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Domain events trigger automatic GL postings through a configurable rule engine. When a payment completes, fee is collected, or interest accrues — corresponding journal entries post without manual intervention.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Event-driven posting rules</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Configurable debit/credit mappings per event</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Zero manual posting for routine transactions</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Full posting log with event reference</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">Reconciliation</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Compare GL balances against domain balances (wallet, account, reserve) and flag any discrepancies. Run on-demand or schedule nightly for automated financial close.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>GL vs domain balance comparison</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Discrepancy flagging with entity reference</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Scheduled via artisan command</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Webhook notification on mismatch</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Build on Solid Accounting Foundations</h2>
            <p class="text-lg text-slate-400 mb-8">The double-entry engine handles the accounting invariants so you can focus on product logic. Start with MySQL, scale to TigerBeetle when you need it.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Start Free Trial</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
