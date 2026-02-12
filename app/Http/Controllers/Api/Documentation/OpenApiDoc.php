<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Info(
 *     title="FinAegis Core Banking API",
 *     version="3.1.0",
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
 */
class OpenApiDoc
{
}
