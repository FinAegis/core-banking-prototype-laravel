<?php

use Illuminate\Support\Facades\Route;

// Public Pages
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/features', function () {
    return view('features.index');
})->name('features');

Route::get('/features/{feature}', function ($feature) {
    return view('features.' . $feature);
})->name('features.show');

Route::get('/pricing', function () {
    return view('pricing');
})->name('pricing');

Route::get('/security', function () {
    return view('security');
})->name('security');

Route::get('/compliance', function () {
    return view('compliance');
})->name('compliance');

Route::get('/developers', function () {
    return view('developers.index');
})->name('developers');

Route::get('/developers/{section}', function ($section) {
    return view('developers.' . $section);
})->name('developers.show');

Route::get('/support', function () {
    return view('support.index');
})->name('support');

Route::get('/support/contact', function () {
    return view('support.contact');
})->name('support.contact');

Route::get('/support/faq', function () {
    return view('support.faq');
})->name('support.faq');

Route::get('/support/guides', function () {
    return view('support.guides');
})->name('support.guides');

Route::get('/blog', function () {
    return view('blog.index');
})->name('blog');

Route::get('/partners', function () {
    return view('partners');
})->name('partners');

Route::get('/legal/terms', function () {
    return view('legal.terms');
})->name('legal.terms');

Route::get('/legal/privacy', function () {
    return view('legal.privacy');
})->name('legal.privacy');

Route::get('/legal/cookies', function () {
    return view('legal.cookies');
})->name('legal.cookies');

Route::get('/status', function () {
    return view('status');
})->name('status');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Onboarding routes
    Route::post('/onboarding/complete', [App\Http\Controllers\OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/skip', [App\Http\Controllers\OnboardingController::class, 'skip'])->name('onboarding.skip');
    
    // KYC route
    Route::get('/compliance/kyc', function () {
        return view('compliance.kyc');
    })->name('compliance.kyc');
    
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
        
        // Wallet transaction routes
        Route::get('/deposit', [App\Http\Controllers\WalletController::class, 'showDeposit'])->name('deposit');
        Route::post('/deposit', [App\Http\Controllers\WalletController::class, 'deposit'])->name('deposit.store');
        
        Route::get('/withdraw', [App\Http\Controllers\WalletController::class, 'showWithdraw'])->name('withdraw');
        Route::post('/withdraw', [App\Http\Controllers\WalletController::class, 'withdraw'])->name('withdraw.store');
        
        Route::get('/transfer', [App\Http\Controllers\WalletController::class, 'showTransfer'])->name('transfer');
        Route::post('/transfer', [App\Http\Controllers\WalletController::class, 'transfer'])->name('transfer.store');
        
        Route::get('/convert', [App\Http\Controllers\WalletController::class, 'showConvert'])->name('convert');
        Route::post('/convert', [App\Http\Controllers\WalletController::class, 'convert'])->name('convert.store');
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
