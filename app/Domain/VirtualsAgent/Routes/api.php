<?php

declare(strict_types=1);

use App\Http\Controllers\Api\VirtualsAgent\VirtualsAgentController;
use Illuminate\Support\Facades\Route;

// 404 unless VIRTUALS_AGENT_ENABLED (off by default under APP_ENV=production).
Route::prefix('v1/virtuals-agents')->name('api.virtuals-agents.')
    ->middleware(['feature:virtuals-agent.enabled', 'auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        Route::get('/', [VirtualsAgentController::class, 'index'])->name('index');
        Route::post('/onboard', [VirtualsAgentController::class, 'onboard'])->name('onboard');
        Route::get('/agdp', [VirtualsAgentController::class, 'agdp'])->name('agdp');
        Route::get('/{id}', [VirtualsAgentController::class, 'show'])->name('show');
        Route::put('/{id}/suspend', [VirtualsAgentController::class, 'suspend'])->name('suspend');
        Route::put('/{id}/activate', [VirtualsAgentController::class, 'activate'])->name('activate');
        Route::get('/{id}/transactions', [VirtualsAgentController::class, 'transactions'])->name('transactions');
    });
