@extends('layouts.public')

@section('title', 'Double-Entry Ledger Engine - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Double-Entry Ledger Engine',
        'description' => 'A reference double-entry ledger with a database driver and an optional TigerBeetle driver. It has a chart of accounts, journal entries, a trial balance, automatic postings from domain events and reconciliation. It runs in demo mode.',
        'keywords' => 'double-entry ledger, accounting engine, chart of accounts, journal entries, trial balance, TigerBeetle, general ledger posting, reconciliation, financial accounting, ledger software, reference implementation',
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
                    <span class="text-gray-400 text-sm font-medium">Reference implementation</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Double-Entry Ledger</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    The ledger module does double-entry accounting for the platform. It calculates with exact decimals and comes with a chart of 21 accounts. Domain events post entries to the general ledger by themselves. An optional driver can store entries in TigerBeetle instead of the database. This is a reference implementation. It runs in demo mode and is switched off on the production site.
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
                LedgerService checks every journal entry before it posts it. Debits must equal credits exactly. All arithmetic uses PHP's <code class="text-sm bg-gray-100 px-1 rounded">bcmath</code> extension, so no rounding errors creep in.
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
                    <p class="text-slate-500 mb-4">Every financial event creates a balanced journal entry. An entry can have many lines, and either all lines post or none do. Each line has a debit or credit, an account code and an optional description.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>All lines post in one database transaction</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Debits must equal credits before the entry is saved</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Four decimal places with bcmath</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mistakes are corrected with a reversing entry</li>
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
                    <p class="text-slate-500 mb-4">A trial balance adds up all journal lines for each account at a chosen date. Total debits and total credits must be equal. This is how the ledger checks itself.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Trial balance at any date</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Balance for each account code</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Account history for a date range</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Available through the API</li>
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
                The default chart has 21 accounts of 5 types. You can add your own accounts to it.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-blue-50 rounded-xl p-5 border border-blue-100">
                    <h3 class="font-bold text-blue-800 mb-3">Assets</h3>
                    <ul class="space-y-1 text-sm text-blue-700">
                        <li>1000 Assets</li>
                        <li>1100 Cash &amp; Bank</li>
                        <li>1110 Operating Account</li>
                        <li>1120 Settlement Account</li>
                        <li>1200 Loans Receivable</li>
                        <li>1300 Card Receivables</li>
                        <li>1400 Decentralised Finance Positions</li>
                    </ul>
                </div>
                <div class="bg-red-50 rounded-xl p-5 border border-red-100">
                    <h3 class="font-bold text-red-800 mb-3">Liabilities</h3>
                    <ul class="space-y-1 text-sm text-red-700">
                        <li>2000 Liabilities</li>
                        <li>2100 Customer Deposits</li>
                        <li>2200 Customer Wallets</li>
                        <li>2300 Pending Settlements</li>
                    </ul>
                </div>
                <div class="bg-purple-50 rounded-xl p-5 border border-purple-100">
                    <h3 class="font-bold text-purple-800 mb-3">Equity</h3>
                    <ul class="space-y-1 text-sm text-purple-700">
                        <li>3000 Equity</li>
                        <li>3100 Retained Earnings</li>
                    </ul>
                </div>
                <div class="bg-green-50 rounded-xl p-5 border border-green-100">
                    <h3 class="font-bold text-green-800 mb-3">Revenue</h3>
                    <ul class="space-y-1 text-sm text-green-700">
                        <li>4000 Revenue</li>
                        <li>4100 Transaction Fees</li>
                        <li>4200 Interest Income</li>
                        <li>4300 Exchange Revenue</li>
                    </ul>
                </div>
                <div class="bg-orange-50 rounded-xl p-5 border border-orange-100">
                    <h3 class="font-bold text-orange-800 mb-3">Expenses</h3>
                    <ul class="space-y-1 text-sm text-orange-700">
                        <li>5000 Expenses</li>
                        <li>5100 Payment Processing Fees</li>
                        <li>5200 Network Fees</li>
                        <li>5300 Operational Costs</li>
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
                You choose where the ledger stores its entries. Both drivers use the same LedgerService interface, so you can switch without changing your business logic.
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
                                <span class="text-xs text-gray-400">Default, uses MySQL</span>
                            </div>
                        </div>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">Default</span>
                    </div>
                    <p class="text-slate-500 mb-4">This driver stores entries in MySQL through Eloquent. Postings run inside <code class="text-xs bg-gray-100 px-1 rounded">DB::transaction()</code> and use <code class="text-xs bg-gray-100 px-1 rounded">lockForUpdate()</code> to keep balances consistent. It suits most deployments.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>No extra infrastructure</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Full access to the Eloquent query builder</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>The default driver</li>
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
                                <span class="text-xs text-gray-400">Optional, for high volume</span>
                            </div>
                        </div>
                        <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">Optional</span>
                    </div>
                    <p class="text-slate-500 mb-4">This driver sends postings to a TigerBeetle cluster through an HTTP gateway. TigerBeetle is a database built for financial transfers at high volume.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Optional, switched on with a setting</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Debit and credit lines become TigerBeetle transfers</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Cluster address and timeout are configurable</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Auto-Posting & Reconciliation -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Automatic Posting and Reconciliation</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">PostingRuleEngine</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Domain events create general ledger postings through a rule engine. For example, when a payment completes or a fee is charged, the matching journal entry posts by itself.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Posting rules triggered by events</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Debit and credit accounts set for each event</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>No manual posting for routine transactions</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Each entry keeps a reference to its source event</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">Reconciliation</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Reconciliation compares a general ledger balance with the balance a domain reports, such as a wallet or an account. It saves a report and flags any difference. Someone can then resolve the difference with a note.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>General ledger balance compared with the domain balance</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Differences flagged in a saved report</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>History of past reconciliations for each domain</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Differences resolved with a note</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Study Double-Entry Accounting in Code</h2>
            <p class="text-lg text-slate-400 mb-8">The engine enforces the accounting rules, so your code can focus on the product. You start with MySQL and can move to TigerBeetle later.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Create a Sandbox Account</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
