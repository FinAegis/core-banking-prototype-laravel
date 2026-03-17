@extends('layouts.public')

@section('title', 'Privacy & Identity - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Privacy & Identity',
        'description' => 'Privacy-preserving identity verification with zero-knowledge proofs, verifiable credentials, Merkle trees, and ERC-4337 gas abstraction. Protect user data while meeting compliance requirements.',
        'keywords' => 'privacy identity, zero knowledge proofs, ZK-KYC, verifiable credentials, Merkle trees, soulbound tokens, ERC-4337, gas abstraction, key management, ' . config('brand.name', 'Zelta') . '',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Privacy & Identity', 'url' => url('/features/privacy-identity')]
    ]" />
@endsection


@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Privacy-Preserving Identity</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Prove who you are without revealing what you are. Harness zero-knowledge proofs, W3C verifiable credentials, and advanced cryptographic primitives to verify identity while keeping personal data private.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Core Technologies -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Core Technologies</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                A comprehensive privacy stack built on proven cryptographic foundations, enabling compliant identity verification without compromising user data.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- ZK-KYC -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">ZK-KYC</h3>
                    <p class="text-slate-500 mb-4">
                        Zero-knowledge identity verification that proves compliance status without exposing personal documents. Users complete KYC once and generate reusable ZK proofs for future verifications.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Selective attribute disclosure
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Age range proofs without birthdate
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Jurisdiction compliance checks
                        </li>
                    </ul>
                </div>

                <!-- Merkle Trees -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Merkle Trees</h3>
                    <p class="text-slate-500 mb-4">
                        Privacy-preserving data structures that enable efficient proof of membership and data integrity without revealing the full dataset. Anchor proofs on-chain for public verifiability.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Inclusion and exclusion proofs
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            On-chain root anchoring
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Logarithmic verification complexity
                        </li>
                    </ul>
                </div>

                <!-- Soulbound Tokens -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-pink-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Soulbound Tokens</h3>
                    <p class="text-slate-500 mb-4">
                        Non-transferable on-chain attestations that bind credentials permanently to an identity. Perfect for certifications, compliance status, and reputation that should not be sold or transferred.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Non-transferable by design
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Revocable attestation support
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Merchant onboarding credentials
                        </li>
                    </ul>
                </div>

                <!-- Verifiable Credentials -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Verifiable Credentials</h3>
                    <p class="text-slate-500 mb-4">
                        W3C-standard verifiable credentials that enable portable, tamper-evident digital identity. Issue, hold, and verify credentials across platforms with cryptographic assurance.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            W3C VC Data Model compliant
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            JSON-LD and JWT proof formats
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Cross-platform credential portability
                        </li>
                    </ul>
                </div>

                <!-- Key Management -->
                <div class="card-feature !p-8 lg:col-span-2">
                    <div class="flex flex-col md:flex-row md:items-start gap-6">
                        <div class="w-14 h-14 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold mb-3 text-slate-900">Key Management</h3>
                            <p class="text-slate-500 mb-4">
                                Enterprise-grade key management using Shamir's Secret Sharing for key sharding and HSM integration for hardware-backed security. Split private keys across multiple custodians with configurable threshold recovery, ensuring no single point of compromise.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-amber-900 mb-1">Shamir's Secret Sharing</h4>
                                    <p class="text-sm text-slate-500">M-of-N threshold key recovery with configurable shard distribution</p>
                                </div>
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-amber-900 mb-1">HSM Integration</h4>
                                    <p class="text-sm text-slate-500">Hardware Security Module support for tamper-resistant key storage</p>
                                </div>
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-amber-900 mb-1">Key Rotation</h4>
                                    <p class="text-sm text-slate-500">Automated key lifecycle management and rotation policies</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Privacy Layer -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Privacy Layer</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Advanced cryptographic protocols that let users prove statements about their data without revealing the underlying information.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Delegated Proofs -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Delegated Proofs</h3>
                    <p class="text-slate-500 mb-4">
                        Allow trusted third parties to generate proofs on a user's behalf without accessing raw data. Enables institutional verification workflows while preserving individual privacy.
                    </p>
                    <div class="border-t pt-4 mt-4">
                        <h4 class="font-semibold text-sm text-slate-900 mb-2">Use Cases</h4>
                        <ul class="space-y-1 text-sm text-gray-500">
                            <li class="flex items-start">
                                <span class="text-indigo-400 mr-2 mt-0.5">--</span>
                                Corporate compliance delegation
                            </li>
                            <li class="flex items-start">
                                <span class="text-indigo-400 mr-2 mt-0.5">--</span>
                                Automated regulatory reporting
                            </li>
                            <li class="flex items-start">
                                <span class="text-indigo-400 mr-2 mt-0.5">--</span>
                                Multi-party verification chains
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Proof of Innocence -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-emerald-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Proof of Innocence</h3>
                    <p class="text-slate-500 mb-4">
                        Cryptographically demonstrate that funds are not associated with sanctioned addresses or illicit activity, all without revealing full transaction history or account balances.
                    </p>
                    <div class="border-t pt-4 mt-4">
                        <h4 class="font-semibold text-sm text-slate-900 mb-2">Capabilities</h4>
                        <ul class="space-y-1 text-sm text-gray-500">
                            <li class="flex items-start">
                                <span class="text-emerald-400 mr-2 mt-0.5">--</span>
                                Sanctions list non-membership proof
                            </li>
                            <li class="flex items-start">
                                <span class="text-emerald-400 mr-2 mt-0.5">--</span>
                                Source-of-funds attestation
                            </li>
                            <li class="flex items-start">
                                <span class="text-emerald-400 mr-2 mt-0.5">--</span>
                                AML compliance without data exposure
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- SRS Manifests -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-violet-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">SRS Manifests</h3>
                    <p class="text-slate-500 mb-4">
                        Structured Reference String manifests that define the parameters for zero-knowledge proof generation. Manage trusted setup ceremonies and proof circuit configurations transparently.
                    </p>
                    <div class="border-t pt-4 mt-4">
                        <h4 class="font-semibold text-sm text-slate-900 mb-2">Features</h4>
                        <ul class="space-y-1 text-sm text-gray-500">
                            <li class="flex items-start">
                                <span class="text-violet-400 mr-2 mt-0.5">--</span>
                                Verifiable trusted setup parameters
                            </li>
                            <li class="flex items-start">
                                <span class="text-violet-400 mr-2 mt-0.5">--</span>
                                Circuit versioning and upgrades
                            </li>
                            <li class="flex items-start">
                                <span class="text-violet-400 mr-2 mt-0.5">--</span>
                                Multi-party computation ceremonies
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Framework -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Trust Framework</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                A layered trust architecture that establishes verifiable chains of authority from root certificate authorities to individual credential holders.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="space-y-8">
                        <!-- Certificate Authority -->
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold mb-2 text-slate-900">Certificate Authority</h3>
                                <p class="text-slate-500">
                                    Built-in Certificate Authority for issuing and managing digital certificates. Supports hierarchical PKI with root and intermediate CAs, certificate revocation lists, and OCSP responders for real-time status checking.
                                </p>
                            </div>
                        </div>

                        <!-- Trust Chains -->
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold mb-2 text-slate-900">Trust Chains</h3>
                                <p class="text-slate-500">
                                    Establish cryptographically verifiable chains of trust from issuers to holders. Every credential can be traced back through a chain of signed attestations to a trusted root authority, enabling instant verification.
                                </p>
                            </div>
                        </div>

                        <!-- Credential Export -->
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold mb-2 text-slate-900">Credential Export</h3>
                                <p class="text-slate-500">
                                    Export verifiable credentials in interoperable formats including JSON-LD, JWT, and CBOR. Credentials are portable across platforms and can be verified offline with embedded proof chains.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-2xl p-8">
                    <h3 class="text-2xl font-bold mb-6 text-slate-900">Trust Architecture</h3>
                    <div class="space-y-4">
                        <div class="flex items-center p-4 bg-white rounded-lg shadow-sm">
                            <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <span class="text-white font-bold text-sm">CA</span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Root Certificate Authority</h4>
                                <p class="text-sm text-gray-500">Self-signed root of trust</p>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                        </div>
                        <div class="flex items-center p-4 bg-white rounded-lg shadow-sm">
                            <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <span class="text-white font-bold text-sm">IC</span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Intermediate Certificates</h4>
                                <p class="text-sm text-gray-500">Domain-specific signing authority</p>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                        </div>
                        <div class="flex items-center p-4 bg-white rounded-lg shadow-sm">
                            <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <span class="text-white font-bold text-sm">VC</span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Verifiable Credentials</h4>
                                <p class="text-sm text-gray-500">User-held portable credentials</p>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                        </div>
                        <div class="flex items-center p-4 bg-white rounded-lg shadow-sm">
                            <div class="w-10 h-10 bg-amber-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <span class="text-white font-bold text-sm">ZK</span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Zero-Knowledge Proofs</h4>
                                <p class="text-sm text-gray-500">Privacy-preserving verification</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ERC-4337 Gas Abstraction -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">ERC-4337 Gas Abstraction</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Account abstraction eliminates the gas barrier for users. Sponsored transactions, smart accounts, and meta-transactions make blockchain interactions seamless.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Smart Accounts -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">Smart Accounts</h3>
                    </div>
                    <p class="text-slate-500 mb-6">
                        Contract-based accounts with programmable logic for spending limits, multi-signature requirements, social recovery, and session keys. Users operate smart accounts instead of basic externally-owned accounts.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-blue-900">Social Recovery</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-blue-900">Spending Limits</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-blue-900">Session Keys</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-blue-900">Multi-sig</p>
                        </div>
                    </div>
                </div>

                <!-- UserOp Signing -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-14 h-14 bg-teal-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">UserOp Signing</h3>
                    </div>
                    <p class="text-slate-500 mb-6">
                        Sign user operations with biometric JWT authentication for mobile-first transaction authorization. The signing pipeline validates biometric proofs, constructs ERC-4337 UserOperations, and submits them to bundlers.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-teal-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-teal-900">Biometric JWT</p>
                        </div>
                        <div class="bg-teal-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-teal-900">Bundler Submit</p>
                        </div>
                        <div class="bg-teal-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-teal-900">Paymaster Integration</p>
                        </div>
                        <div class="bg-teal-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-teal-900">Nonce Management</p>
                        </div>
                    </div>
                </div>

                <!-- Gas Station -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-14 h-14 bg-orange-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">Gas Station</h3>
                    </div>
                    <p class="text-slate-500 mb-6">
                        A production-ready paymaster service that sponsors transaction gas fees for users. Supports policy-based sponsorship rules, per-user gas budgets, and real-time gas price optimization across networks.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Fee Sponsorship</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Gas Budgets</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Price Optimization</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Multi-Network</p>
                        </div>
                    </div>
                </div>

                <!-- Meta-Transactions -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-14 h-14 bg-rose-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">Meta-Transactions</h3>
                    </div>
                    <p class="text-slate-500 mb-6">
                        Users sign transaction intents off-chain while relayers submit and pay gas on-chain. This decouples the signer from the gas payer, enabling gasless user experiences and batch transaction processing.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">Gasless UX</p>
                        </div>
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">Batch Processing</p>
                        </div>
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">Relayer Network</p>
                        </div>
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">EIP-712 Signing</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Features -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Technical Features</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Built on battle-tested cryptographic libraries and standards, every component is designed for production-grade security and performance.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Zero-Knowledge Proof Generation</h4>
                        <p class="text-sm text-gray-500">Groth16 and PLONK proving systems</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">W3C DID Resolution</h4>
                        <p class="text-sm text-gray-500">Decentralized identifier support</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Shamir Secret Sharing (SSS)</h4>
                        <p class="text-sm text-gray-500">Threshold key recovery with M-of-N</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">HSM-Backed Key Storage</h4>
                        <p class="text-sm text-gray-500">Hardware security module integration</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Biometric JWT Authentication</h4>
                        <p class="text-sm text-gray-500">Mobile biometric proof tokens</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">ERC-4337 Bundler Integration</h4>
                        <p class="text-sm text-gray-500">UserOperation bundling and submission</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Merkle Proof Verification</h4>
                        <p class="text-sm text-gray-500">On-chain anchored inclusion proofs</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Selective Disclosure</h4>
                        <p class="text-sm text-gray-500">Reveal only required attributes</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Credential Revocation</h4>
                        <p class="text-sm text-gray-500">Real-time status list management</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Paymaster Policies</h4>
                        <p class="text-sm text-gray-500">Configurable gas sponsorship rules</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Multi-Format Export</h4>
                        <p class="text-sm text-gray-500">JSON-LD, JWT, and CBOR outputs</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Audit Trail Logging</h4>
                        <p class="text-sm text-gray-500">Immutable verification event logs</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Protect Identity. Prove Compliance.</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Experience the next generation of digital identity where privacy and compliance coexist. Zero-knowledge proofs, verifiable credentials, and gasless transactions -- all in one platform.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Get Started
                </a>
                <a href="{{ url('/features') }}" class="btn-outline px-8 py-4 text-lg">
                    Explore All Features
                </a>
            </div>
        </div>
    </section>

@endsection
