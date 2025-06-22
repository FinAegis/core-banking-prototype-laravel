<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // GCU Wallet Routes
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/bank-allocation', function () {
            return view('wallet.bank-allocation');
        })->name('bank-allocation');
        
        Route::get('/voting', function () {
            return view('wallet.voting');
        })->name('voting');
        
        Route::get('/transactions', function () {
            return view('wallet.transactions');
        })->name('transactions');
        
        // Placeholder routes for quick actions
        Route::get('/deposit', function () {
            return view('wallet.deposit');
        })->name('deposit');
        
        Route::get('/withdraw', function () {
            return view('wallet.withdraw');
        })->name('withdraw');
        
        Route::get('/transfer', function () {
            return view('wallet.transfer');
        })->name('transfer');
        
        Route::get('/convert', function () {
            return view('wallet.convert');
        })->name('convert');
    });
});

// API Documentation route
Route::get('/docs/api-docs.json', function () {
    $path = storage_path('api-docs/api-docs.json');
    if (!file_exists($path)) {
        abort(404, 'API documentation not found. Run: php artisan l5-swagger:generate');
    }
    return response()->json(json_decode(file_get_contents($path), true));
});
