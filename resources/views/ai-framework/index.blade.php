@extends('layouts.public')

@section('title', 'AI Agent Framework - Intelligent Financial Automation | FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'AI Agent Framework - Intelligent Financial Automation',
        'description' => 'Experience the next generation of banking with AI-powered agents. Automate workflows, enhance decision-making, and deliver personalized financial services with FinAegis AI Framework.',
        'keywords' => 'AI agents, financial automation, machine learning, LLM integration, intelligent banking, workflow automation, AI-powered finance, conversational banking',
    ])

    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "FinAegis AI Agent Framework",
        "applicationCategory": "FinancialApplication",
        "operatingSystem": "Web",
        "description": "Enterprise AI framework for intelligent financial automation and decision support",
        "offers": {
            "@@type": "Offer",
            "availability": "https://schema.org/InStock",
            "price": "0",
            "priceCurrency": "USD"
        },
        "featureList": [
            "Multi-LLM Provider Support",
            "Event-Sourced Architecture",
            "Workflow Automation",
            "Vector Database Integration",
            "Real-time Decision Making",
            "Compliance-Ready AI"
        ]
    }
    </script>
@endsection

@push('styles')
<style>
    .ai-gradient {
        background: linear-gradient(135deg, #06b6d4 0%, #8b5cf6 100%);
    }
    .ai-card {
        transition: all 0.4s ease;
        border: 2px solid transparent;
        background: linear-gradient(white, white) padding-box,
                    linear-gradient(135deg, #06b6d4, #8b5cf6) border-box;
    }
    .ai-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(139, 92, 246, 0.2);
    }
    .workflow-step {
        position: relative;
        padding-left: 3rem;
    }
    .workflow-step::before {
        content: '';
        position: absolute;
        left: 1rem;
        top: 2rem;
        bottom: -2rem;
        width: 2px;
        background: linear-gradient(to bottom, #8b5cf6, transparent);
    }
    .workflow-step:last-child::before {
        display: none;
    }
    .tech-badge {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        border: 1px solid #d1d5db;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-block;
        margin: 0.25rem;
    }
    .demo-terminal {
        background: #1e293b;
        border-radius: 0.5rem;
        padding: 1.5rem;
        font-family: 'Courier New', monospace;
        color: #94a3b8;
        overflow-x: auto;
    }
    .demo-terminal .prompt {
        color: #10b981;
    }
    .demo-terminal .response {
        color: #60a5fa;
    }
    @keyframes pulse-ai {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.7;
            transform: scale(1.05);
        }
    }
    .ai-pulse {
        animation: pulse-ai 3s ease-in-out infinite;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="ai-gradient text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 relative">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur-sm rounded-full mb-6 ai-pulse">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h1 class="text-5xl md:text-6xl font-bold mb-6">
                    AI Agent Framework
                </h1>
                <p class="text-xl md:text-2xl mb-8 text-cyan-100 max-w-4xl mx-auto">
                    Transform your financial operations with intelligent AI agents that automate workflows,
                    enhance decision-making, and deliver personalized experiences at scale
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#demo" class="bg-white text-purple-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                        Try Live Demo
                    </a>
                    <a href="/api/documentation" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-purple-600 transition">
                        View API Docs
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Animated Wave -->
        <div class="absolute bottom-0 left-0 right-0">
            <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0V120Z" fill="white"/>
            </svg>
        </div>
    </section>

    <!-- Key Capabilities -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Intelligent Financial Automation</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Leverage state-of-the-art AI models to automate complex financial workflows
                    while maintaining full control and transparency
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Multi-LLM Support -->
                <div class="ai-card rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-purple-600 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Multi-LLM Provider Support</h3>
                    <p class="text-gray-600 mb-4">
                        Seamlessly switch between OpenAI GPT-4, Anthropic Claude, and other leading models
                        based on task requirements and performance needs.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="tech-badge">OpenAI</span>
                        <span class="tech-badge">Claude</span>
                        <span class="tech-badge">Custom Models</span>
                    </div>
                </div>

                <!-- Event Sourcing -->
                <div class="ai-card rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Event-Sourced Architecture</h3>
                    <p class="text-gray-600 mb-4">
                        Every AI interaction is recorded with complete audit trails, enabling
                        compliance, debugging, and continuous improvement.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="tech-badge">Audit Trail</span>
                        <span class="tech-badge">Compliance</span>
                        <span class="tech-badge">Analytics</span>
                    </div>
                </div>

                <!-- Workflow Automation -->
                <div class="ai-card rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-teal-600 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Intelligent Workflows</h3>
                    <p class="text-gray-600 mb-4">
                        Create complex, multi-step workflows with AI decision points,
                        human-in-the-loop approvals, and automatic compensation.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="tech-badge">Sagas</span>
                        <span class="tech-badge">Activities</span>
                        <span class="tech-badge">Compensation</span>
                    </div>
                </div>

                <!-- Vector Search -->
                <div class="ai-card rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Semantic Search & RAG</h3>
                    <p class="text-gray-600 mb-4">
                        Leverage vector databases for semantic search and retrieval-augmented
                        generation to provide accurate, contextual responses.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="tech-badge">Pinecone</span>
                        <span class="tech-badge">Embeddings</span>
                        <span class="tech-badge">RAG</span>
                    </div>
                </div>

                <!-- Real-time Processing -->
                <div class="ai-card rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Real-time Processing</h3>
                    <p class="text-gray-600 mb-4">
                        Stream responses for instant user feedback, with intelligent caching
                        and rate limiting for optimal performance.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="tech-badge">Streaming</span>
                        <span class="tech-badge">Redis Cache</span>
                        <span class="tech-badge">WebSockets</span>
                    </div>
                </div>

                <!-- Compliance Ready -->
                <div class="ai-card rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Compliance & Security</h3>
                    <p class="text-gray-600 mb-4">
                        Built with financial regulations in mind, featuring data privacy,
                        explainable AI, and comprehensive audit capabilities.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="tech-badge">GDPR Ready</span>
                        <span class="tech-badge">Explainable</span>
                        <span class="tech-badge">Encrypted</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture Overview -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Enterprise-Grade Architecture</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Built on proven patterns with Domain-Driven Design, Event Sourcing,
                    and Saga orchestration for reliability at scale
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- Architecture Diagram -->
                    <div>
                        <h3 class="text-2xl font-semibold mb-6">System Architecture</h3>
                        <div class="space-y-4">
                            <div class="workflow-step">
                                <div class="flex items-start">
                                    <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold mr-4 mt-1">1</div>
                                    <div>
                                        <h4 class="font-semibold mb-2">API Gateway</h4>
                                        <p class="text-gray-600">REST APIs and WebSocket connections for real-time AI interactions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-step">
                                <div class="flex items-start">
                                    <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold mr-4 mt-1">2</div>
                                    <div>
                                        <h4 class="font-semibold mb-2">AI Orchestration Layer</h4>
                                        <p class="text-gray-600">Intelligent routing between LLM providers with fallback strategies</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-step">
                                <div class="flex items-start">
                                    <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold mr-4 mt-1">3</div>
                                    <div>
                                        <h4 class="font-semibold mb-2">Event Store</h4>
                                        <p class="text-gray-600">Immutable audit trail of all AI interactions and decisions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-step">
                                <div class="flex items-start">
                                    <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold mr-4 mt-1">4</div>
                                    <div>
                                        <h4 class="font-semibold mb-2">Vector Database</h4>
                                        <p class="text-gray-600">Semantic search and knowledge base for contextual AI responses</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Key Technologies -->
                    <div>
                        <h3 class="text-2xl font-semibold mb-6">Technology Stack</h3>
                        <div class="space-y-6">
                            <div>
                                <h4 class="font-semibold mb-3">AI & Machine Learning</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span class="tech-badge">OpenAI GPT-4</span>
                                    <span class="tech-badge">Claude 3</span>
                                    <span class="tech-badge">LangChain</span>
                                    <span class="tech-badge">Pinecone</span>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-3">Infrastructure</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span class="tech-badge">Laravel</span>
                                    <span class="tech-badge">Redis</span>
                                    <span class="tech-badge">PostgreSQL</span>
                                    <span class="tech-badge">Event Sourcing</span>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-3">Orchestration</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span class="tech-badge">Laravel Workflow</span>
                                    <span class="tech-badge">Sagas</span>
                                    <span class="tech-badge">Activities</span>
                                    <span class="tech-badge">Child Workflows</span>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-3">Monitoring & Security</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span class="tech-badge">Horizon</span>
                                    <span class="tech-badge">OpenTelemetry</span>
                                    <span class="tech-badge">Encryption</span>
                                    <span class="tech-badge">Rate Limiting</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Real-World Applications</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    See how financial institutions are transforming their operations with AI agents
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Customer Service -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-blue-600 text-white rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold">Intelligent Customer Service</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        Deploy AI agents that understand context, handle complex queries,
                        and seamlessly escalate to human agents when needed.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            24/7 multilingual support
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            90% query resolution rate
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Seamless human handoff
                        </li>
                    </ul>
                </div>

                <!-- Risk Assessment -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-purple-600 text-white rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold">Automated Risk Assessment</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        AI-powered risk analysis that evaluates transactions, accounts,
                        and behaviors in real-time to prevent fraud and ensure compliance.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Real-time fraud detection
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            AML/KYC automation
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Behavioral analysis
                        </li>
                    </ul>
                </div>

                <!-- Document Processing -->
                <div class="bg-gradient-to-br from-green-50 to-teal-50 rounded-xl p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-green-600 text-white rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold">Intelligent Document Processing</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        Extract, validate, and process financial documents automatically
                        with high accuracy and compliance validation.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            OCR with 99% accuracy
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Automated data extraction
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Compliance validation
                        </li>
                    </ul>
                </div>

                <!-- Trading Assistant -->
                <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-orange-600 text-white rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold">AI Trading Assistant</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        Analyze market conditions, generate insights, and execute
                        trading strategies with AI-powered decision support.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Market sentiment analysis
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Pattern recognition
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Strategy backtesting
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive Demo -->
    <section id="demo" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Try It Live</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Experience the power of our AI agents with this interactive demo
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- Demo Terminal -->
                    <div>
                        <h3 class="text-xl font-semibold mb-4">Example Conversation</h3>
                        <div class="demo-terminal">
                            <div class="mb-4">
                                <span class="prompt">User:</span>
                                <span class="text-gray-300">I need to analyze my transaction history for tax purposes</span>
                            </div>
                            <div class="mb-4">
                                <span class="response">AI Agent:</span>
                                <span class="text-gray-300">I'll help you analyze your transaction history for tax purposes. Let me retrieve and categorize your transactions...</span>
                            </div>
                            <div class="mb-4">
                                <span class="text-gray-500">[Processing transactions...]</span>
                            </div>
                            <div class="mb-4">
                                <span class="response">AI Agent:</span>
                                <span class="text-gray-300">I've analyzed 1,247 transactions from 2024. Here's your tax summary:
- Business expenses: $45,231
- Investment income: $12,450
- Deductible donations: $3,200
Would you like me to generate a detailed report?</span>
                            </div>
                        </div>
                    </div>

                    <!-- Try It Yourself -->
                    <div>
                        <h3 class="text-xl font-semibold mb-4">Try It Yourself</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select a scenario:</label>
                                <select class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500">
                                    <option>Account balance inquiry</option>
                                    <option>Transaction analysis</option>
                                    <option>Risk assessment</option>
                                    <option>Document processing</option>
                                    <option>Investment advice</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Your question:</label>
                                <textarea class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500" rows="4" placeholder="Type your question here..."></textarea>
                            </div>
                            <button class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition shadow-lg">
                                Send to AI Agent
                            </button>
                            <p class="text-sm text-gray-500 text-center">
                                This is a demo environment. No real data is processed.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Integration Guide -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Easy Integration</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Get started with our AI Agent Framework in minutes
                </p>
            </div>

            <div class="max-w-4xl mx-auto">
                <!-- Code Example -->
                <div class="bg-gray-900 rounded-xl p-6 overflow-x-auto">
                    <pre class="text-sm"><code class="language-php text-gray-300">// Initialize AI Agent
$agent = app(AIAgentService::class);

// Create a conversation context
$context = new ConversationContext(
    conversationId: Str::uuid(),
    userId: auth()->id(),
    systemPrompt: 'You are a helpful financial assistant.'
);

// Send a message to the AI
$response = $agent->chat(
    message: 'Analyze my spending patterns',
    context: $context,
    options: [
        'model' => 'gpt-4',
        'temperature' => 0.7,
    ]
);

// Stream responses for real-time feedback
foreach ($agent->stream($message, $context) as $chunk) {
    echo $chunk; // Display to user in real-time
}</code></pre>
                </div>

                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="/api/documentation" class="text-center p-6 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-12 h-12 text-purple-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h3 class="font-semibold mb-2">API Documentation</h3>
                        <p class="text-sm text-gray-600">Complete API reference with examples</p>
                    </a>

                    <a href="https://github.com/your-org/finaegis-sdk" target="_blank" class="text-center p-6 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-12 h-12 text-purple-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                        <h3 class="font-semibold mb-2">SDKs & Libraries</h3>
                        <p class="text-sm text-gray-600">Client libraries for all platforms</p>
                    </a>

                    <a href="{{ route('about') }}" class="text-center p-6 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-12 h-12 text-purple-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <h3 class="font-semibold mb-2">Tutorials & Guides</h3>
                        <p class="text-sm text-gray-600">Step-by-step implementation guides</p>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 ai-gradient text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold mb-6">Ready to Transform Your Financial Services?</h2>
            <p class="text-xl mb-8 text-cyan-100 max-w-3xl mx-auto">
                Join leading financial institutions using AI to deliver exceptional experiences
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-purple-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                    Start Free Trial
                </a>
                <a href="mailto:info@finaegis.org" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-purple-600 transition">
                    Schedule Demo
                </a>
            </div>
        </div>
    </section>

@endsection

@push('scripts')
<script>
    // Add interactive demo functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.ai-card').forEach(card => {
            observer.observe(card);
        });
    });
</script>
@endpush

