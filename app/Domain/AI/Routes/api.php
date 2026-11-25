<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AgentsDiscoveryController;
use App\Http\Controllers\Api\AI\AIQueryController;
use App\Http\Controllers\Api\AIAgentController;
use App\Http\Controllers\Api\DemoAIChatController;
use App\Http\Controllers\Api\MCPToolsController;
use Illuminate\Support\Facades\Route;

// AI Agent endpoints (protected)
Route::prefix('ai')->middleware(['auth:sanctum', 'api.rate_limit:private'])->group(function () {
    Route::post('/chat', [AIAgentController::class, 'chat'])->name('api.ai.chat');
    Route::get('/conversations', [AIAgentController::class, 'conversations'])->name('api.ai.conversations');
    Route::get('/conversations/{conversationId}', [AIAgentController::class, 'getConversation'])->name('api.ai.conversation');
    Route::delete('/conversations/{conversationId}', [AIAgentController::class, 'deleteConversation'])->name('api.ai.conversation.delete');
    Route::post('/feedback', [AIAgentController::class, 'submitFeedback'])->name('api.ai.feedback');

    // MCP Tools endpoints
    Route::prefix('mcp')->group(function () {
        Route::get('/tools', [MCPToolsController::class, 'listTools'])->name('api.ai.mcp.tools');
        Route::get('/tools/{tool}', [MCPToolsController::class, 'getToolDetails'])->name('api.ai.mcp.tool.details');
        Route::post('/tools/{tool}/execute', [MCPToolsController::class, 'executeTool'])->name('api.ai.mcp.tool.execute');
    });

    // AI Query endpoints (natural language transaction queries)
    Route::prefix('query')->group(function () {
        Route::post('/transactions', [AIQueryController::class, 'transactions'])->name('api.ai.query.transactions');
        Route::post('/spending-analysis', [AIQueryController::class, 'spendingAnalysis'])->name('api.ai.query.spending-analysis');
    });
});

// Demo AI chat endpoint (public, rate-limited). Demo only: 404 in production.
Route::post('/demo/ai-chat', [DemoAIChatController::class, 'chat'])
    ->middleware(['not.production', 'throttle:60,1'])
    ->name('api.demo.ai.chat');

// AGENTS.md Discovery endpoints (public for AI tools)
Route::prefix('agents')->group(function () {
    Route::get('/discovery', [AgentsDiscoveryController::class, 'discover'])->name('api.agents.discovery');
    Route::get('/content/{path}', [AgentsDiscoveryController::class, 'getContent'])->name('api.agents.content');
    Route::get('/summary', [AgentsDiscoveryController::class, 'summary'])->name('api.agents.summary');
});
