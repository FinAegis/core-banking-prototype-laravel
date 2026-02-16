<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Mobile\UserPreferencesController;
use App\Http\Controllers\Api\MobileController;
use Illuminate\Support\Facades\Route;

// App status endpoint (no auth required, used by mobile for version/maintenance check)
Route::prefix('v1/app')->name('api.app.')->group(function () {
    Route::get('/status', [MobileController::class, 'getAppStatus'])->name('status');
});

Route::prefix('mobile')->name('api.mobile.')->group(function () {
    // Public endpoints (no auth required)
    Route::get('/config', [MobileController::class, 'getConfig'])->name('config');

    // Biometric authentication (no auth required - this IS the auth)
    // Rate limited to prevent brute force attacks (10 requests per minute)
    Route::prefix('auth/biometric')
        ->middleware('throttle:10,1')
        ->name('auth.biometric.')
        ->group(function () {
            Route::post('/challenge', [MobileController::class, 'getBiometricChallenge'])->name('challenge');
            Route::post('/verify', [MobileController::class, 'verifyBiometric'])->name('verify');
        });

    // Protected endpoints (require authentication)
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        // Device management
        Route::prefix('devices')->name('devices.')->group(function () {
            Route::get('/', [MobileController::class, 'listDevices'])->name('index');
            Route::post('/', [MobileController::class, 'registerDevice'])->name('register');
            Route::delete('/all', [MobileController::class, 'bulkRemoveDevices'])->name('bulk-destroy');
            Route::get('/{id}', [MobileController::class, 'getDevice'])->name('show');
            Route::delete('/{id}', [MobileController::class, 'unregisterDevice'])->name('destroy');
            Route::patch('/{id}/token', [MobileController::class, 'updatePushToken'])->name('token');

            // Device security actions
            Route::post('/{id}/block', [MobileController::class, 'blockDevice'])->name('block');
            Route::post('/{id}/unblock', [MobileController::class, 'unblockDevice'])->name('unblock');
            Route::post('/{id}/trust', [MobileController::class, 'trustDevice'])->name('trust');
        });

        // Biometric management (requires auth to enable/disable)
        Route::prefix('auth/biometric')->name('auth.biometric.')->group(function () {
            Route::post('/enable', [MobileController::class, 'enableBiometric'])->name('enable');
            Route::delete('/disable', [MobileController::class, 'disableBiometric'])->name('disable');
        });

        // Token refresh
        Route::post('/auth/refresh', [MobileController::class, 'refreshToken'])->name('auth.refresh');

        // Session management
        Route::prefix('sessions')->name('sessions.')->group(function () {
            Route::get('/', [MobileController::class, 'listSessions'])->name('index');
            Route::delete('/{id}', [MobileController::class, 'revokeSession'])->name('revoke');
            Route::delete('/', [MobileController::class, 'revokeAllSessions'])->name('revoke-all');
        });

        // Push notifications
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [MobileController::class, 'getNotifications'])->name('index');
            Route::post('/{id}/read', [MobileController::class, 'markNotificationRead'])->name('read');
            Route::post('/read-all', [MobileController::class, 'markAllNotificationsRead'])->name('read-all');

            // Notification preferences
            Route::get('/preferences', [MobileController::class, 'getNotificationPreferences'])->name('preferences.index');
            Route::put('/preferences', [MobileController::class, 'updateNotificationPreferences'])->name('preferences.update');
        });
    });
});

// User preferences (v3.3.4)
Route::prefix('v1/user')->name('api.user.')
    ->middleware(['auth:sanctum', 'check.token.expiration'])
    ->group(function () {
        Route::get('/preferences', [UserPreferencesController::class, 'show'])->name('preferences.show');
        Route::patch('/preferences', [UserPreferencesController::class, 'update'])->name('preferences.update');
    });
