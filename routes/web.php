<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GCUController;

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

Route::get('/gcu', [GCUController::class, 'index'])->name('gcu');

Route::get('/sub-products', function () {
    return view('sub-products.index');
})->name('sub-products');

Route::get('/sub-products/{product}', function ($product) {
    return view('sub-products.' . $product);
})->name('sub-products.show');

// Features routes
Route::get('/features', function () {
    return view('features.index');
})->name('features');

Route::get('/features/gcu', function () {
    return view('features.gcu');
})->name('features.gcu');

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
Route::get('/financial-institutions/apply', [App\Http\Controllers\FinancialInstitutionApplicationController::class, 'show'])
    ->name('financial-institutions.apply');

Route::post('/financial-institutions/submit', [App\Http\Controllers\FinancialInstitutionApplicationController::class, 'submit'])
    ->name('financial-institutions.submit');

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

Route::get('/blog', [App\Http\Controllers\BlogController::class, 'index'])->name('blog');
Route::post('/blog/subscribe', [App\Http\Controllers\BlogController::class, 'subscribe'])->name('blog.subscribe');

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

Route::get('/cgo/terms', function () {
    return view('legal.cgo-terms');
})->name('cgo.terms');

Route::post('/cgo/notify', [App\Http\Controllers\CgoController::class, 'notify'])->name('cgo.notify');

// Authenticated CGO routes
Route::middleware(['auth', 'verified'])->prefix('cgo')->name('cgo.')->group(function () {
    Route::get('/invest', [App\Http\Controllers\CgoController::class, 'showInvest'])->name('invest');
    Route::post('/invest', [App\Http\Controllers\CgoController::class, 'invest']);
    Route::get('/certificate/{uuid}', [App\Http\Controllers\CgoController::class, 'downloadCertificate'])->name('certificate');
});

// GCU Voting routes (public and authenticated)
Route::prefix('gcu/voting')->name('gcu.voting.')->group(function () {
    Route::get('/', [App\Http\Controllers\GcuVotingController::class, 'index'])->name('index');
    Route::get('/{proposal}', [App\Http\Controllers\GcuVotingController::class, 'show'])->name('show');
    
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('/{proposal}/vote', [App\Http\Controllers\GcuVotingController::class, 'vote'])->name('vote');
        Route::get('/create', [App\Http\Controllers\GcuVotingController::class, 'create'])->name('create');
        Route::post('/store', [App\Http\Controllers\GcuVotingController::class, 'store'])->name('store');
    });
});

// GCU Trading routes (authenticated)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/gcu/trading', [App\Http\Controllers\GcuTradingController::class, 'index'])->name('gcu.trading');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Team Member Management (for business organizations)
    Route::prefix('teams/{team}')->name('teams.')->group(function () {
        Route::get('/members', [App\Http\Controllers\TeamMemberController::class, 'index'])->name('members.index');
        Route::get('/members/create', [App\Http\Controllers\TeamMemberController::class, 'create'])->name('members.create');
        Route::post('/members', [App\Http\Controllers\TeamMemberController::class, 'store'])->name('members.store');
        Route::get('/members/{user}/edit', [App\Http\Controllers\TeamMemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{user}', [App\Http\Controllers\TeamMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{user}', [App\Http\Controllers\TeamMemberController::class, 'destroy'])->name('members.destroy');
    });
    
    // Onboarding routes
    Route::post('/onboarding/complete', [App\Http\Controllers\OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/skip', [App\Http\Controllers\OnboardingController::class, 'skip'])->name('onboarding.skip');
    
    // API Key Management
    Route::resource('api-keys', App\Http\Controllers\ApiKeyController::class);
    Route::post('/api-keys/{apiKey}/regenerate', [App\Http\Controllers\ApiKeyController::class, 'regenerate'])->name('api-keys.regenerate');
    
    // Compliance Routes
    Route::prefix('compliance')->name('compliance.')->group(function () {
        // KYC route
        Route::get('/kyc', function () {
            return view('compliance.kyc');
        })->name('kyc');
        
        // Compliance Metrics
        Route::get('/metrics', function () {
            $metrics = [
                'overall_score' => 94.5,
                'kyc_rate' => 98.2,
                'pending_kyc' => 12,
                'aml_alerts' => 23,
                'resolved_alerts' => 18,
                'sanctions_hits' => 2,
                'risk_score' => 'Low'
            ];
            
            return view('compliance.metrics', compact('metrics'));
        })->name('metrics');
        
        // AML/BSA/OFAC Reporting
        Route::get('/aml', function () {
            $stats = [
                'active_alerts' => 18,
                'new_today' => 3,
                'ofac_matches' => 2,
                'bsa_reports' => 45,
                'pending_bsa' => 5,
                'risk_score' => 'Low'
            ];
            
            return view('compliance.aml-reporting', compact('stats'));
        })->name('aml');
        Route::get('/aml/create', function () {
            return redirect()->route('regulatory.reports.create');
        })->name('aml.create');
        Route::get('/bsa/create', function () {
            return redirect()->route('regulatory.reports.create');
        })->name('bsa.create');
        Route::get('/risk/assessment', function () {
            return redirect()->route('risk.analysis.index');
        })->name('risk.assessment');
    });
    
    // Audit Trail Routes
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/trail', function () {
            $auditLogs = collect(); // Empty collection for now
            return view('audit.trail', compact('auditLogs'));
        })->name('trail');
    });
    
    // Fraud Alerts Routes
    Route::prefix('fraud')->name('fraud.')->group(function () {
        Route::get('/alerts', [App\Http\Controllers\FraudAlertsController::class, 'index'])->name('alerts.index');
        Route::get('/alerts/export', [App\Http\Controllers\FraudAlertsController::class, 'export'])->name('alerts.export');
        Route::get('/alerts/{fraudCase}', [App\Http\Controllers\FraudAlertsController::class, 'show'])->name('alerts.show');
        Route::patch('/alerts/{fraudCase}/status', [App\Http\Controllers\FraudAlertsController::class, 'updateStatus'])->name('alerts.update-status');
    });
    
    // Regulatory Reports Routes
    Route::prefix('regulatory')->name('regulatory.')->group(function () {
        Route::get('/reports', [App\Http\Controllers\RegulatoryReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/create', [App\Http\Controllers\RegulatoryReportsController::class, 'create'])->name('reports.create');
        Route::post('/reports', [App\Http\Controllers\RegulatoryReportsController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}', [App\Http\Controllers\RegulatoryReportsController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/download', [App\Http\Controllers\RegulatoryReportsController::class, 'download'])->name('reports.download');
        Route::post('/reports/{report}/submit', [App\Http\Controllers\RegulatoryReportsController::class, 'submit'])->name('reports.submit');
    });
    
    // Risk Analysis Routes
    Route::prefix('risk')->name('risk.')->group(function () {
        Route::get('/analysis', function () {
            // Mock data for demonstration
            $stats = [
                'low_risk' => 1243,
                'medium_risk' => 567,
                'high_risk' => 190,
                'total_customers' => 2000,
                'avg_risk_score' => 32.5,
            ];
            
            $topRiskFactors = [
                ['name' => 'High Transaction Volume', 'count' => 234, 'percentage' => 45],
                ['name' => 'Geographic Risk', 'count' => 189, 'percentage' => 36],
                ['name' => 'PEP Status', 'count' => 123, 'percentage' => 24],
                ['name' => 'Unusual Transaction Patterns', 'count' => 98, 'percentage' => 19],
                ['name' => 'Business Type Risk', 'count' => 87, 'percentage' => 17],
            ];
            
            $highRiskCustomers = collect(); // Empty collection for now
            
            return view('risk.analysis.index', compact('stats', 'topRiskFactors', 'highRiskCustomers'));
        })->name('analysis.index');
    });
    
    // Transaction Monitoring Routes
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/transactions', function () {
            return view('monitoring.transactions.index');
        })->name('transactions.index');
    });
    
    // Account Management Routes
    Route::get('/accounts', function () {
        $accounts = Auth::user()->accounts()->with('balances.asset')->get();
        return view('accounts.index', compact('accounts'));
    })->name('accounts');
    
    Route::post('/accounts/create', function (Request $request) {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);
            
            $user = Auth::user();
            
            \Log::info('Creating account for user', [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'account_name' => $request->name
            ]);
            
            // Use the AccountService to create the account via event sourcing
            $accountService = app(\App\Domain\Account\Services\AccountService::class);
            $account = new \App\Domain\Account\DataObjects\Account(
                name: $request->name,
                userUuid: $user->uuid
            );
            
            $accountService->create($account);
            
            // Process the workflow queue immediately
            \Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--queue' => 'default,events,ledger,transactions'
            ]);
            
            // Verify account was created
            $createdAccount = \App\Models\Account::where('user_uuid', $user->uuid)
                ->where('name', $request->name)
                ->first();
                
            \Log::info('Account creation result', [
                'account_found' => $createdAccount ? true : false,
                'account_id' => $createdAccount ? $createdAccount->id : null
            ]);
            
            return response()->json([
                'success' => true,
                'account_created' => $createdAccount ? true : false
            ]);
        } catch (\Exception $e) {
            \Log::error('Account creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ], 500);
        }
    })->name('accounts.create');
    
    // Transaction History Route
    Route::get('/transactions', function () {
        return redirect()->route('wallet.transactions');
    })->name('transactions');
    
    // Transaction Status Tracking Routes
    Route::get('/transactions/status', [App\Http\Controllers\TransactionStatusController::class, 'index'])->name('transactions.status');
    Route::prefix('transactions/status')->name('transactions.status.')->group(function () {
        Route::get('/{transactionId}', [App\Http\Controllers\TransactionStatusController::class, 'show'])->name('show');
        Route::get('/{transactionId}/status', [App\Http\Controllers\TransactionStatusController::class, 'status'])->name('status');
        Route::post('/{transactionId}/cancel', [App\Http\Controllers\TransactionStatusController::class, 'cancel'])->name('cancel');
        Route::post('/{transactionId}/retry', [App\Http\Controllers\TransactionStatusController::class, 'retry'])->name('retry');
    });
    
    // Fund Flow Visualization Routes
    Route::prefix('fund-flow')->name('fund-flow.')->group(function () {
        Route::get('/', [App\Http\Controllers\FundFlowController::class, 'index'])->name('index');
        Route::get('/account/{accountUuid}', [App\Http\Controllers\FundFlowController::class, 'accountFlow'])->name('account');
        Route::get('/data', [App\Http\Controllers\FundFlowController::class, 'data'])->name('data');
    });
    
    // Exchange Rate Viewer Routes
    Route::prefix('exchange-rates')->name('exchange-rates.')->group(function () {
        Route::get('/', [App\Http\Controllers\ExchangeRateViewController::class, 'index'])->name('index');
        Route::post('/rates', [App\Http\Controllers\ExchangeRateViewController::class, 'rates'])->name('rates');
        Route::get('/historical', [App\Http\Controllers\ExchangeRateViewController::class, 'historical'])->name('historical');
    });
    
    // Batch Processing Routes
    Route::prefix('batch-processing')->name('batch-processing.')->group(function () {
        Route::get('/', [App\Http\Controllers\BatchProcessingController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\BatchProcessingController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\BatchProcessingController::class, 'store'])->name('store');
        Route::get('/{batchJob}', [App\Http\Controllers\BatchProcessingController::class, 'show'])->name('show');
        Route::post('/{batchJob}/cancel', [App\Http\Controllers\BatchProcessingController::class, 'cancel'])->name('cancel');
        Route::post('/{batchJob}/retry', [App\Http\Controllers\BatchProcessingController::class, 'retry'])->name('retry');
        Route::get('/{batchJob}/download', [App\Http\Controllers\BatchProcessingController::class, 'download'])->name('download');
    });
    
    // Asset Management Routes
    Route::prefix('asset-management')->name('asset-management.')->group(function () {
        Route::get('/', [App\Http\Controllers\AssetManagementController::class, 'index'])->name('index');
        Route::get('/analytics', [App\Http\Controllers\AssetManagementController::class, 'analytics'])->name('analytics');
        Route::get('/export', [App\Http\Controllers\AssetManagementController::class, 'export'])->name('export');
        Route::get('/{asset}', [App\Http\Controllers\AssetManagementController::class, 'show'])->name('show');
    });
    
    // Transfer Route
    Route::get('/transfers', function () {
        return redirect()->route('wallet.transfer');
    })->name('transfers');
    
    // Exchange Route
    Route::get('/exchange', function () {
        return redirect()->route('wallet.convert');
    })->name('exchange');
    
    // GCU Wallet Routes
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [App\Http\Controllers\WalletController::class, 'index'])->name('index');
        
        Route::get('/bank-allocation', function () {
            return view('wallet.bank-allocation');
        })->name('bank-allocation');
        
        Route::get('/voting', function () {
            return redirect()->route('gcu.voting.index');
        })->name('voting');
        
        Route::get('/transactions', [App\Http\Controllers\WalletController::class, 'transactions'])->name('transactions');
        
        // Wallet transaction routes (views only - operations handled via API)
        Route::get('/deposit', [App\Http\Controllers\WalletController::class, 'showDeposit'])->name('deposit');
        Route::get('/withdraw', [App\Http\Controllers\WalletController::class, 'showWithdraw'])->name('withdraw');
        Route::get('/transfer', [App\Http\Controllers\WalletController::class, 'showTransfer'])->name('transfer');
        Route::get('/convert', [App\Http\Controllers\WalletController::class, 'showConvert'])->name('convert');
        
        // New Interface Routes
        // Stablecoin Operations
        Route::prefix('stablecoin-operations')->name('stablecoin-operations.')->group(function () {
            Route::get('/', [App\Http\Controllers\StablecoinOperationsController::class, 'index'])->name('index');
            Route::get('/mint', [App\Http\Controllers\StablecoinOperationsController::class, 'mint'])->name('mint');
            Route::post('/mint', [App\Http\Controllers\StablecoinOperationsController::class, 'processMint'])->name('mint.process');
            Route::get('/burn', [App\Http\Controllers\StablecoinOperationsController::class, 'burn'])->name('burn');
            Route::post('/burn', [App\Http\Controllers\StablecoinOperationsController::class, 'processBurn'])->name('burn.process');
            Route::get('/history', [App\Http\Controllers\StablecoinOperationsController::class, 'history'])->name('history');
        });
        
        // Custodian Integration Status
        Route::prefix('custodian-integration')->name('custodian-integration.')->group(function () {
            Route::get('/', [App\Http\Controllers\CustodianIntegrationController::class, 'index'])->name('index');
            Route::get('/{custodianCode}', [App\Http\Controllers\CustodianIntegrationController::class, 'show'])->name('show');
            Route::post('/{custodianCode}/test-connection', [App\Http\Controllers\CustodianIntegrationController::class, 'testConnection'])->name('test-connection');
            Route::post('/{custodianCode}/synchronize', [App\Http\Controllers\CustodianIntegrationController::class, 'synchronize'])->name('synchronize');
        });
        
        // Deposit routes
        Route::prefix('deposit')->name('deposit.')->group(function () {
            // Card deposits (Stripe integration)
            Route::get('/card', [App\Http\Controllers\DepositController::class, 'create'])->name('create');
            Route::post('/card', [App\Http\Controllers\DepositController::class, 'store'])->name('store');
            Route::get('/confirm', [App\Http\Controllers\DepositController::class, 'confirm'])->name('confirm');
            Route::post('/payment-method', [App\Http\Controllers\DepositController::class, 'addPaymentMethod'])->name('payment-method.add');
            Route::delete('/payment-method/{id}', [App\Http\Controllers\DepositController::class, 'removePaymentMethod'])->name('payment-method.remove');
            
            // Bank deposits
            Route::get('/bank', function () {
                $account = Auth::user()->accounts()->first();
                return view('wallet.deposit-bank', compact('account'));
            })->name('bank');
            
            // Paysera deposits
            Route::get('/paysera', function () {
                $account = Auth::user()->accounts()->first();
                return view('wallet.deposit-paysera', compact('account'));
            })->name('paysera');
            Route::post('/paysera/initiate', [App\Http\Controllers\PayseraDepositController::class, 'initiate'])->name('paysera.initiate');
            Route::get('/paysera/callback', [App\Http\Controllers\PayseraDepositController::class, 'callback'])->name('paysera.callback');
            
            // Open Banking deposits
            Route::get('/openbanking', function () {
                $account = Auth::user()->accounts()->first();
                return view('wallet.deposit-openbanking', compact('account'));
            })->name('openbanking');
            Route::post('/openbanking/initiate', [App\Http\Controllers\OpenBankingDepositController::class, 'initiate'])->name('openbanking.initiate');
            Route::get('/openbanking/callback', [App\Http\Controllers\OpenBankingDepositController::class, 'callback'])->name('openbanking.callback');
            
            // Manual bank transfer
            Route::get('/manual', function () {
                $account = Auth::user()->accounts()->first();
                return view('wallet.deposit-manual', compact('account'));
            })->name('manual');
            
            // Crypto deposits (placeholder)
            Route::get('/crypto', function () {
                return view('wallet.deposit-crypto');
            })->name('crypto');
        });
        
        // Bank withdrawal routes
        Route::prefix('withdraw')->name('withdraw.')->group(function () {
            Route::get('/bank', [App\Http\Controllers\WithdrawalController::class, 'create'])->name('create');
            Route::post('/bank', [App\Http\Controllers\WithdrawalController::class, 'store'])->name('store');
            Route::post('/bank-account', [App\Http\Controllers\WithdrawalController::class, 'addBankAccount'])->name('bank-account.add');
            Route::delete('/bank-account/{bankAccount}', [App\Http\Controllers\WithdrawalController::class, 'removeBankAccount'])->name('bank-account.remove');
            
            // OpenBanking withdrawal routes
            Route::get('/openbanking', [App\Http\Controllers\OpenBankingWithdrawalController::class, 'create'])->name('openbanking');
            Route::post('/openbanking/initiate', [App\Http\Controllers\OpenBankingWithdrawalController::class, 'initiate'])->name('openbanking.initiate');
            Route::get('/openbanking/callback', [App\Http\Controllers\OpenBankingWithdrawalController::class, 'callback'])->name('openbanking.callback');
            Route::post('/openbanking/select-account', [App\Http\Controllers\OpenBankingWithdrawalController::class, 'selectAccount'])->name('openbanking.select-account');
            Route::post('/openbanking/process', [App\Http\Controllers\OpenBankingWithdrawalController::class, 'processWithAccount'])->name('openbanking.process');
        });
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
