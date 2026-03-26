<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AgentProtocol\A2ATaskController;
use App\Http\Controllers\Api\AgentProtocol\AgentAuthController;
use App\Http\Controllers\Api\AgentProtocol\AgentEscrowController;
use App\Http\Controllers\Api\AgentProtocol\AgentIdentityController;
use App\Http\Controllers\Api\AgentProtocol\AgentMessageController;
use App\Http\Controllers\Api\AgentProtocol\AgentPaymentController;
use App\Http\Controllers\Api\AgentProtocol\AgentProtocolNegotiationController;
use App\Http\Controllers\Api\AgentProtocol\AgentReputationController;
use Illuminate\Support\Facades\Route;

// AP2 well-known configuration (public)
Route::get('/.well-known/ap2-configuration', [AgentIdentityController::class, 'wellKnownConfiguration'])
    ->name('ap2.configuration');

// A2A Agent Card (public)
Route::get('/.well-known/agent.json', App\Http\Controllers\Api\AgentProtocol\A2AAgentCardController::class)
    ->name('api.a2a.agent-card');

Route::prefix('agent-protocol')->name('api.agent-protocol.')->group(function () {
    // Public endpoints
    Route::middleware('api.rate_limit:public')->group(function () {
        // Agent discovery (public)
        Route::get('/agents/discover', [AgentIdentityController::class, 'discover'])->name('agents.discover');
        Route::get('/agents/{did}', [AgentIdentityController::class, 'show'])->name('agents.show');

        // Reputation queries (public)
        Route::get('/agents/{did}/reputation', [AgentReputationController::class, 'show'])->name('reputation.show');
        Route::get('/reputation/leaderboard', [AgentReputationController::class, 'leaderboard'])->name('reputation.leaderboard');

        // Authentication endpoints (public - used to obtain authentication)
        Route::prefix('auth')->name('auth.')->group(function () {
            // DID authentication flow
            Route::post('/challenge', [AgentAuthController::class, 'getChallenge'])->name('challenge');
            Route::post('/did', [AgentAuthController::class, 'authenticateWithDID'])->name('did');

            // API key authentication
            Route::post('/api-key', [AgentAuthController::class, 'authenticateWithApiKey'])->name('api-key');

            // Session validation (uses session token, not sanctum)
            Route::post('/validate', [AgentAuthController::class, 'validateSession'])->name('validate');

            // Session revocation (uses session token)
            Route::post('/revoke', [AgentAuthController::class, 'revokeSession'])->name('revoke');

            // List available scopes
            Route::get('/scopes', [AgentAuthController::class, 'listScopes'])->name('scopes');
        });

        // Protocol version information (public)
        Route::prefix('protocol')->name('protocol.')->group(function () {
            Route::get('/versions', [AgentProtocolNegotiationController::class, 'listVersions'])->name('versions');
            Route::get('/versions/{version}/capabilities', [AgentProtocolNegotiationController::class, 'getVersionCapabilities'])
                ->name('versions.capabilities');
        });
    });

    // User-authenticated endpoints (users manage their agents via sanctum)
    Route::middleware(['auth:sanctum', 'api.rate_limit:private'])->group(function () {
        // A2A Task lifecycle endpoints
        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::post('/send', [A2ATaskController::class, 'send'])->name('send');
            Route::get('/', [A2ATaskController::class, 'list'])->name('list');
            Route::get('/{taskId}', [A2ATaskController::class, 'get'])->name('get');
            Route::post('/{taskId}/cancel', [A2ATaskController::class, 'cancel'])->name('cancel');
        });

        // Agent registration (users create/own agents)
        Route::post('/agents/register', [AgentIdentityController::class, 'register'])->name('agents.register');

        // API key management (users manage their agent's API keys)
        Route::prefix('agents/{did}/api-keys')->name('api-keys.')->group(function () {
            Route::get('/', [AgentAuthController::class, 'listApiKeys'])->name('list');
            Route::post('/', [AgentAuthController::class, 'generateApiKey'])->name('generate');
            Route::delete('/{keyId}', [AgentAuthController::class, 'revokeApiKey'])->name('revoke');
        });

        // Session management (users manage their agent's sessions)
        Route::prefix('agents/{did}/sessions')->name('sessions.')->group(function () {
            Route::get('/', [AgentAuthController::class, 'listSessions'])->name('list');
            Route::delete('/', [AgentAuthController::class, 'revokeAllSessions'])->name('revoke-all');
        });
    });

    // Agent-authenticated endpoints (agents authenticate via DID, API key, or session token)
    Route::middleware(['auth.agent', 'api.rate_limit:private'])->group(function () {
        // Agent profile management (agent updates own capabilities)
        Route::put('/agents/{did}/capabilities', [AgentIdentityController::class, 'updateCapabilities'])
            ->middleware('agent.capability:profile_management')
            ->name('agents.capabilities');

        // Payment endpoints (require payments capability and scope)
        Route::prefix('agents/{did}/payments')->name('payments.')
            ->middleware(['agent.capability:payments', 'agent.scope:payments:read,payments:write'])
            ->group(function () {
                Route::get('/', [AgentPaymentController::class, 'listPayments'])->name('list');
                Route::post('/', [AgentPaymentController::class, 'initiatePayment'])
                    ->middleware('transaction.rate_limit:agent_payment')
                    ->name('initiate');
                Route::get('/{transactionId}', [AgentPaymentController::class, 'getPaymentStatus'])->name('status');
                Route::post('/{transactionId}/confirm', [AgentPaymentController::class, 'confirmPayment'])
                    ->middleware('transaction.rate_limit:agent_payment')
                    ->name('confirm');
                Route::post('/{transactionId}/cancel', [AgentPaymentController::class, 'cancelPayment'])->name('cancel');
            });

        // Escrow endpoints (require escrow capability and scope)
        Route::prefix('escrow')->name('escrow.')
            ->middleware(['agent.capability:escrow', 'agent.scope:escrow:read,escrow:write'])
            ->group(function () {
                Route::post('/', [AgentEscrowController::class, 'create'])
                    ->middleware('transaction.rate_limit:agent_escrow')
                    ->name('create');
                Route::get('/{escrowId}', [AgentEscrowController::class, 'show'])->name('show');
                Route::post('/{escrowId}/fund', [AgentEscrowController::class, 'fund'])
                    ->middleware('transaction.rate_limit:agent_escrow')
                    ->name('fund');
                Route::post('/{escrowId}/release', [AgentEscrowController::class, 'release'])
                    ->middleware('transaction.rate_limit:agent_escrow')
                    ->name('release');
                Route::post('/{escrowId}/dispute', [AgentEscrowController::class, 'dispute'])->name('dispute');
                Route::post('/{escrowId}/resolve', [AgentEscrowController::class, 'resolveDispute'])->name('resolve');
            });

        // Messaging endpoints - A2A (require messages capability and scope)
        Route::prefix('agents/{did}/messages')->name('messages.')
            ->middleware(['agent.capability:messages', 'agent.scope:messages:read,messages:write'])
            ->group(function () {
                Route::get('/', [AgentMessageController::class, 'list'])->name('list');
                Route::post('/', [AgentMessageController::class, 'send'])
                    ->middleware('transaction.rate_limit:agent_message')
                    ->name('send');
                Route::get('/{messageId}', [AgentMessageController::class, 'show'])->name('show');
                Route::post('/{messageId}/ack', [AgentMessageController::class, 'acknowledge'])->name('acknowledge');
            });

        // Reputation management endpoints (require reputation capability)
        Route::middleware('agent.capability:reputation')->group(function () {
            Route::post('/agents/{did}/reputation/feedback', [AgentReputationController::class, 'submitFeedback'])
                ->middleware('agent.scope:reputation:write')
                ->name('reputation.feedback');
            Route::get('/agents/{did}/reputation/history', [AgentReputationController::class, 'history'])
                ->middleware('agent.scope:reputation:read')
                ->name('reputation.history');
            Route::get('/agents/{agentA}/trust/{agentB}', [AgentReputationController::class, 'evaluateTrust'])
                ->middleware('agent.scope:reputation:read')
                ->name('reputation.trust');
        });

        // Protocol negotiation endpoints (require messages capability for A2A negotiation)
        Route::prefix('agents/{did}/protocol')->name('protocol.')
            ->middleware(['agent.capability:messages', 'agent.scope:messages:read,messages:write'])
            ->group(function () {
                Route::post('/negotiate', [AgentProtocolNegotiationController::class, 'negotiate'])->name('negotiate');
                Route::get('/agreements/{otherDid}', [AgentProtocolNegotiationController::class, 'getAgreement'])
                    ->name('agreements.show');
                Route::delete('/agreements/{otherDid}', [AgentProtocolNegotiationController::class, 'revokeAgreement'])
                    ->name('agreements.revoke');
                Route::post('/agreements/{otherDid}/refresh', [AgentProtocolNegotiationController::class, 'refreshAgreement'])
                    ->name('agreements.refresh');
                Route::get('/agreements/{otherDid}/check', [AgentProtocolNegotiationController::class, 'checkAgreement'])
                    ->name('agreements.check');
            });
    });
});
