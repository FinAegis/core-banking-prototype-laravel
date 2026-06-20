<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Privacy\DelegatedProofController;
use App\Http\Controllers\Api\Privacy\PrivacyController;
use App\Http\Controllers\Api\Privacy\RailgunEngineConfigController;
use App\Http\Controllers\Api\Privacy\RailgunRpcProxyController;
use App\Http\Controllers\Api\Privacy\RailgunWalletRegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/privacy')->name('api.privacy.')->group(function () {
    // Public endpoint for supported networks
    Route::get('/networks', [PrivacyController::class, 'getNetworks'])->name('networks');

    // Public endpoint for delegated proof types
    Route::get('/delegated-proof-types', [DelegatedProofController::class, 'getSupportedTypes'])
        ->name('delegated-proof-types');

    // Public endpoint for SRS manifest (mobile needs this before auth)
    Route::get('/srs-manifest', [PrivacyController::class, 'getSrsManifest'])->name('srs-manifest');

    // Public endpoint for per-chain SRS download URL
    Route::get('/srs-url', [PrivacyController::class, 'getSrsUrl'])->name('srs-url');

    // Public endpoint for privacy pool statistics (v3.3.4)
    Route::get('/pool-stats', [PrivacyController::class, 'getPoolStats'])->name('pool-stats');

    // Non-custodial RPC proxy — authed by a SHORT-LIVED SIGNED URL minted by
    // engine-config (not Sanctum: the SDK's provider takes a plain URL string and
    // cannot send a bearer header). Injects the server-side provider key + whitelists
    // read methods. See RailgunRpcProxyController.
    Route::post('/rpc/{network}', RailgunRpcProxyController::class)
        ->middleware(['signed', 'throttle:railgun-rpc'])
        ->name('rpc');

    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        // Non-custodial wallet registration — device registers its public 0zk
        // address; no seed material is ever sent or stored (Phase 1).
        Route::post('/wallet/register', RailgunWalletRegistrationController::class)->name('wallet.register');

        // On-device engine bootstrap — config for startRailgunEngine + loadProvider
        // pointed at our self-hosted infra (POI node, artifact mirror, RPC).
        Route::get('/engine-config', RailgunEngineConfigController::class)->name('engine-config');

        Route::get('/merkle-root', [PrivacyController::class, 'getMerkleRoot'])->name('merkle-root');
        Route::post('/merkle-path', [PrivacyController::class, 'getMerklePath'])->middleware('throttle:10,1')->name('merkle-path');
        Route::post('/verify-commitment', [PrivacyController::class, 'verifyCommitment'])->middleware('throttle:10,1')->name('verify-commitment');
        Route::post('/sync', [PrivacyController::class, 'syncTree'])
            ->middleware('transaction.rate_limit:privacy_sync')
            ->name('sync');

        // Delegated Proof Generation (v2.6.0)
        Route::post('/delegated-proof', [DelegatedProofController::class, 'requestProof'])
            ->middleware('transaction.rate_limit:delegated_proof')
            ->name('delegated-proof.request');
        Route::get('/delegated-proof/{jobId}', [DelegatedProofController::class, 'getProofStatus'])
            ->name('delegated-proof.status');
        Route::get('/delegated-proofs', [DelegatedProofController::class, 'listProofJobs'])
            ->name('delegated-proofs.list');
        Route::delete('/delegated-proof/{jobId}', [DelegatedProofController::class, 'cancelProofJob'])
            ->name('delegated-proof.cancel');

        // SRS download tracking for analytics
        Route::post('/srs-downloaded', [PrivacyController::class, 'trackSrsDownload'])->name('srs-downloaded');

        // Shielded balances and transactions
        Route::get('/balances', [PrivacyController::class, 'getShieldedBalances'])->name('balances');
        Route::get('/total-balance', [PrivacyController::class, 'getTotalShieldedBalance'])->name('total-balance');
        Route::get('/transactions', [PrivacyController::class, 'getPrivacyTransactions'])->name('transactions');

        // Shield/Unshield/Transfer operations
        Route::post('/shield', [PrivacyController::class, 'shield'])
            ->middleware('transaction.rate_limit:delegated_proof')
            ->name('shield');
        Route::post('/unshield', [PrivacyController::class, 'unshield'])
            ->middleware('transaction.rate_limit:delegated_proof')
            ->name('unshield');
        Route::post('/transfer', [PrivacyController::class, 'privateTransfer'])
            ->middleware('transaction.rate_limit:delegated_proof')
            ->name('transfer');

        // Viewing key
        Route::get('/viewing-key', [PrivacyController::class, 'getViewingKey'])->name('viewing-key');

        // Proof of Innocence
        Route::post('/proof-of-innocence', [PrivacyController::class, 'generateProofOfInnocence'])
            ->middleware('transaction.rate_limit:delegated_proof')
            ->name('proof-of-innocence.generate');
        Route::get('/proof-of-innocence/{proofId}/verify', [PrivacyController::class, 'verifyProofOfInnocence'])
            ->name('proof-of-innocence.verify');

        // SRS status
        Route::get('/srs-status', [PrivacyController::class, 'getSrsStatus'])->name('srs-status');

        // Transaction calldata
        Route::get('/transaction-calldata/{txHash}', [PrivacyController::class, 'getTransactionCalldata'])
            ->name('transaction-calldata');
        Route::put('/transactions/{transactionId}/tx-hash', [PrivacyController::class, 'updateTransactionHash'])
            ->name('transaction.update-hash');
    });
});
