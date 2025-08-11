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
                    <a href="{{ route('demo.ai-agent') }}" class="inline-block bg-white text-purple-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl text-center">
                        Try Live Demo
                    </a>
                    <a href="/api/documentation" class="inline-block border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-purple-600 transition text-center">
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
                                    <span class="tech-badge">OpenAI Integration</span>
                                    <span class="tech-badge">Anthropic Claude</span>
                                    <span class="tech-badge">MCP Protocol</span>
                                    <span class="tech-badge">Vector Store</span>
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
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Implemented AI Workflows</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Production-ready workflows with event sourcing, sagas, and MCP tool integration
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
                        <h3 class="text-2xl font-semibold">Customer Service Workflow</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        CustomerServiceWorkflow handles complex banking queries with MCP tool integration,
                        automated account operations, and human escalation when confidence is low.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Automated response capabilities
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Context-aware query handling
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Human escalation support
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
                        <h3 class="text-2xl font-semibold">Risk Assessment Saga</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        RiskAssessmentSaga orchestrates FraudDetectionWorkflow, CreditRiskWorkflow,
                        and MarketRiskWorkflow with compensation patterns for comprehensive analysis.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Fraud detection workflows
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            KYC/AML integration
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Risk scoring models
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
                        <h3 class="text-2xl font-semibold">Trading Agent Workflow</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        TradingAgentWorkflow performs market analysis with technical indicators,
                        generates strategies, and executes trades with confidence thresholds.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            RSI, MACD, SMA indicators
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Momentum & mean reversion
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            VaR risk assessment
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
                        <h3 class="text-2xl font-semibold">Compliance Workflow</h3>
                    </div>
                    <p class="text-gray-700 mb-4">
                        ComplianceWorkflow automates KYC verification and AML screening
                        with MCP tools for regulatory compliance and audit trails.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            KycTool for identity verification
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            AML screening automation
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Audit trail generation
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
                <div class="text-center">
                    <h3 class="text-2xl font-semibold mb-4">Experience AI-Powered Banking</h3>
                    <p class="text-gray-600 mb-8 max-w-2xl mx-auto">
                        Our interactive demo showcases how AI agents handle real banking scenarios.
                        Try conversations about account balances, transactions, transfers, and more.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 max-w-3xl mx-auto">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z"></path>
                            </svg>
                            <h4 class="font-semibold">Natural Language</h4>
                            <p class="text-sm text-gray-600">Chat naturally about your banking needs</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <h4 class="font-semibold">Smart Context</h4>
                            <p class="text-sm text-gray-600">AI understands banking context</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <h4 class="font-semibold">Instant Response</h4>
                            <p class="text-sm text-gray-600">Real-time processing with MCP tools</p>
                        </div>
                    </div>
                    <a href="{{ route('demo.ai-agent') }}" class="inline-block bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-8 py-4 rounded-lg text-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition shadow-lg hover:shadow-xl">
                        Launch Interactive Demo
                    </a>
                    <p class="text-sm text-gray-500 mt-4">
                        Safe demo environment - No authentication required
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Advanced Features -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Advanced AI Capabilities</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Enterprise features for complex financial operations
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Multi-Agent Coordination -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Multi-Agent Coordination</h3>
                    <p class="text-gray-600 mb-4">
                        Orchestrates multiple AI agents (Customer Service, Compliance, Risk, Trading) with consensus building and weighted voting.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Agent registry & discovery</li>
                        <li>• Task delegation</li>
                        <li>• Consensus algorithms</li>
                    </ul>
                </div>

                <!-- Human-in-the-Loop -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Human-in-the-Loop</h3>
                    <p class="text-gray-600 mb-4">
                        HumanInTheLoopWorkflow manages AI decisions requiring oversight with configurable confidence and value thresholds.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Confidence thresholds</li>
                        <li>• Value-based escalation</li>
                        <li>• Approval workflows</li>
                    </ul>
                </div>

                <!-- MCP Tool Integration -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">MCP Tool Server</h3>
                    <p class="text-gray-600 mb-4">
                        Model Context Protocol server exposes 15+ banking tools for account operations, transactions, and compliance.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Account & balance tools</li>
                        <li>• Transaction tools</li>
                        <li>• KYC/AML tools</li>
                    </ul>
                </div>

                <!-- Event Sourcing -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">AI Event Sourcing</h3>
                    <p class="text-gray-600 mb-4">
                        AIInteractionAggregate records every LLM interaction with complete audit trails for compliance and analytics.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Immutable event store</li>
                        <li>• Decision tracking</li>
                        <li>• Compliance audit trail</li>
                    </ul>
                </div>

                <!-- Technical Analysis -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
                    <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-rose-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Technical Analysis</h3>
                    <p class="text-gray-600 mb-4">
                        Activities for technical indicators (RSI, MACD, SMA), pattern recognition, and automated trading strategies.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• CalculateRSIActivity</li>
                        <li>• CalculateMACDActivity</li>
                        <li>• VaRCalculationActivity</li>
                    </ul>
                </div>

                <!-- Saga Orchestration -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition">
                    <div class="w-12 h-12 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Saga Pattern</h3>
                    <p class="text-gray-600 mb-4">
                        Complex workflows with automatic compensation for failures, ensuring data consistency across operations.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-1">
                        <li>• Automatic rollback</li>
                        <li>• Compensation handlers</li>
                        <li>• Transaction integrity</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- MCP Integration Section -->
    <section class="py-20 bg-gradient-to-br from-indigo-50 to-purple-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Model Context Protocol (MCP)</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Bridging AI models with banking operations through standardized tool interfaces
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- MCP Overview -->
                <div>
                    <h3 class="text-2xl font-semibold mb-6">How MCP Works</h3>
                    <div class="space-y-4">
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-semibold text-lg mb-2">1. Tool Discovery</h4>
                            <p class="text-gray-600">
                                AI agents discover available banking tools through the MCP server, including account operations, transactions, and compliance checks.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-semibold text-lg mb-2">2. Context Building</h4>
                            <p class="text-gray-600">
                                The protocol provides rich context about user permissions, account states, and regulatory requirements to the AI model.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-semibold text-lg mb-2">3. Tool Execution</h4>
                            <p class="text-gray-600">
                                AI agents execute banking operations through MCP tools with built-in validation, security checks, and audit logging.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-semibold text-lg mb-2">4. Response Formatting</h4>
                            <p class="text-gray-600">
                                Results are formatted with confidence scores, explanations, and next action suggestions for seamless user experience.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Available Tools -->
                <div>
                    <h3 class="text-2xl font-semibold mb-6">Banking Tools Available</h3>
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="grid grid-cols-1 gap-4">
                            <!-- Account Tools -->
                            <div class="border-l-4 border-blue-500 pl-4">
                                <h4 class="font-semibold">Account Management</h4>
                                <p class="text-sm text-gray-600">GetAccountBalance, CreateAccount, CloseAccount, FreezeAccount</p>
                            </div>
                            <!-- Transaction Tools -->
                            <div class="border-l-4 border-green-500 pl-4">
                                <h4 class="font-semibold">Transactions</h4>
                                <p class="text-sm text-gray-600">TransferMoney, GetTransactionHistory, CancelTransaction, RefundTransaction</p>
                            </div>
                            <!-- Compliance Tools -->
                            <div class="border-l-4 border-purple-500 pl-4">
                                <h4 class="font-semibold">Compliance</h4>
                                <p class="text-sm text-gray-600">KycTool, AmlScreeningTool, RiskAssessmentTool, ComplianceCheckTool</p>
                            </div>
                            <!-- Analytics Tools -->
                            <div class="border-l-4 border-orange-500 pl-4">
                                <h4 class="font-semibold">Analytics</h4>
                                <p class="text-sm text-gray-600">SpendingAnalysisTool, CashFlowTool, TrendAnalysisTool, ReportGeneratorTool</p>
                            </div>
                            <!-- Trading Tools -->
                            <div class="border-l-4 border-red-500 pl-4">
                                <h4 class="font-semibold">Trading & Exchange</h4>
                                <p class="text-sm text-gray-600">GetExchangeRate, ExecuteTrade, GetMarketData, PortfolioAnalysisTool</p>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-700">
                                <strong>Security First:</strong> All MCP tools include built-in authentication, authorization, rate limiting, and comprehensive audit logging.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Example -->
            <div class="mt-12 bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-semibold mb-4">MCP Tool Implementation Example</h3>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm"><code class="language-json text-gray-300"><span class="text-gray-500">// MCP Tool Definition</span>
{
  <span class="text-yellow-400">"name"</span>: <span class="text-green-400">"GetAccountBalance"</span>,
  <span class="text-yellow-400">"description"</span>: <span class="text-green-400">"Retrieve the current balance for a user's account"</span>,
  <span class="text-yellow-400">"parameters"</span>: {
    <span class="text-yellow-400">"account_id"</span>: {
      <span class="text-yellow-400">"type"</span>: <span class="text-green-400">"string"</span>,
      <span class="text-yellow-400">"description"</span>: <span class="text-green-400">"The account identifier"</span>,
      <span class="text-yellow-400">"required"</span>: <span class="text-pink-400">true</span>
    },
    <span class="text-yellow-400">"currency"</span>: {
      <span class="text-yellow-400">"type"</span>: <span class="text-green-400">"string"</span>,
      <span class="text-yellow-400">"description"</span>: <span class="text-green-400">"Currency code (USD, EUR, GCU)"</span>,
      <span class="text-yellow-400">"default"</span>: <span class="text-green-400">"USD"</span>
    }
  },
  <span class="text-yellow-400">"returns"</span>: {
    <span class="text-yellow-400">"balance"</span>: <span class="text-green-400">"number"</span>,
    <span class="text-yellow-400">"currency"</span>: <span class="text-green-400">"string"</span>,
    <span class="text-yellow-400">"available"</span>: <span class="text-green-400">"number"</span>,
    <span class="text-yellow-400">"pending"</span>: <span class="text-green-400">"number"</span>
  }
}</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Integration Guide -->
    <section class="py-20 bg-gray-50">
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
                    <pre class="text-sm"><code class="language-php text-gray-300"><span class="text-gray-500">// Initialize AI Agent Service</span>
<span class="text-purple-400">$agent</span> = <span class="text-cyan-400">app</span>(<span class="text-green-400">AIAgentService</span>::<span class="text-blue-400">class</span>);

<span class="text-gray-500">// Create conversation context</span>
<span class="text-purple-400">$context</span> = <span class="text-blue-400">new</span> <span class="text-green-400">ConversationContext</span>(
    <span class="text-orange-400">conversationId:</span> <span class="text-green-400">Str</span>::<span class="text-cyan-400">uuid</span>(),
    <span class="text-orange-400">userId:</span> <span class="text-cyan-400">auth</span>()-><span class="text-cyan-400">id</span>(),
    <span class="text-orange-400">systemPrompt:</span> <span class="text-yellow-400">'You are a helpful financial assistant.'</span>
);

<span class="text-gray-500">// Send message to AI agent</span>
<span class="text-purple-400">$response</span> = <span class="text-purple-400">$agent</span>-><span class="text-cyan-400">chat</span>(
    <span class="text-orange-400">message:</span> <span class="text-yellow-400">'Analyze my spending patterns'</span>,
    <span class="text-orange-400">context:</span> <span class="text-purple-400">$context</span>,
    <span class="text-orange-400">options:</span> [
        <span class="text-yellow-400">'model'</span> => <span class="text-yellow-400">'gpt-4'</span>,
        <span class="text-yellow-400">'temperature'</span> => <span class="text-pink-400">0.7</span>,
    ]
);

<span class="text-gray-500">// Stream responses for real-time feedback</span>
<span class="text-blue-400">foreach</span> (<span class="text-purple-400">$agent</span>-><span class="text-cyan-400">stream</span>(<span class="text-purple-400">$message</span>, <span class="text-purple-400">$context</span>) <span class="text-blue-400">as</span> <span class="text-purple-400">$chunk</span>) {
    <span class="text-blue-400">echo</span> <span class="text-purple-400">$chunk</span>; <span class="text-gray-500">// Display to user in real-time</span>
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

                    <a href="{{ route('developers.show', 'sdks') }}" class="text-center p-6 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
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

