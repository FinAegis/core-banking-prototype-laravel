@extends('layouts.public')

@section('title', 'Privacy and Identity - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Privacy and Identity',
        'description' => 'The privacy and identity modules let a person prove facts about themselves without showing the underlying data. They use zero-knowledge proofs, verifiable credentials and Merkle trees, and ERC-4337 smart accounts let users send without holding gas.',
        'keywords' => 'privacy identity, zero knowledge proofs, zero-knowledge identity checks, verifiable credentials, Merkle trees, soulbound tokens, ERC-4337, gas sponsorship, key management, ' . config('brand.name', 'Zelta'),
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
                    These modules let a person prove a fact about themselves without showing the data behind it. They use zero-knowledge proofs and verifiable credentials that follow the World Wide Web Consortium standard. Personal data stays private during the check.
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
                The identity checks are built from well-known cryptographic methods.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- ZK-KYC -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Zero-Knowledge Identity Checks</h3>
                    <p class="text-slate-500 mb-4">
                        These modules prove an identity attribute, such as an age range, without showing the personal documents. It is not a regulated identity-check service. It is not meant to meet anyone's legal know your customer or anti-money laundering duties.
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
                            Age range proofs without the birth date
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Jurisdiction attribute checks
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
                        A Merkle tree proves that an item is in a set without showing the whole set. It also shows that the data has not changed. The tree's root can be anchored on a blockchain, so anyone can check a proof.
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
                            Fast checks, even for large sets
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
                        A soulbound token is an attestation on a blockchain that cannot be transferred. It ties a credential to one identity. It suits certificates and reputation that should never be sold.
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
                        Verifiable credentials follow the World Wide Web Consortium standard. A signature shows whether a credential was changed. The module issues credentials and verifies them.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Verifiable Credentials Data Model
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            JSON-LD credentials with signed proofs
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Credentials that work on other platforms
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
                                The key management module splits a secret key into shares with Shamir's Secret Sharing. A set number of shares can rebuild the key, so no single holder can use it alone. Keys can also be kept in hardware security modules.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-amber-900 mb-1">Shamir's Secret Sharing</h4>
                                    <p class="text-sm text-slate-500">You choose how many shares exist and how many rebuild the key.</p>
                                </div>
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-amber-900 mb-1">Hardware Security Modules</h4>
                                    <p class="text-sm text-slate-500">Keys can be stored in hardware that resists tampering.</p>
                                </div>
                                <div class="bg-amber-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-amber-900 mb-1">Key Rotation</h4>
                                    <p class="text-sm text-slate-500">Keys are replaced on a schedule that you set.</p>
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
                These protocols let users prove a statement about their data. The data itself is not revealed.
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
                        A trusted service can generate a proof for a user without seeing the raw data. Institutions can use this to run checks while the person's data stays private.
                    </p>
                    <div class="border-t pt-4 mt-4">
                        <h4 class="font-semibold text-sm text-slate-900 mb-2">Use Cases</h4>
                        <ul class="space-y-1 text-sm text-gray-500">
                            <li class="flex items-start">
                                <span class="text-indigo-400 mr-2 mt-0.5">--</span>
                                Compliance checks run for a company
                            </li>
                            <li class="flex items-start">
                                <span class="text-indigo-400 mr-2 mt-0.5">--</span>
                                Automated regulatory reporting
                            </li>
                            <li class="flex items-start">
                                <span class="text-indigo-400 mr-2 mt-0.5">--</span>
                                Checks that pass through several parties
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
                        These modules prove that an address is not on a sanctions list. They do not reveal the transaction history or the balances.
                    </p>
                    <div class="border-t pt-4 mt-4">
                        <h4 class="font-semibold text-sm text-slate-900 mb-2">Capabilities</h4>
                        <ul class="space-y-1 text-sm text-gray-500">
                            <li class="flex items-start">
                                <span class="text-emerald-400 mr-2 mt-0.5">--</span>
                                Sanctions list non-membership proof
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
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Zero-Knowledge Prover</h3>
                    <p class="text-slate-500 mb-4">
                        The prover ships with five Circom circuits for age, income range, identity tier, residency and sanctions checks. Services manage the trusted setup ceremony and compile the circuits. Solidity verifier contracts are generated from them. Run <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">php artisan zk:setup</code> to set up the proving pipeline.
                    </p>
                    <div class="border-t pt-4 mt-4">
                        <h4 class="font-semibold text-sm text-slate-900 mb-2">Features</h4>
                        <ul class="space-y-1 text-sm text-gray-500">
                            <li class="flex items-start">
                                <span class="text-violet-400 mr-2 mt-0.5">--</span>
                                Trusted setup that can be checked
                            </li>
                            <li class="flex items-start">
                                <span class="text-violet-400 mr-2 mt-0.5">--</span>
                                Circom circuit compilation and versioning
                            </li>
                            <li class="flex items-start">
                                <span class="text-violet-400 mr-2 mt-0.5">--</span>
                                Solidity verifier contract generation
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
                The trust framework links each credential holder back to a root certificate authority. Every link in that chain can be checked.
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
                                    The built-in certificate authority issues and manages digital certificates. A root authority signs the certificates below it. A revocation registry shows whether a credential is still valid.
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
                                    A chain of trust runs from the issuer to the holder. Each credential can be traced through signed attestations to a trusted root. A verifier only needs to check that chain.
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
                                <h3 class="text-xl font-bold mb-2 text-slate-900">Credential Formats</h3>
                                <p class="text-slate-500">
                                    Credentials are issued as JSON-LD documents with a signed proof. Other platforms can read them. Certificates can also be exported as PDF documents.
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
                                <p class="text-sm text-gray-500">The root of trust signs itself</p>
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
                                <p class="text-sm text-gray-500">Signing authority for one area</p>
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
                                <p class="text-sm text-gray-500">Credentials the user holds</p>
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
                                <p class="text-sm text-gray-500">Checks that keep data private</p>
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">ERC-4337 Smart Accounts and Gas Sponsorship</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Users of a blockchain normally pay a fee, called gas, for each transaction. With ERC-4337 smart accounts, the platform can pay that fee for them. Users can then send stablecoins without holding the network's own coin.
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
                        A smart account is a contract that holds the user's assets. Its rules can set spending limits, require several signatures or allow recovery through trusted contacts. Users get a smart account instead of a plain key-based account.
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
                        <h3 class="text-xl font-bold text-slate-900">Device Signing</h3>
                    </div>
                    <p class="text-slate-500 mb-6">
                        The wallet is non-custodial, so the user's device signs every transaction with its passkey. The backend prepares the ERC-4337 user operation and never sees the private key. A bundler then submits the signed operation to the network.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-teal-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-teal-900">Passkey Signing</p>
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
                        A paymaster is a service that pays the gas fee for the user. Sponsorship rules and a daily limit for each user control what it pays. It reads the current gas price on each network.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Fee Sponsorship</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Daily Limits</p>
                        </div>
                        <div class="bg-orange-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-orange-900">Gas Price Reading</p>
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
                        The user signs what they want to do. A relayer then submits the transaction and pays the gas. The person who signs and the party who pays are separate, so the user needs no gas.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">No Gas for Users</p>
                        </div>
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">Batch Processing</p>
                        </div>
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">Relayer</p>
                        </div>
                        <div class="bg-rose-50 rounded-lg p-3 text-center">
                            <p class="text-sm font-semibold text-rose-900">Typed-Data Signing</p>
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
                The modules use established cryptographic libraries and standards.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Zero-Knowledge Proof Generation</h4>
                        <p class="text-sm text-gray-500">Groth16 and PLONK proof systems</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Decentralised Identifiers</h4>
                        <p class="text-sm text-gray-500">Identifiers that follow the World Wide Web Consortium standard</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Shamir Secret Sharing</h4>
                        <p class="text-sm text-gray-500">A set number of shares rebuilds a key</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Hardware Key Storage</h4>
                        <p class="text-sm text-gray-500">Keys kept in hardware security modules</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Passkey Authentication</h4>
                        <p class="text-sm text-gray-500">The device confirms the user with a passkey</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">ERC-4337 Bundler Integration</h4>
                        <p class="text-sm text-gray-500">Signed user operations are bundled and submitted</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Merkle Proof Verification</h4>
                        <p class="text-sm text-gray-500">Inclusion proofs anchored on a blockchain</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Selective Disclosure</h4>
                        <p class="text-sm text-gray-500">Only the needed attributes are shown</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Credential Revocation</h4>
                        <p class="text-sm text-gray-500">A status list shows revoked credentials</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Paymaster Policies</h4>
                        <p class="text-sm text-gray-500">Rules for which gas fees are paid</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Credential Export</h4>
                        <p class="text-sm text-gray-500">JSON-LD credentials and PDF certificates</p>
                    </div>
                </div>

                <div class="flex items-start p-4">
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-slate-900">Audit Trail Logging</h4>
                        <p class="text-sm text-gray-500">Every check is logged and cannot be changed</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Privacy-Preserving Identity Tooling</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                These open-source modules cover zero-knowledge proofs, verifiable credentials and sponsored transactions. Operators can build their own identity services on them.
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
