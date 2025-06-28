<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\ContactController;

// Public Pages
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/platform', function () {
    return view('platform.index');
})->name('platform');

Route::get('/gcu', function () {
    return view('gcu.index');
})->name('gcu');

Route::get('/sub-products', function () {
    return view('sub-products.index');
})->name('sub-products');

Route::get('/sub-products/{product}', function ($product) {
    return view('sub-products.' . $product);
})->name('sub-products.show');

// Features route removed - content moved to platform page

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

// Subproduct routes
Route::get('/subproducts/exchange', function () {
    return view('subproducts.exchange');
})->name('subproducts.exchange');

Route::get('/subproducts/lending', function () {
    return view('subproducts.lending');
})->name('subproducts.lending');

Route::get('/subproducts/stablecoins', function () {
    return view('subproducts.stablecoins');
})->name('subproducts.stablecoins');

Route::get('/subproducts/treasury', function () {
    return view('subproducts.treasury');
})->name('subproducts.treasury');

// Financial institutions routes
Route::get('/financial-institutions/apply', function () {
    return view('financial-institutions.apply');
})->name('financial-institutions.apply');

Route::post('/financial-institutions/submit', function () {
    // For now, just redirect back with success message
    return redirect()->route('financial-institutions.apply')
        ->with('success', 'Thank you for your application. We will review it and contact you soon.');
})->name('financial-institutions.submit');

Route::get('/support', function () {
    return view('support.index');
})->name('support');

Route::get('/support/contact', function () {
    return view('support.contact');
})->name('support.contact');

Route::post('/support/contact', [ContactController::class, 'submit'])->name('support.contact.submit');

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

Route::get('/status', [StatusController::class, 'index'])->name('status');

Route::get('/cgo', function () {
    return view('cgo');
})->name('cgo');

Route::post('/cgo/notify', [App\Http\Controllers\CgoController::class, 'notify'])->name('cgo.notify');

// Authenticated CGO routes
Route::middleware(['auth', 'verified'])->prefix('cgo')->name('cgo.')->group(function () {
    Route::get('/invest', [App\Http\Controllers\CgoController::class, 'showInvest'])->name('invest');
    Route::post('/invest', [App\Http\Controllers\CgoController::class, 'invest']);
    Route::get('/certificate/{uuid}', [App\Http\Controllers\CgoController::class, 'downloadCertificate'])->name('certificate');
});

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
        
        // Wallet transaction routes (views only - operations handled via API)
        Route::get('/deposit', [App\Http\Controllers\WalletController::class, 'showDeposit'])->name('deposit');
        Route::get('/withdraw', [App\Http\Controllers\WalletController::class, 'showWithdraw'])->name('withdraw');
        Route::get('/transfer', [App\Http\Controllers\WalletController::class, 'showTransfer'])->name('transfer');
        Route::get('/convert', [App\Http\Controllers\WalletController::class, 'showConvert'])->name('convert');
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
