<?php

use App\Filament\Admin\Resources\AccountResource\Widgets\AccountBalanceChart;
use App\Filament\Admin\Resources\AccountResource\Widgets\RecentTransactionsChart;
use App\Filament\Admin\Resources\AccountResource\Widgets\TurnoverTrendChart;
use App\Filament\Admin\Resources\AccountResource\Widgets\AccountGrowthChart;
use App\Filament\Admin\Resources\AccountResource\Widgets\SystemHealthWidget;
use App\Models\Account;
use App\Models\Turnover;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

describe('AccountBalanceChart', function () {
    it('can render the widget', function () {
        Account::factory()->count(5)->create();
        
        Livewire::test(AccountBalanceChart::class)
            ->assertSuccessful()
            ->assertSee('Account Balance Trends');
    });
    
    it('displays filter options', function () {
        Livewire::test(AccountBalanceChart::class)
            ->assertSee('Last 24 Hours')
            ->assertSee('Last 7 Days')
            ->assertSee('Last 30 Days')
            ->assertSee('Last 90 Days');
    });
    
    it('has correct chart type', function () {
        $widget = new AccountBalanceChart();
        expect((new ReflectionMethod($widget, 'getType'))->invoke($widget))->toBe('line');
    });
});

describe('RecentTransactionsChart', function () {
    it('can render the widget', function () {
        Livewire::test(RecentTransactionsChart::class)
            ->assertSuccessful()
            ->assertSee('Transaction Volume');
    });
    
    it('displays filter options', function () {
        Livewire::test(RecentTransactionsChart::class)
            ->assertSee('Last 24 Hours')
            ->assertSee('Last 7 Days')
            ->assertSee('Last 30 Days')
            ->assertSee('Last 90 Days');
    });
    
    it('has correct chart type', function () {
        $widget = new RecentTransactionsChart();
        expect((new ReflectionMethod($widget, 'getType'))->invoke($widget))->toBe('bar');
    });
});

describe('TurnoverTrendChart', function () {
    it('can render the widget', function () {
        // Create turnovers with valid account references
        Account::factory()->count(3)->create()->each(function ($account) {
            Turnover::factory()->count(2)->create([
                'account_uuid' => $account->uuid,
            ]);
        });
        
        Livewire::test(TurnoverTrendChart::class)
            ->assertSuccessful()
            ->assertSee('Turnover Flow Analysis');
    });
    
    it('displays filter options', function () {
        Livewire::test(TurnoverTrendChart::class)
            ->assertSee('Last 3 Months')
            ->assertSee('Last 6 Months')
            ->assertSee('Last 12 Months')
            ->assertSee('Last 24 Months');
    });
    
    it('has correct chart type', function () {
        $widget = new TurnoverTrendChart();
        expect((new ReflectionMethod($widget, 'getType'))->invoke($widget))->toBe('bar');
    });
});

describe('AccountGrowthChart', function () {
    it('can render the widget', function () {
        Account::factory()->count(5)->create();
        
        Livewire::test(AccountGrowthChart::class)
            ->assertSuccessful()
            ->assertSee('Account Growth');
    });
    
    it('displays filter options', function () {
        Livewire::test(AccountGrowthChart::class)
            ->assertSee('Last 7 Days')
            ->assertSee('Last 30 Days')
            ->assertSee('Last 90 Days')
            ->assertSee('Last Year');
    });
    
    it('has correct chart type', function () {
        $widget = new AccountGrowthChart();
        expect((new ReflectionMethod($widget, 'getType'))->invoke($widget))->toBe('bar');
    });
});

describe('SystemHealthWidget', function () {
    it('can render the widget', function () {
        Livewire::test(SystemHealthWidget::class)
            ->assertSuccessful()
            ->assertSee('System Status')
            ->assertSee('Transaction Processing')
            ->assertSee('Cache Performance')
            ->assertSee('Queue Health');
    });
    
    it('shows stats overview structure', function () {
        $widget = new SystemHealthWidget();
        $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);
        
        expect($stats)->toHaveCount(4);
        // Stats are objects with protected properties, so we can't access them directly
        expect($stats)->toBeArray();
    });
});

describe('Chart Widget Configuration', function () {
    it('all chart widgets extend the base ChartWidget class', function () {
        $widgets = [
            AccountBalanceChart::class,
            RecentTransactionsChart::class,
            TurnoverTrendChart::class,
            AccountGrowthChart::class,
        ];
        
        foreach ($widgets as $widget) {
            expect(is_subclass_of($widget, ChartWidget::class))->toBeTrue();
        }
    });
    
    it('all widgets have proper polling intervals', function () {
        $widgets = [
            AccountBalanceChart::class => '30s',
            RecentTransactionsChart::class => '30s',
            TurnoverTrendChart::class => '60s',
            AccountGrowthChart::class => '60s',
            SystemHealthWidget::class => '10s',
        ];
        
        foreach ($widgets as $widget => $expectedInterval) {
            $reflection = new ReflectionClass($widget);
            $property = $reflection->getProperty('pollingInterval');
            $property->setAccessible(true);
            
            expect($property->getValue())->toBe($expectedInterval);
        }
    });
    
    it('all chart widgets have column span configuration', function () {
        $widgets = [
            AccountBalanceChart::class => 'full',
            RecentTransactionsChart::class => 'full',
            TurnoverTrendChart::class => 'full',
            AccountGrowthChart::class => '1',
        ];
        
        foreach ($widgets as $widgetClass => $expectedSpan) {
            $widget = new $widgetClass();
            $reflection = new ReflectionClass($widget);
            $property = $reflection->getProperty('columnSpan');
            $property->setAccessible(true);
            
            expect($property->getValue($widget))->toBe($expectedSpan);
        }
    });
});