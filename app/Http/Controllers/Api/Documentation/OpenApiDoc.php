<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Info(
 *     title="FinAegis Core Banking API",
 *     version="1.0.0",
 *     description="Open Source Core Banking as a Service - A modern, scalable, and secure core banking platform built with Laravel 12, featuring event sourcing, domain-driven design, workflow orchestration, and quantum-resistant security measures.",
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
 */
class OpenApiDoc
{
}
