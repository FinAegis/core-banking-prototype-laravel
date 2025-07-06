<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Lending\LoanApplicationService;
use App\Domain\Lending\Services\CreditScoringService;
use App\Domain\Lending\Services\RiskAssessmentService;
use App\Domain\Lending\Services\CollateralManagementService;
use App\Services\Lending\MockCreditScoringService;
use App\Services\Lending\DefaultRiskAssessmentService;
use App\Services\Lending\DefaultCollateralManagementService;

class LendingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register loan application service
        $this->app->singleton(LoanApplicationService::class, function ($app) {
            return new LoanApplicationService(
                $app->make(CreditScoringService::class),
                $app->make(RiskAssessmentService::class)
            );
        });
        
        // Register credit scoring service
        $this->app->bind(CreditScoringService::class, function ($app) {
            return new MockCreditScoringService();
        });
        
        // Register risk assessment service
        $this->app->bind(RiskAssessmentService::class, function ($app) {
            return new DefaultRiskAssessmentService();
        });
        
        // Register collateral management service
        $this->app->bind(CollateralManagementService::class, function ($app) {
            return new DefaultCollateralManagementService();
        });
    }
    
    public function boot()
    {
        //
    }
}