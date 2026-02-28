<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Rewards\RewardsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/rewards')->name('api.rewards.')
    ->middleware(['auth:sanctum', 'check.token.expiration'])
    ->group(function () {
        Route::get('/profile', [RewardsController::class, 'profile'])
            ->middleware('api.rate_limit:query')
            ->name('profile');

        Route::get('/quests', [RewardsController::class, 'quests'])
            ->middleware('api.rate_limit:query')
            ->name('quests');

        Route::post('/quests/{id}/complete', [RewardsController::class, 'completeQuest'])
            ->middleware('api.rate_limit:query')
            ->name('quests.complete');

        Route::get('/shop', [RewardsController::class, 'shop'])
            ->middleware('api.rate_limit:query')
            ->name('shop');

        Route::post('/shop/{id}/redeem', [RewardsController::class, 'redeemItem'])
            ->middleware('api.rate_limit:query')
            ->name('shop.redeem');
    });
