<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BlockchainWalletController;
use App\Http\Controllers\Api\HardwareWalletController;
use App\Http\Controllers\Api\MultiSigWalletController;
use App\Http\Controllers\Api\Wallet\MobileWalletController;
use Illuminate\Support\Facades\Route;

// Blockchain wallet endpoints
Route::prefix('blockchain-wallets')->middleware(['auth:sanctum', 'check.token.expiration', 'sub_product:blockchain'])->group(function () {
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::get('/', [BlockchainWalletController::class, 'index']);
        Route::get('/{walletId}', [BlockchainWalletController::class, 'show']);
        Route::get('/{walletId}/addresses', [BlockchainWalletController::class, 'addresses']);
        Route::get('/{walletId}/transactions', [BlockchainWalletController::class, 'transactions']);
    });

    Route::middleware(['transaction.rate_limit:blockchain', 'idempotency'])->group(function () {
        Route::post('/', [BlockchainWalletController::class, 'store']);
        Route::put('/{walletId}', [BlockchainWalletController::class, 'update']);
        Route::post('/{walletId}/addresses', [BlockchainWalletController::class, 'generateAddress']);
        Route::post('/{walletId}/backup', [BlockchainWalletController::class, 'createBackup']);
    });

    Route::post('/generate-mnemonic', [BlockchainWalletController::class, 'generateMnemonic']);
});

// Hardware Wallet endpoints (v2.1.0)
Route::prefix('hardware-wallet')->name('api.hardware-wallet.')->group(function () {
    // Public endpoint for supported devices/chains
    Route::get('/supported', [HardwareWalletController::class, 'supported'])
        ->name('supported');

    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration', 'sub_product:blockchain'])->group(function () {
        // Device registration
        Route::post('/register', [HardwareWalletController::class, 'register'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('register');

        // Signing requests
        Route::post('/signing-request', [HardwareWalletController::class, 'createSigningRequest'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('signing-request.create');

        Route::get('/signing-request/{id}', [HardwareWalletController::class, 'getSigningRequestStatus'])
            ->name('signing-request.status');

        Route::post('/signing-request/{id}/submit', [HardwareWalletController::class, 'submitSignature'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('signing-request.submit');

        Route::post('/signing-request/{id}/cancel', [HardwareWalletController::class, 'cancelSigningRequest'])
            ->name('signing-request.cancel');

        // Associations management
        Route::get('/associations', [HardwareWalletController::class, 'listAssociations'])
            ->name('associations.list');

        Route::delete('/associations/{uuid}', [HardwareWalletController::class, 'removeAssociation'])
            ->name('associations.remove');
    });
});

// Multi-Signature Wallet endpoints (v2.1.0)
Route::prefix('multi-sig')->name('api.multi-sig.')->group(function () {
    // Public endpoint for supported configuration
    Route::get('/supported', [MultiSigWalletController::class, 'getSupported'])
        ->name('supported');

    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration', 'sub_product:blockchain'])->group(function () {
        // Wallet management
        Route::post('/wallets', [MultiSigWalletController::class, 'createWallet'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('wallets.create');

        Route::get('/wallets', [MultiSigWalletController::class, 'listWallets'])
            ->name('wallets.list');

        Route::get('/wallets/{id}', [MultiSigWalletController::class, 'getWallet'])
            ->name('wallets.show');

        Route::post('/wallets/{id}/signers', [MultiSigWalletController::class, 'addSigner'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('wallets.signers.add');

        // Approval requests
        Route::post('/wallets/{id}/approval-requests', [MultiSigWalletController::class, 'createApprovalRequest'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('wallets.approval-requests.create');

        Route::post('/approval-requests/{id}/approve', [MultiSigWalletController::class, 'submitApproval'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('approval-requests.approve');

        Route::post('/approval-requests/{id}/reject', [MultiSigWalletController::class, 'rejectApproval'])
            ->name('approval-requests.reject');

        Route::post('/approval-requests/{id}/broadcast', [MultiSigWalletController::class, 'broadcastTransaction'])
            ->middleware('transaction.rate_limit:blockchain')
            ->name('approval-requests.broadcast');

        // Pending approvals for current user
        Route::get('/pending-approvals', [MultiSigWalletController::class, 'getPendingApprovals'])
            ->name('pending-approvals');
    });
});

// Mobile Wallet API (v2.10.0)
Route::prefix('v1/wallet')->name('mobile.wallet.')
    ->middleware(['auth:sanctum', 'check.token.expiration'])
    ->group(function () {
        Route::get('/tokens', [MobileWalletController::class, 'tokens'])
            ->middleware('api.rate_limit:query')
            ->name('tokens');
        Route::get('/balances', [MobileWalletController::class, 'balances'])
            ->middleware('api.rate_limit:query')
            ->name('balances');
        Route::get('/state', [MobileWalletController::class, 'state'])
            ->middleware('api.rate_limit:query')
            ->name('state');
        Route::get('/addresses', [MobileWalletController::class, 'addresses'])
            ->middleware('api.rate_limit:query')
            ->name('addresses');
        Route::get('/transactions', [MobileWalletController::class, 'transactions'])
            ->middleware('api.rate_limit:query')
            ->name('transactions');
        Route::get('/transactions/{id}', [MobileWalletController::class, 'transactionDetail'])
            ->middleware('api.rate_limit:query')
            ->name('transactions.detail');
        Route::post('/transactions/send', [MobileWalletController::class, 'send'])
            ->middleware(['transaction.rate_limit:payment_intent', 'idempotency'])
            ->name('transactions.send');
    });
