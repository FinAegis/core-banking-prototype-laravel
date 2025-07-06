<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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