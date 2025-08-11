<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="AIMessage",
 *     type="object",
 *     title="AI Message",
 *     description="AI conversation message",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", description="The message content", example="What is my account balance?"),
 *     @OA\Property(property="conversation_id", type="string", format="uuid", description="Conversation ID for context", example="conv_123e4567-e89b-12d3-a456"),
 *     @OA\Property(property="context", type="object", description="Additional context for the AI", example={"account_id": "acct_123", "session_type": "web"}),
 *     @OA\Property(property="model", type="string", enum={"gpt-4", "gpt-3.5-turbo", "claude-3", "llama-2"}, description="AI model to use", example="gpt-4"),
 *     @OA\Property(property="temperature", type="number", minimum=0, maximum=2, description="Creativity level", example=0.7),
 *     @OA\Property(property="stream", type="boolean", description="Enable streaming responses", example=false),
 *     @OA\Property(property="enable_tools", type="boolean", description="Allow AI to use MCP tools", example=true),
 *     @OA\Property(property="max_tokens", type="integer", description="Maximum response length", example=500)
 * )
 *
 * @OA\Schema(
 *     schema="AIResponse",
 *     type="object",
 *     title="AI Response",
 *     description="AI agent response",
 *     @OA\Property(property="message_id", type="string", description="Unique message ID", example="msg_abc123xyz"),
 *     @OA\Property(property="conversation_id", type="string", description="Conversation ID", example="conv_123e4567"),
 *     @OA\Property(property="content", type="string", description="AI response content", example="Your current balance is $12,456.78"),
 *     @OA\Property(property="confidence", type="number", minimum=0, maximum=1, description="Confidence score", example=0.92),
 *     @OA\Property(property="tools_used", type="array", @OA\Items(type="string"), description="MCP tools used", example={"AccountBalanceTool", "TransactionHistoryTool"}),
 *     @OA\Property(property="requires_action", type="boolean", description="Whether user action is required", example=false),
 *     @OA\Property(
 *         property="actions",
 *         type="array",
 *         description="Required actions",
 *         @OA\Items(
 *             @OA\Property(property="type", type="string", example="transfer"),
 *             @OA\Property(property="description", type="string", example="Transfer $500 to John Smith"),
 *             @OA\Property(property="parameters", type="object"),
 *             @OA\Property(property="confidence", type="number", example=0.89)
 *         )
 *     ),
 *     @OA\Property(property="metadata", type="object", description="Response metadata")
 * )
 *
 * @OA\Schema(
 *     schema="MCPTool",
 *     type="object",
 *     title="MCP Tool",
 *     description="Model Context Protocol tool definition",
 *     @OA\Property(property="name", type="string", description="Tool name", example="get_account_balance"),
 *     @OA\Property(property="description", type="string", description="Tool description", example="Retrieve account balance"),
 *     @OA\Property(property="category", type="string", description="Tool category", example="account_management"),
 *     @OA\Property(property="parameters", type="object", description="Tool parameters schema"),
 *     @OA\Property(property="requires_auth", type="boolean", description="Authentication required", example=true),
 *     @OA\Property(property="requires_2fa", type="boolean", description="2FA required", example=false),
 *     @OA\Property(property="rate_limit", type="integer", description="Rate limit per minute", example=100),
 *     @OA\Property(property="cache_ttl", type="integer", description="Cache TTL in seconds", example=60),
 *     @OA\Property(property="ml_enabled", type="boolean", description="ML features enabled", example=false),
 *     @OA\Property(property="real_time", type="boolean", description="Real-time processing", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="MCPToolExecution",
 *     type="object",
 *     title="MCP Tool Execution",
 *     description="MCP tool execution request",
 *     required={"parameters"},
 *     @OA\Property(property="parameters", type="object", description="Tool-specific parameters", example={"account_id": "acct_123", "include_pending": true}),
 *     @OA\Property(property="timeout", type="integer", description="Execution timeout in ms", example=5000),
 *     @OA\Property(property="async", type="boolean", description="Execute asynchronously", example=false)
 * )
 *
 * @OA\Schema(
 *     schema="MCPToolResult",
 *     type="object",
 *     title="MCP Tool Result",
 *     description="MCP tool execution result",
 *     @OA\Property(property="success", type="boolean", description="Execution success", example=true),
 *     @OA\Property(property="tool", type="string", description="Tool name", example="get_account_balance"),
 *     @OA\Property(property="result", type="object", description="Tool execution result"),
 *     @OA\Property(property="execution_time_ms", type="integer", description="Execution time", example=145),
 *     @OA\Property(property="error", type="string", description="Error message if failed"),
 *     @OA\Property(property="metadata", type="object", description="Execution metadata")
 * )
 *
 * @OA\Schema(
 *     schema="AIConversation",
 *     type="object",
 *     title="AI Conversation",
 *     description="AI conversation thread",
 *     @OA\Property(property="id", type="string", format="uuid", description="Conversation ID", example="conv_123e4567"),
 *     @OA\Property(property="title", type="string", description="Conversation title", example="Account Balance Inquiry"),
 *     @OA\Property(property="user_id", type="integer", description="User ID", example=123),
 *     @OA\Property(
 *         property="messages",
 *         type="array",
 *         description="Conversation messages",
 *         @OA\Items(
 *             @OA\Property(property="role", type="string", enum={"user", "assistant", "system"}, example="user"),
 *             @OA\Property(property="content", type="string", example="What is my balance?"),
 *             @OA\Property(property="timestamp", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Property(property="context", type="object", description="Conversation context"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AIFeedback",
 *     type="object",
 *     title="AI Feedback",
 *     description="User feedback for AI response",
 *     required={"message_id", "rating"},
 *     @OA\Property(property="message_id", type="string", description="Message ID to provide feedback for", example="msg_abc123"),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, description="Rating 1-5", example=5),
 *     @OA\Property(property="feedback", type="string", description="Optional text feedback", example="Very helpful response"),
 *     @OA\Property(property="issues", type="array", @OA\Items(type="string"), description="Reported issues", example={"incorrect_info", "too_verbose"})
 * )
 *
 * @OA\Schema(
 *     schema="MCPToolRegistration",
 *     type="object",
 *     title="MCP Tool Registration",
 *     description="Register a new MCP tool",
 *     required={"name", "description", "endpoint", "parameters"},
 *     @OA\Property(property="name", type="string", description="Tool name", example="custom_analysis_tool"),
 *     @OA\Property(property="description", type="string", description="Tool description", example="Custom financial analysis tool"),
 *     @OA\Property(property="endpoint", type="string", format="url", description="Tool endpoint URL", example="https://api.example.com/tool"),
 *     @OA\Property(property="parameters", type="object", description="Parameter schema"),
 *     @OA\Property(property="category", type="string", description="Tool category", example="analysis"),
 *     @OA\Property(property="authentication", type="object", description="Authentication configuration"),
 *     @OA\Property(property="rate_limit", type="integer", description="Rate limit", example=50),
 *     @OA\Property(property="timeout", type="integer", description="Timeout in ms", example=10000)
 * )
 *
 * @OA\Schema(
 *     schema="AIWorkflow",
 *     type="object",
 *     title="AI Workflow",
 *     description="AI-driven workflow definition",
 *     @OA\Property(property="id", type="string", description="Workflow ID", example="wf_789xyz"),
 *     @OA\Property(property="type", type="string", description="Workflow type", example="customer_service"),
 *     @OA\Property(property="status", type="string", enum={"pending", "running", "completed", "failed"}, example="running"),
 *     @OA\Property(
 *         property="steps",
 *         type="array",
 *         description="Workflow steps",
 *         @OA\Items(
 *             @OA\Property(property="name", type="string", example="analyze_request"),
 *             @OA\Property(property="type", type="string", example="ai_analysis"),
 *             @OA\Property(property="status", type="string", example="completed"),
 *             @OA\Property(property="result", type="object")
 *         )
 *     ),
 *     @OA\Property(property="ai_config", type="object", description="AI configuration"),
 *     @OA\Property(property="human_intervention", type="object", description="Human-in-the-loop config"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="completed_at", type="string", format="date-time")
 * )
 */
class AISchemas
{
}
