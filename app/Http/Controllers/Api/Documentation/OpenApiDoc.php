<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Info(
 *     title="FinAegis Core Banking API",
 *     version="3.4.0",
 *     description="Open Source Core Banking as a Service - A modern, scalable, and secure core banking platform built with Laravel 12, featuring 41 DDD domains, event sourcing, CQRS, cross-chain bridges, DeFi protocol integration, privacy-preserving identity, RegTech compliance, Banking-as-a-Service, and AI-powered analytics.",
 *
 * @OA\Contact(
 *         email="support@finaegis.org",
 *         name="FinAegis Support"
 *     ),
 *
 * @OA\License(
 *         name="Apache 2.0",
 *         url="https://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     description="Enter token in format: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for authentication"
 * )
 * @OA\Tag(
 *     name="Accounts",
 *     description="Account management operations"
 * )
 * @OA\Tag(
 *     name="Transactions",
 *     description="Transaction operations (deposits and withdrawals)"
 * )
 * @OA\Tag(
 *     name="Transfers",
 *     description="Money transfer operations between accounts"
 * )
 * @OA\Tag(
 *     name="Balance",
 *     description="Balance inquiry and account statistics"
 * )
 * @OA\Tag(
 *     name="AI Agent",
 *     description="AI Agent chat and conversation management for intelligent banking assistance"
 * )
 * @OA\Tag(
 *     name="MCP Tools",
 *     description="Model Context Protocol (MCP) tools for AI agent banking operations"
 * )
 * @OA\Tag(
 *     name="CrossChain",
 *     description="Cross-chain bridge operations, multi-chain transfers, and cross-chain swaps"
 * )
 * @OA\Tag(
 *     name="DeFi",
 *     description="Decentralized finance operations: DEX swaps, lending, staking, and yield optimization"
 * )
 * @OA\Tag(
 *     name="RegTech",
 *     description="Regulatory technology: MiFID II, MiCA, Travel Rule compliance and reporting"
 * )
 * @OA\Tag(
 *     name="AI Query",
 *     description="AI-powered natural language transaction queries and spending analysis"
 * )
 * @OA\Tag(
 *     name="Mobile Payments",
 *     description="Mobile payment intents, receipts, activity feed, and network status"
 * )
 * @OA\Tag(
 *     name="Partner BaaS",
 *     description="Banking-as-a-Service partner API: billing, SDKs, widgets, marketplace"
 * )
 * @OA\Tag(
 *     name="Compliance",
 *     description="Compliance management: violations, rules, certifications, and policy enforcement"
 * )
 * @OA\Tag(
 *     name="Audit",
 *     description="Audit trail: logs, events, reports, entity trails, and user activity"
 * )
 * @OA\Tag(
 *     name="Fraud Detection",
 *     description="Fraud detection: alerts, patterns, cases, and investigation workflows"
 * )
 * @OA\Tag(
 *     name="Risk Management",
 *     description="Risk analysis: user risk profiles, transaction scoring, device fingerprinting"
 * )
 * @OA\Tag(
 *     name="Module Management",
 *     description="Platform module management: enable, disable, health checks, and verification"
 * )
 * @OA\Tag(
 *     name="WebAuthn",
 *     description="WebAuthn/FIDO2 passkey authentication: challenge, authenticate, and register"
 * )
 * @OA\Tag(
 *     name="Account Deletion",
 *     description="Account deletion and data removal requests"
 * )
 * @OA\Tag(
 *     name="Banking V2",
 *     description="V2 open banking integration: bank connections, account aggregation, and transfers"
 * )
 * @OA\Tag(
 *     name="Blockchain Wallets",
 *     description="Blockchain wallet management: create, backup, generate addresses, and view transactions"
 * )
 * @OA\Tag(
 *     name="Compliance V2",
 *     description="V2 KYC/AML compliance: identity verification, document upload, risk profiling"
 * )
 * @OA\Tag(
 *     name="BaaS Onboarding",
 *     description="Financial institution onboarding: applications, document submission, and status tracking"
 * )
 * @OA\Tag(
 *     name="TrustCert",
 *     description="Trust certificates: applications, verification levels, requirements, and transaction limits"
 * )
 * @OA\Tag(
 *     name="Commerce",
 *     description="Mobile commerce: merchant discovery, QR payments, and payment processing"
 * )
 * @OA\Tag(
 *     name="Relayer",
 *     description="ERC-4337 gas relayer: user operations, gas estimation, and paymaster support"
 * )
 * @OA\Tag(
 *     name="Mobile Wallet",
 *     description="Mobile wallet: token balances, transaction history, address management, and transfers"
 * )
 * @OA\Tag(
 *     name="Treasury",
 *     description="Treasury management: liquidity forecasting, alerts, and workflow orchestration"
 * )
 * @OA\Tag(
 *     name="Lending",
 *     description="P2P lending: loan applications, payments, early settlement, and loan management"
 * )
 */
class OpenApiDoc
{
}
