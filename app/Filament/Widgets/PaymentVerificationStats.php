<?php

namespace App\Filament\Widgets;

use App\Models\CgoInvestment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class PaymentVerificationStats extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingPayments = CgoInvestment::where('payment_status', 'pending')->count();
        $processingPayments = CgoInvestment::where('payment_status', 'processing')->count();
        $pendingAmount = CgoInvestment::where('payment_status', 'pending')->sum('amount');
        $urgentPayments = CgoInvestment::where('payment_status', 'pending')
            ->where('created_at', '<=', now()->subDay())
            ->count();
        
        // Payment method breakdown
        $stripePayments = CgoInvestment::where('payment_status', 'pending')
            ->where('payment_method', 'stripe')
            ->count();
        $cryptoPayments = CgoInvestment::where('payment_status', 'pending')
            ->where('payment_method', 'crypto')
            ->count();
        $bankPayments = CgoInvestment::where('payment_status', 'pending')
            ->where('payment_method', 'bank_transfer')
            ->count();
        
        return [
            Stat::make('Pending Verifications', $pendingPayments)
                ->description($processingPayments . ' processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayments > 10 ? 'warning' : 'primary')
                ->chart([7, 5, 8, 12, 10, 9, $pendingPayments]),
            
            Stat::make('Pending Amount', '$' . Number::abbreviate($pendingAmount, 2))
                ->description('Total value awaiting verification')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
            
            Stat::make('Urgent Payments', $urgentPayments)
                ->description('Pending >24 hours')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($urgentPayments > 0 ? 'danger' : 'success'),
            
            Stat::make('By Method', 
                "Card: {$stripePayments} | Crypto: {$cryptoPayments} | Bank: {$bankPayments}"
            )
                ->description('Payment method breakdown')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('gray'),
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '10s';
    }
}